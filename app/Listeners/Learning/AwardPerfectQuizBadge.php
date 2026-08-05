<?php

namespace App\Listeners\Learning;

use App\Events\Learning\QuizAttemptSubmitted;
use App\Services\Learning\BadgeService;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Fires on every graded attempt (auto or manually completed); a no-op unless the score is exactly 100%. */
class AwardPerfectQuizBadge implements ShouldQueue
{
    public function __construct(private readonly BadgeService $badges) {}

    public function handle(QuizAttemptSubmitted $event): void
    {
        $this->badges->awardPerfectQuizBadgeIfEarned($event->attempt);
    }
}
