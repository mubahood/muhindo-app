<?php

namespace App\Services\Learning;

use App\Enums\QuizAttemptStatus;
use App\Models\Course;
use App\Models\LessonProgress;
use Illuminate\Support\Collection;

/**
 * The course analytics tab: enrollment funnel, per-lesson drop-off,
 * and a watch-time histogram. Quiz item analysis (the fourth chart the plan
 * lists here) already exists per-quiz at `QuizAnalysisService`/
 * `admin.quizzes.analysis`, this service links out to it rather than
 * duplicating it. Every count here is scoped to `active`/`completed`
 * enrollments (the same "genuinely enrolled" definition
 * `CourseCatalogueController` already uses) so a student who abandoned
 * checkout mid-payment never inflates the funnel.
 */
class CourseAnalyticsService
{
    private const ENROLLED_STATUSES = ['active', 'completed'];

    /** @return array<string,int> ordered funnel stage => count, ready for <x-dash.bars> */
    public function funnel(Course $course): array
    {
        $base = fn () => $course->enrollments()->whereIn('status', self::ENROLLED_STATUSES);

        return [
            'enrolled' => $base()->count(),
            'started' => $base()->whereNotNull('last_accessed_at')->count(),
            'reached_25' => $base()->where('progress_percent', '>=', 25)->count(),
            'reached_50' => $base()->where('progress_percent', '>=', 50)->count(),
            'reached_75' => $base()->where('progress_percent', '>=', 75)->count(),
            'completed' => $base()->where('status', 'completed')->count(),
            'certified' => $base()->whereHas('certificate')->count(),
        ];
    }

    /** @return array<int,array{lesson_id:int,title:string,completed_count:int,completion_rate:float}> */
    public function lessonDropOff(Course $course): array
    {
        $enrollmentIds = $course->enrollments()->whereIn('status', self::ENROLLED_STATUSES)->pluck('id');
        $enrolledCount = $enrollmentIds->count();

        $rows = [];
        foreach ($this->orderedPublishedLessons($course) as $lesson) {
            $completedCount = $enrolledCount > 0
                ? LessonProgress::where('lesson_id', $lesson->id)
                    ->whereIn('enrollment_id', $enrollmentIds)
                    ->whereNotNull('completed_at')
                    ->count()
                : 0;

            $rows[] = [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'completed_count' => $completedCount,
                'completion_rate' => $enrolledCount > 0 ? round($completedCount / $enrolledCount * 100, 1) : 0.0,
            ];
        }

        return $rows;
    }

    /** @return array<string,int> bucket label => enrollment count, ready for <x-dash.bars> */
    public function watchTimeHistogram(Course $course): array
    {
        $buckets = ['No watch time' => 0, 'Under 30 min' => 0, '30-60 min' => 0, '1-2 hrs' => 0, '2-5 hrs' => 0, '5+ hrs' => 0];

        $seconds = $course->enrollments()->whereIn('status', self::ENROLLED_STATUSES)->pluck('total_watch_seconds');
        foreach ($seconds as $totalSeconds) {
            $minutes = (int) $totalSeconds / 60;
            $bucket = match (true) {
                (int) $totalSeconds <= 0 => 'No watch time',
                $minutes < 30 => 'Under 30 min',
                $minutes < 60 => '30-60 min',
                $minutes < 120 => '1-2 hrs',
                $minutes < 300 => '2-5 hrs',
                default => '5+ hrs',
            };
            $buckets[$bucket]++;
        }

        return $buckets;
    }

    /**
     * Per-quiz average score and attempt volume, linking out to the existing
     * per-question item analysis rather than duplicating it here.
     *
     * @return array<int,array{quiz_id:int,title:string,graded_attempts:int,average_score_percent:?float}>
     */
    public function quizSummaries(Course $course): array
    {
        $rows = [];
        foreach ($course->quizzes()->orderBy('title')->get() as $quiz) {
            $graded = $quiz->attempts()->where('status', QuizAttemptStatus::Graded);
            $count = (clone $graded)->count();

            $rows[] = [
                'quiz_id' => $quiz->id,
                'title' => $quiz->title,
                'graded_attempts' => $count,
                'average_score_percent' => $count > 0 ? round((float) (clone $graded)->avg('score_percent'), 1) : null,
            ];
        }

        return $rows;
    }

    /** @return Collection<int,\App\Models\Lesson> */
    private function orderedPublishedLessons(Course $course): Collection
    {
        $course->loadMissing('modules.lessons');

        return $course->modules->flatMap(fn ($module) => $module->lessons)->where('is_published', true)->values();
    }
}
