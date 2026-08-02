<?php

namespace App\Services\Catalog;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persists one parsed course file.
 *
 * Idempotent by natural key — course by slug, module by title within the
 * course, lesson by title within the module — so a re-run updates rather than
 * duplicates. That matters because these files will be re-imported every time
 * a title is fixed or a dead link replaced, and a catalogue that grows a second
 * copy of itself on the second run is not usable.
 *
 * Rows a human has edited are respected where it would be rude not to:
 * publication state and price are never overwritten by an import, because the
 * owner sets those, not the file.
 */
class CourseImporter
{
    /**
     * @param  array<string,bool>  $embeddable  video id => can it play in an iframe
     * @return array{course:Course, created:bool, modules:int, lessons:int, videos:int, text:int}
     */
    public function import(array $parsed, array $embeddable = []): array
    {
        return DB::transaction(function () use ($parsed, $embeddable) {
            $slug = Str::slug($parsed['title']);
            $existing = Course::withTrashed()->where('slug', $slug)->first();

            $course = $existing ?? new Course(['uuid' => (string) Str::uuid()]);
            $created = $existing === null;

            $course->fill([
                'title' => $parsed['title'],
                'slug' => $slug,
                'course_number' => $parsed['course_number'],
                'tier' => $parsed['tier'],
                'description' => $parsed['description'],
                'outcomes' => $parsed['outcomes'],
                'requirements' => $parsed['requirements'],
                'prerequisites_note' => $parsed['prerequisites_note'],
                'playlist_url' => $parsed['playlist_url'],
                'level' => $parsed['level'],
                'category' => $parsed['category'],
                'is_featured' => $parsed['is_featured'],
                'source_file' => $parsed['source_file'],
                'synced_at' => now(),
            ]);

            // The owner owns these two. An import must never republish a course
            // they unpublished, or reset a price they set.
            if ($created) {
                $course->is_published = false;
                $course->price = '0.00';
                $course->currency = 'UGX';
                $course->progression = \App\Enums\CourseProgression::Free;
            }

            $course->save();

            $counts = ['modules' => 0, 'lessons' => 0, 'videos' => 0, 'text' => 0];
            $keptModuleIds = [];

            foreach ($parsed['modules'] as $index => $parsedModule) {
                $module = CourseModule::firstOrNew([
                    'course_id' => $course->id,
                    'title' => $parsedModule['title'],
                ]);
                $module->sort_order = $index + 1;
                $module->save();

                $keptModuleIds[] = $module->id;
                $counts['modules']++;

                $keptLessonIds = [];

                foreach ($parsedModule['lessons'] as $position => $parsedLesson) {
                    $lesson = $this->upsertLesson($module, $parsedLesson, $position + 1, $embeddable);

                    $keptLessonIds[] = $lesson->id;
                    $counts['lessons']++;
                    $parsedLesson['video_id'] ? $counts['videos']++ : $counts['text']++;
                }

                // A lesson removed from the file is removed from the course.
                // Without this, re-importing after an edit leaves the old
                // version behind and the module quietly grows.
                Lesson::where('course_module_id', $module->id)
                    ->whereNotIn('id', $keptLessonIds)->delete();
            }

            CourseModule::where('course_id', $course->id)
                ->whereNotIn('id', $keptModuleIds)->delete();

            $this->upsertAssignment($course, $parsed);

            return ['course' => $course->refresh(), 'created' => $created] + $counts;
        });
    }

    private function upsertLesson(CourseModule $module, array $parsed, int $position, array $embeddable): Lesson
    {
        $lesson = Lesson::firstOrNew([
            'course_module_id' => $module->id,
            'title' => $parsed['title'],
        ]);

        $body = $parsed['body'];
        if ($parsed['is_external'] && $parsed['attribution']) {
            // Credit stays in the lesson body, visible to the student, not just
            // in a database column nobody reads.
            $body[] = '*Video by '.$parsed['attribution'].', used with thanks.*';
        }

        $videoId = $parsed['video_id'];
        $canPlayInline = $videoId === null || ($embeddable[$videoId] ?? true);

        $lesson->fill([
            'content' => trim(implode("\n\n", $body)),
            'content_format' => 'markdown',
            // The player iframes this directly, so it must be the embed form.
            // youtube-nocookie keeps YouTube from setting tracking cookies on a
            // student who only ever visited this site.
            'video_url' => $videoId ? 'https://www.youtube-nocookie.com/embed/'.$videoId : null,
            // The canonical watch URL, kept because a student may want to open
            // it on YouTube and subscribe — and because a non-embeddable video
            // has nowhere else to send them.
            'resource_url' => $videoId ? 'https://www.youtube.com/watch?v='.$videoId : null,
            'is_embeddable' => $canPlayInline,
            'is_external' => $parsed['is_external'],
            'sort_order' => $position,
            'is_published' => true,
        ]);

        $lesson->save();

        return $lesson;
    }

    private function upsertAssignment(Course $course, array $parsed): void
    {
        if (empty($parsed['assignment'])) {
            return;
        }

        $assignment = Assignment::firstOrNew([
            'course_id' => $course->id,
            'title' => $parsed['assignment']['title'],
        ]);

        $assignment->fill([
            'instructions' => $parsed['assignment']['body'],
            'points' => $assignment->points ?: 100,
            'allowed_types' => $assignment->allowed_types ?: 'text,link,zip',
            'is_required' => true,
            // Left as a draft on first import: Stage 3 writes the real brief
            // and rubric, and an assignment students can see before it has one
            // is worse than none.
            'is_published' => $assignment->exists ? $assignment->is_published : false,
        ]);

        $assignment->save();
    }
}
