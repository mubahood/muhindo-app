<?php

namespace App\Services\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizGradingMethod;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;

/**
 * §5.4 — per-enrollment grades: each quiz's counted attempt (per its grading_method), each
 * assignment's returned points (late penalty applied), averaged into one course grade. An
 * ungraded/not-yet-attempted item is excluded from the average rather than counted as zero —
 * "current grade so far," not "final grade assuming zeros for everything outstanding."
 */
class GradebookService
{
    /** @return array<int, array{type: string, id: int, title: string, percent: ?float, max_points: float}> */
    public function itemsFor(Enrollment $enrollment): array
    {
        $items = [];

        foreach ($enrollment->course->quizzes()->where('is_published', true)->get() as $quiz) {
            $items[] = [
                'type' => 'quiz',
                'id' => $quiz->id,
                'title' => $quiz->title,
                'percent' => $this->quizGradePercent($enrollment, $quiz),
                'max_points' => 100.0,
            ];
        }

        foreach ($enrollment->course->assignments()->where('is_published', true)->get() as $assignment) {
            $items[] = [
                'type' => 'assignment',
                'id' => $assignment->id,
                'title' => $assignment->title,
                'percent' => $this->assignmentGradePercent($enrollment, $assignment),
                'max_points' => (float) $assignment->points,
            ];
        }

        return $items;
    }

    /** The weighted course grade — equal weight per graded item, per §5.4 ("weights default equal"). */
    public function courseGradePercent(Enrollment $enrollment): ?float
    {
        return $this->courseGradePercentFromItems($this->itemsFor($enrollment));
    }

    /**
     * Same computation as courseGradePercent(), but from an already-fetched item list — lets a
     * caller iterating many enrollments (the admin grade matrix) avoid re-querying itemsFor()
     * a second time per row just to get the course grade.
     *
     * @param  array<int, array{type: string, id: int, title: string, percent: ?float, max_points: float}>  $items
     */
    public function courseGradePercentFromItems(array $items): ?float
    {
        $graded = array_filter($items, fn (array $item) => $item['percent'] !== null);

        if ($graded === []) {
            return null;
        }

        return round(array_sum(array_column($graded, 'percent')) / count($graded), 2);
    }

    private function quizGradePercent(Enrollment $enrollment, Quiz $quiz): ?float
    {
        $attempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('enrollment_id', $enrollment->id)
            ->where('status', QuizAttemptStatus::Graded)
            ->get();

        if ($attempts->isEmpty()) {
            return null;
        }

        $percent = match ($quiz->grading_method) {
            QuizGradingMethod::Highest => (float) $attempts->max('score_percent'),
            QuizGradingMethod::Latest => (float) $attempts->sortByDesc('attempt_no')->first()->score_percent,
            QuizGradingMethod::First => (float) $attempts->sortBy('attempt_no')->first()->score_percent,
            QuizGradingMethod::Average => (float) $attempts->avg('score_percent'),
        };

        return round($percent, 2);
    }

    private function assignmentGradePercent(Enrollment $enrollment, Assignment $assignment): ?float
    {
        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('enrollment_id', $enrollment->id)
            ->where('status', AssignmentSubmissionStatus::Returned)
            ->latest('attempt_no')
            ->first();

        if (! $submission || $assignment->points <= 0) {
            return null;
        }

        $percent = ((float) $submission->points_awarded / $assignment->points) * 100;

        if ($submission->is_late && $assignment->late_penalty_percent) {
            $percent *= 1 - ($assignment->late_penalty_percent / 100);
        }

        return round(max(0, $percent), 2);
    }
}
