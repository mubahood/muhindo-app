<?php

namespace App\Listeners\Learning;

use App\Events\Learning\EnrollmentCreated;
use App\Notifications\EnrolledInCourseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyStudentOfEnrollment implements ShouldQueue
{
    public function handle(EnrollmentCreated $event): void
    {
        $event->enrollment->user->notify(new EnrolledInCourseNotification($event->enrollment));
    }
}
