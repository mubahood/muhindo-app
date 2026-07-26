<?php

namespace App\Services\Learning;

use App\Models\LearningEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * §6.5 — the "optional weekly streak counter": consecutive ISO weeks (ending this week or
 * last week — a week still in progress doesn't break a streak that's still alive) with at
 * least one `learning_events` row across any of the user's enrollments. Computed in PHP
 * rather than a DB-specific week function (`YEARWEEK` is MySQL-only; tests run on SQLite),
 * so this stays portable across both.
 */
class StreakService
{
    public function currentWeeklyStreak(User $user): int
    {
        $enrollmentIds = $user->enrollments()->pluck('id');
        if ($enrollmentIds->isEmpty()) {
            return 0;
        }

        $activeDates = LearningEvent::whereIn('enrollment_id', $enrollmentIds)
            ->selectRaw('DATE(created_at) as d')
            ->distinct()
            ->pluck('d');

        if ($activeDates->isEmpty()) {
            return 0;
        }

        $activeWeeks = $activeDates->map(fn ($date) => Carbon::parse($date)->format('oW'))->unique();

        $cursor = now();
        if (! $activeWeeks->contains($cursor->format('oW'))) {
            $cursor = $cursor->copy()->subWeek();
            if (! $activeWeeks->contains($cursor->format('oW'))) {
                return 0;
            }
        }

        $streak = 0;
        while ($activeWeeks->contains($cursor->format('oW'))) {
            $streak++;
            $cursor = $cursor->copy()->subWeek();
        }

        return $streak;
    }
}
