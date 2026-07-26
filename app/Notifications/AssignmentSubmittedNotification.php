<?php

namespace App\Notifications;

use App\Models\AssignmentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** §4.5/§6.3.3 — fired by NotifyInstructorOfAssignmentSubmission so grading isn't discovered cold from the queue. */
class AssignmentSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AssignmentSubmission $submission) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $assignment = $this->submission->assignment;
        $student = $this->submission->enrollment->user;

        return [
            'title' => "New submission for \"{$assignment->title}\"",
            'message' => "{$student->name} turned in their work — grade it from the grading queue.",
        ];
    }
}
