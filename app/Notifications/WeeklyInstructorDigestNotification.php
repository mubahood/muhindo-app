<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The weekly "5 students at risk: ..." instructor digest. Only ever
 * dispatched when at least one enrollment is flagged ('s "optional" * no email when there's nothing to say); the command that sends this never
 * fires it on an empty list.
 */
class WeeklyInstructorDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param Collection<int, \App\Models\Enrollment> $atRiskEnrollments */
    public function __construct(public Collection $atRiskEnrollments) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->atRiskEnrollments->count();
        $shown = $this->atRiskEnrollments->take(20);

        $mail = (new MailMessage)
            ->subject($count === 1 ? '1 student at risk this week' : "{$count} students at risk this week")
            ->greeting("Hi {$notifiable->name},")
            ->line($count === 1
                ? '1 student needs attention this week:'
                : "{$count} students need attention this week:");

        foreach ($shown as $enrollment) {
            $reason = ucfirst(str_replace('_', ' ', (string) $enrollment->at_risk_reason));
            $mail->line("• {$enrollment->user->name}, \"{$enrollment->course->title}\" ({$reason})");
        }

        if ($count > $shown->count()) {
            $mail->line('...and '.($count - $shown->count()).' more.');
        }

        return $mail->action('Review enrollments', route('admin.enrollments.index'))
            ->line('Each student page has a one-click nudge you can send from their drill-down.');
    }
}
