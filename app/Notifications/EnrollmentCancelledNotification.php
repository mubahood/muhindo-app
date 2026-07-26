<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** §7.1 — fired when an admin cancels a paid enrollment and credits the invoice. */
class EnrollmentCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Enrollment $enrollment, public bool $refunded) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->enrollment->course;

        $mail = (new MailMessage)
            ->subject("Your enrollment in \"{$course->title}\" was cancelled")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your enrollment in \"{$course->title}\" has been cancelled and your access has been removed.");

        if ($this->refunded) {
            $mail->line('Your payment for this course has been refunded.');
        }

        return $mail;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $course = $this->enrollment->course;

        return [
            'title' => "Enrollment in \"{$course->title}\" cancelled",
            'message' => $this->refunded ? 'Your payment has been refunded.' : 'Your access has been removed.',
        ];
    }
}
