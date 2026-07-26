<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** §7.3 — Classroom's stream: fired to every active/completed enrollment the moment an announcement publishes. */
class AnnouncementPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->announcement->course;

        return (new MailMessage)
            ->subject("New announcement in \"{$course->title}\": {$this->announcement->title}")
            ->greeting("Hi {$notifiable->name},")
            ->line($this->announcement->title)
            ->line(Str::limit(strip_tags($this->announcement->body), 300))
            ->action('View course', route('learn.course', $course));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $course = $this->announcement->course;

        return [
            'title' => "New announcement: {$this->announcement->title}",
            'message' => "In \"{$course->title}\"",
        ];
    }
}
