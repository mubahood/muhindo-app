<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The one-click "nudge" sent from the drill-down page or the at-risk digest. */
class StudentNudgeNotification extends Notification
{
    use Queueable;

    public function __construct(public Enrollment $enrollment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject("Keep going on {$course->title}")
            ->greeting("Hi {$notifiable->name},")
            ->line("You're {$this->enrollment->progress_percent}% through \"{$course->title}\" just a quick nudge to keep your momentum going.")
            ->action('Continue learning', route('learn.course', $course))
            ->line('Even 10 minutes today keeps you on track.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $course = $this->enrollment->course;

        return [
            'title' => "Keep going on {$course->title}",
            'message' => "You're {$this->enrollment->progress_percent}% through, pick up where you left off.",
        ];
    }
}
