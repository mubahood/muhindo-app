<?php

namespace App\Console\Commands;

use App\Enums\LearningEventType;
use App\Enums\QuizAttemptStatus;
use App\Models\Enrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Nightly rules-first at-risk tagging (no ML pretence), first match wins
 * in the order the plan lists them:
 * - inactive: no activity (or never started) in 14 days.
 * - stalled: active in the last 3 weeks but nothing completed in that window
 *   (the closest honest proxy for "progress unchanged despite logins" without
 *   a progress-history table).
 * - struggling: on any quiz, the graded-attempt average is below that quiz's
 *   own pass mark, or the two most recent graded attempts both failed.
 * - missing_work: any published assignment past its due date has no
 *   submission at all from this enrollment.
 * The latter two were deferred pending the quiz/assignment models,
 * which now exist.
 */
class DetectAtRiskEnrollments extends Command
{
    protected $signature = 'app:detect-at-risk-enrollments';

    protected $description = 'Tag active enrollments as inactive/stalled/struggling/missing_work based on activity and performance';

    public function handle(): int
    {
        $inactiveThreshold = now()->subDays(14);
        $stalledWindow = now()->subDays(21);
        $counts = ['inactive' => 0, 'stalled' => 0, 'struggling' => 0, 'missing_work' => 0, 'cleared' => 0];

        Enrollment::where('status', 'active')
            ->chunkById(200, function ($enrollments) use ($inactiveThreshold, $stalledWindow, &$counts) {
                foreach ($enrollments as $enrollment) {
                    $reason = $this->determineReason($enrollment, $inactiveThreshold, $stalledWindow);

                    if ($reason !== $enrollment->at_risk_reason) {
                        $enrollment->update(['at_risk_reason' => $reason]);
                    }

                    $counts[$reason ?? 'cleared']++;
                }
            });

        $this->info(
            "At-risk detection complete: {$counts['inactive']} inactive, {$counts['stalled']} stalled, "
            ."{$counts['struggling']} struggling, {$counts['missing_work']} missing work."
        );

        return self::SUCCESS;
    }

    private function determineReason(Enrollment $enrollment, Carbon $inactiveThreshold, Carbon $stalledWindow): ?string
    {
        if ($enrollment->last_accessed_at) {
            if ($enrollment->last_accessed_at->lt($inactiveThreshold)) {
                return 'inactive';
            }
        } elseif (! $enrollment->enrolled_at || $enrollment->enrolled_at->lt($inactiveThreshold)) {
            return 'inactive';
        }

        $hasRecentActivity = $enrollment->learningEvents()->where('created_at', '>=', $stalledWindow)->exists();
        $hasRecentCompletion = $enrollment->learningEvents()
            ->where('event', LearningEventType::LessonCompleted->value)
            ->where('created_at', '>=', $stalledWindow)
            ->exists();

        if ($hasRecentActivity && ! $hasRecentCompletion) {
            return 'stalled';
        }

        if ($this->isStruggling($enrollment)) {
            return 'struggling';
        }

        if ($this->hasMissingWork($enrollment)) {
            return 'missing_work';
        }

        return null;
    }

    private function isStruggling(Enrollment $enrollment): bool
    {
        foreach ($enrollment->course->quizzes()->where('is_published', true)->get() as $quiz) {
            $attempts = $quiz->attempts()
                ->where('enrollment_id', $enrollment->id)
                ->where('status', QuizAttemptStatus::Graded)
                ->orderByDesc('attempt_no')
                ->get();

            if ($attempts->isEmpty()) {
                continue;
            }

            $average = (float) $attempts->avg('score_percent');
            if ($average < (float) $quiz->pass_percent) {
                return true;
            }

            if ($attempts->count() >= 2 && ! $attempts[0]->passed && ! $attempts[1]->passed) {
                return true;
            }
        }

        return false;
    }

    private function hasMissingWork(Enrollment $enrollment): bool
    {
        foreach ($enrollment->course->assignments()->where('is_published', true)->get() as $assignment) {
            if (! $assignment->isPastDue()) {
                continue;
            }

            $hasSubmission = $assignment->submissions()->where('enrollment_id', $enrollment->id)->exists();
            if (! $hasSubmission) {
                return true;
            }
        }

        return false;
    }
}
