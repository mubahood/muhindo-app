<?php

namespace App\Notifications;

use App\Models\AssignmentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Fired by NotifyStudentOfSubmissionGrade once an instructor returns a submission. */
class SubmissionGradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AssignmentSubmission $submission) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $assignment = $this->submission->assignment;

        $mail = (new MailMessage)
            ->subject("Your submission for \"{$assignment->title}\" has been graded")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your submission for \"{$assignment->title}\" has been graded: {$this->submission->points_awarded} / {$assignment->points} points.")
            ->action('View feedback', route('learn.assignment.show', [$assignment->course, $assignment]));

        if ($this->submission->feedback) {
            $mail->line("Instructor feedback: {$this->submission->feedback}");
        }

        return $mail;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $assignment = $this->submission->assignment;

        return [
            'title' => "\"{$assignment->title}\" was graded",
            'message' => "{$this->submission->points_awarded} / {$assignment->points} points.",
        ];
    }
}
