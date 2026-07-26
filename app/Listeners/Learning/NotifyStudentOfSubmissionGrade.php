<?php

namespace App\Listeners\Learning;

use App\Events\Learning\SubmissionGraded;
use App\Notifications\SubmissionGradedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyStudentOfSubmissionGrade implements ShouldQueue
{
    public function handle(SubmissionGraded $event): void
    {
        $event->submission->enrollment->user->notify(new SubmissionGradedNotification($event->submission));
    }
}
