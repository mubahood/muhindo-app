<?php

namespace App\Notifications;

use App\Models\ProjectInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** §4.3 — a new "start a project" lead, sent to every admin (mail + database — this is a sales lead, not routine activity). */
class ProjectInquiryReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProjectInquiry $inquiry) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New project inquiry from {$this->inquiry->name}")
            ->greeting('New "Start a project" lead')
            ->line("{$this->inquiry->name} ({$this->inquiry->email}) wants to build: {$this->inquiry->project_type}.")
            ->line($this->inquiry->description)
            ->action('View inquiry', route('admin.project-inquiries.show', $this->inquiry));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => "New project inquiry from {$this->inquiry->name}",
            'message' => Str::limit($this->inquiry->description, 100),
        ];
    }
}
