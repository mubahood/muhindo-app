<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\WeeklyInstructorDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Weekly "5 students at risk: ..." digest, sent to every admin. Runs
 * after the nightly `app:detect-at-risk-enrollments` job has had a chance to
 * refresh `at_risk_reason` for the week. Genuinely optional: if nothing is
 * flagged, no email goes out at all.
 */
class SendWeeklyInstructorDigest extends Command
{
    protected $signature = 'app:send-weekly-instructor-digest';

    protected $description = 'Email admins a weekly digest of at-risk enrollments';

    public function handle(): int
    {
        $atRisk = Enrollment::where('status', 'active')
            ->whereNotNull('at_risk_reason')
            ->with(['user', 'course'])
            ->orderBy('at_risk_reason')
            ->get();

        if ($atRisk->isEmpty()) {
            $this->info('No at-risk enrollments this week, digest skipped.');

            return self::SUCCESS;
        }

        $admins = User::whereIn('role', ['super_admin', 'admin'])->get();
        Notification::send($admins, new WeeklyInstructorDigestNotification($atRisk));

        $this->info("Weekly digest sent to {$admins->count()} admin(s): {$atRisk->count()} at-risk enrollment(s).");

        return self::SUCCESS;
    }
}
