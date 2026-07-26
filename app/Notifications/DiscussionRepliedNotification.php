<?php

namespace App\Notifications;

use App\Models\Discussion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** §7.3 — fired to the original asker when someone (an "Instructor" reply included) replies to their question. */
class DiscussionRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Discussion $reply) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $thread = $this->reply->parent;
        $course = $this->reply->course;
        $from = $this->reply->is_instructor_answer ? 'Your instructor' : $this->reply->user->name;

        return (new MailMessage)
            ->subject("New reply to your question in \"{$course->title}\"")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$from} replied to your question:")
            ->line(Str::limit(strip_tags($this->reply->body), 300))
            ->action('View the discussion', route('learn.discussions.show', [$course, $thread]));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $course = $this->reply->course;

        return [
            'title' => "New reply in \"{$course->title}\"",
            'message' => Str::limit(strip_tags($this->reply->body), 100),
        ];
    }
}
