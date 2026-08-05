<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Fired by IssueCertificateOnCourseCompletion when a student finishes a course. */
class CourseCompletedNotification extends Notification implements ShouldQueue
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
        $mail = (new MailMessage)
            ->subject("You completed {$course->title}!")
            ->greeting("Congratulations, {$notifiable->name}!")
            ->line("You've finished \"{$course->title}\" nice work.");

        if ($this->enrollment->certificate) {
            $mail->action('View your certificate', route('learn.certificate.download', $this->enrollment->certificate));
        }

        return $mail;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $course = $this->enrollment->course;

        return [
            'title' => "You completed {$course->title}!",
            'message' => 'Your certificate is ready.',
        ];
    }
}
