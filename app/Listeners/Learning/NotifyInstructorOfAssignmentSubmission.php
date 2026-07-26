<?php

namespace App\Listeners\Learning;

use App\Events\Learning\AssignmentSubmitted;
use App\Notifications\AssignmentSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyInstructorOfAssignmentSubmission implements ShouldQueue
{
    public function handle(AssignmentSubmitted $event): void
    {
        $instructor = $event->submission->assignment->course->createdBy;

        $instructor?->notify(new AssignmentSubmittedNotification($event->submission));
    }
}
