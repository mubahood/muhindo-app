<?php

namespace App\Support\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Everything the shared learning shell (`layouts/learn`) renders around a course
 * page: the chapter/lesson sidebar with per-module completion counts, overall
 * progress, and the current lesson's position.
 *
 * The layout builds this itself from the course + signed-in user, so every
 * course-context page (lesson, quizzes, assignments, Q&A, grades, …) gets
 * identical chrome without each controller having to pass sidebar data.
 */
class LearnShell
{
    public readonly ?Enrollment $enrollment;

    /** @var Collection<int,array{model: \App\Models\CourseModule, lessons: Collection<int,Lesson>, total: int, done: int, isCurrent: bool}> */
    public readonly Collection $modules;

    /** @var Collection<int,Lesson> */
    public readonly Collection $flatLessons;

    /** @var Collection<int,int> */
    public readonly Collection $completedLessonIds;

    /** @var Collection<int,int> */
    public readonly Collection $lockedLessonIds;

    public function __construct(
        public readonly Course $course,
        ?User $user = null,
        public readonly ?Lesson $currentLesson = null,
    ) {
        $this->enrollment = $user
            ? Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first()
            : null;

        $course->loadMissing('modules.lessons');

        $this->completedLessonIds = $this->enrollment
            ? $this->enrollment->progressRecords()->whereNotNull('completed_at')->pluck('lesson_id')
            : collect();

        $modules = [];
        foreach ($course->modules as $module) {
            $described = $this->describeModule($module);
            if ($described['total'] > 0) {
                $modules[] = $described;
            }
        }
        $this->modules = collect($modules);

        $this->flatLessons = $this->modules->flatMap(fn (array $m) => $m['lessons']);

        // A lesson is only "locked" for a real enrollment; without one there's
        // nothing to gate against, so the sidebar renders every lesson plainly.
        $this->lockedLessonIds = $this->enrollment
            ? $this->flatLessons
                ->filter(fn (Lesson $l) => $course->isLessonLocked($this->enrollment, $l))
                ->pluck('id')
            : collect();
    }

    /** @return array{model: \App\Models\CourseModule, lessons: Collection<int,Lesson>, total: int, done: int, isCurrent: bool} */
    private function describeModule(\App\Models\CourseModule $module): array
    {
        $published = $module->lessons->where('is_published', true)->values();

        return [
            'model' => $module,
            'lessons' => $published,
            'total' => $published->count(),
            'done' => $published->pluck('id')->intersect($this->completedLessonIds)->count(),
            'isCurrent' => $this->currentLesson !== null && $published->contains('id', $this->currentLesson->id),
        ];
    }

    public function totalLessons(): int
    {
        return $this->flatLessons->count();
    }

    public function doneLessons(): int
    {
        return (int) $this->modules->sum('done');
    }

    public function progressPercent(): int
    {
        return $this->totalLessons() > 0
            ? (int) round($this->doneLessons() / $this->totalLessons() * 100)
            : 0;
    }

    /** 1-based position of the current lesson in the flattened curriculum (0 when not on a lesson page). */
    public function lessonPosition(): int
    {
        if ($this->currentLesson === null) {
            return 0;
        }

        $index = $this->flatLessons->search(fn (Lesson $l) => $l->id === $this->currentLesson->id);

        return $index === false ? 0 : $index + 1;
    }

    /** Completed lessons in the module holding the current lesson — the sidebar's live counter. */
    public function currentModuleDone(): int
    {
        return (int) ($this->modules->firstWhere('isCurrent', true)['done'] ?? 0);
    }
}
