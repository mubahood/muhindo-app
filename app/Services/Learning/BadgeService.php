<?php

namespace App\Services\Learning;

use App\Enums\BadgeType;
use App\Enums\QuizAttemptStatus;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserBadge;

/** §6.5/§4.5 — awards are idempotent (`firstOrCreate`), so every caller can check-and-award freely without double-issuing. */
class BadgeService
{
    public function __construct(private readonly StreakService $streaks) {}

    public function awardCourseCompletionBadges(User $user): void
    {
        $completedCount = Enrollment::where('user_id', $user->id)->where('status', 'completed')->count();

        if ($completedCount >= 1) {
            $this->award($user, BadgeType::FirstCourseCompleted);
        }
        if ($completedCount >= 5) {
            $this->award($user, BadgeType::FiveCoursesCompleted);
        }
    }

    public function awardPerfectQuizBadgeIfEarned(QuizAttempt $attempt): void
    {
        if ($attempt->status === QuizAttemptStatus::Graded && (float) $attempt->score_percent === 100.0) {
            $this->award($attempt->enrollment->user, BadgeType::PerfectQuiz);
        }
    }

    public function awardStreakBadgeIfEligible(User $user): void
    {
        if ($this->streaks->currentWeeklyStreak($user) >= 4) {
            $this->award($user, BadgeType::FourWeekStreak);
        }
    }

    private function award(User $user, BadgeType $type): void
    {
        UserBadge::firstOrCreate(['user_id' => $user->id, 'badge_type' => $type->value]);
    }
}
