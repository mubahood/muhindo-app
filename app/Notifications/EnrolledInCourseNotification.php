<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Fired by NotifyStudentOfEnrollment for a genuinely new enrollment. */
class EnrolledInCourseNotification extends Notification implements ShouldQueue
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
            ->subject("You're enrolled in {$course->title}")
            ->greeting("Welcome, {$notifiable->name}!")
            ->line("You're now enrolled in \"{$course->title}\".")
            ->action('Start learning', route('learn.course', $course));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $course = $this->enrollment->course;

        return [
            'title' => "You're enrolled in {$course->title}",
            'message' => 'Jump in whenever you\'re ready.',
        ];
    }
}
