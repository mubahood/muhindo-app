<?php

namespace App\Listeners\Learning;

use App\Events\Learning\CourseCompleted;
use App\Services\Learning\BadgeService;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Awards "First Course Completed"/"Five Courses Completed" as they're crossed. */
class AwardCourseCompletionBadges implements ShouldQueue
{
    public function __construct(private readonly BadgeService $badges) {}

    public function handle(CourseCompleted $event): void
    {
        $this->badges->awardCourseCompletionBadges($event->enrollment->user);
    }
}
