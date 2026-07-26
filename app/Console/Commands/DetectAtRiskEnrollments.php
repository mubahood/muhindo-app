<?php

namespace App\Console\Commands;

use App\Enums\LearningEventType;
use App\Models\Enrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * §6.4 — nightly rules-first at-risk tagging (no ML pretence). Only the two
 * rules computable from data that exists today are implemented:
 * - inactive: no activity (or never started) in 14 days.
 * - stalled: active in the last 3 weeks but nothing completed in that window
 *   (the closest honest proxy for "progress unchanged despite logins" without
 *   a progress-history table).
 * `struggling` (quiz average) and `missing_work` (assignment due date) wait
 * on the P3 quiz/assignment models.
 */
class DetectAtRiskEnrollments extends Command
{
    protected $signature = 'app:detect-at-risk-enrollments';

    protected $description = 'Tag active enrollments as inactive/stalled based on activity (§6.4)';

    public function handle(): int
    {
        $inactiveThreshold = now()->subDays(14);
        $stalledWindow = now()->subDays(21);
        $counts = ['inactive' => 0, 'stalled' => 0, 'cleared' => 0];

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

        $this->info("At-risk detection complete: {$counts['inactive']} inactive, {$counts['stalled']} stalled.");

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

        return null;
    }
}
