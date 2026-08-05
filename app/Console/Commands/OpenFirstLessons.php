<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Console\Command;

/**
 * Opens the first lesson of every course as a free preview.
 *
 * The catalogue had 21 courses and not one previewable lesson, so a visitor
 * deciding whether to spend UGX 60,000 could read a curriculum and nothing
 * else. The first lesson is the cheapest thing to give away and the most
 * persuasive: somebody who watches it has already learned how this instructor
 * teaches, which is the actual question.
 *
 * A command rather than a migration, because it has to be re-runnable. Every
 * course imported after this needs the same treatment, and re-running must not
 * close a preview somebody opened by hand.
 */
class OpenFirstLessons extends Command
{
    protected $signature = 'courses:open-first-lessons
        {--course= : Only this course number}
        {--close : Close them again instead}
        {--dry : Show what would change and change nothing}';

    protected $description = 'Make the first lesson of every course free to preview';

    public function handle(): int
    {
        $closing = (bool) $this->option('close');

        $courses = Course::with(['modules' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('course_number')
            ->when($this->option('course'), fn ($q, $n) => $q->where('course_number', $n))
            ->get();

        $changed = 0;
        $already = 0;
        $skipped = 0;

        foreach ($courses as $course) {
            $lesson = $this->firstPlayableLesson($course);

            if (! $lesson) {
                $this->line("  <fg=yellow>skip</>  {$course->slug}, no published lesson with a video");
                $skipped++;

                continue;
            }

            if ($lesson->is_free_preview === ! $closing) {
                $already++;

                continue;
            }

            if (! $this->option('dry')) {
                $lesson->forceFill(['is_free_preview' => ! $closing])->save();
            }

            $this->line(sprintf('  <fg=green>%s</> %s, %s',
                $closing ? 'close' : 'open ', $course->slug, $lesson->title));
            $changed++;
        }

        $verb = $this->option('dry') ? 'would change' : 'changed';
        $this->info("{$verb} {$changed}; {$already} already right; {$skipped} skipped.");

        return self::SUCCESS;
    }

    /**
     * The first lesson somebody could actually watch.
     *
     * Not simply "the first row": a course whose opening lesson is a text
     * page or an unpublished draft would advertise a preview that plays
     * nothing, which is worse than offering none.
     */
    private function firstPlayableLesson(Course $course): ?Lesson
    {
        foreach ($course->modules as $module) {
            $lesson = $module->lessons()
                ->where('is_published', true)
                ->whereNotNull('video_url')
                ->orderBy('sort_order')
                ->first();

            if ($lesson) {
                return $lesson;
            }
        }

        return null;
    }
}
