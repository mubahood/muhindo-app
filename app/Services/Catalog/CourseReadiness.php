<?php

namespace App\Services\Catalog;

use App\Enums\LessonStage;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Support\Collection;

/**
 * How much of a course has actually been made.
 *
 * The counting is separated from the publishing on purpose: the tally is
 * displayed constantly, on the curriculum tree and in the recording queue, and
 * has to be cheap and free of side effects. The publishing happens rarely and
 * writes.
 */
class CourseReadiness
{
    /**
     * @param  iterable<Lesson>  $lessons
     * @return array{planned:int, ready:int, live:int, unrecorded:int, total:int, recorded:int, percent:int}
     */
    public static function tally(iterable $lessons): array
    {
        $counts = ['planned' => 0, 'ready' => 0, 'live' => 0, 'unrecorded' => 0];

        foreach ($lessons as $lesson) {
            $counts[$lesson->stage()->value]++;
        }

        $total = array_sum($counts);

        // "Recorded" deliberately counts Unrecorded out. A published topic
        // with no video is not progress toward finishing the course, and
        // counting it as progress is how 142 of them went unnoticed.
        $recorded = $counts['ready'] + $counts['live'];

        return $counts + [
            'total' => $total,
            'recorded' => $recorded,
            'percent' => $total > 0 ? (int) round($recorded / $total * 100) : 0,
        ];
    }

    /**
     * @return array{planned:int, ready:int, live:int, unrecorded:int, total:int, recorded:int, percent:int}
     */
    public static function forCourse(Course $course): array
    {
        return self::tally(self::lessonsOf($course));
    }

    /**
     * Publish every topic that has been recorded and is still a draft.
     *
     * It moves Ready and nothing else. It cannot publish a topic with no video,
     * which is the precise mistake a button labelled "publish everything" would
     * otherwise make easy, and it does not touch anything already live.
     *
     * @return array{published:int, skipped:int}
     */
    public static function publishReady(Course|CourseModule $scope): array
    {
        $lessons = $scope instanceof Course ? self::lessonsOf($scope) : $scope->lessons()->get();

        $published = 0;
        $skipped = 0;

        foreach ($lessons as $lesson) {
            if ($lesson->isReadyToPublish()) {
                $lesson->publish();
                $published++;

                continue;
            }

            if (! $lesson->is_published) {
                $skipped++;
            }
        }

        return ['published' => $published, 'skipped' => $skipped];
    }

    /**
     * The topics in a stage, worst first, for the recording queue.
     *
     * @return Collection<int, Lesson>
     */
    public static function inStage(Course $course, LessonStage $stage): Collection
    {
        return self::lessonsOf($course)->filter(fn (Lesson $l) => $l->stage() === $stage)->values();
    }

    /**
     * Every lesson under a course, drafts included.
     *
     * `Course::lessons()` cannot be used here: it filters to published, which
     * is the exact set this class exists to look past.
     *
     * @return Collection<int, Lesson>
     */
    public static function lessonsOf(Course $course): Collection
    {
        // setRelation, not another query: the recording queue prints each
        // topic beside its module, and without this that is one query per
        // topic on a 55-topic course.
        return $course->modules()->with('lessons')->get()
            ->flatMap(fn (\App\Models\CourseModule $module) => $module->lessons
                ->each(fn (Lesson $lesson) => $lesson->setRelation('module', $module)));
    }
}
