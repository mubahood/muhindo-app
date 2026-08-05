<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Learning\BadgeService;
use Illuminate\Console\Command;

/**
 * The 4-week-streak badge is time-based, not action-based, so unlike the
 * completion/perfect-quiz badges it has no single triggering event; a nightly
 * command (mirroring `app:detect-at-risk-enrollments`'s pattern) is the natural fit.
 */
class AwardStreakBadges extends Command
{
    protected $signature = 'app:award-streak-badges';

    protected $description = 'Award the 4-week-streak badge to students who have earned it';

    public function handle(BadgeService $badges): int
    {
        $count = 0;

        User::where('role', 'student')
            ->whereHas('enrollments')
            ->chunkById(200, function ($students) use ($badges, &$count) {
                foreach ($students as $student) {
                    $badges->awardStreakBadgeIfEligible($student);
                    $count++;
                }
            });

        $this->info("Streak badge check complete: {$count} student(s) evaluated.");

        return self::SUCCESS;
    }
}
