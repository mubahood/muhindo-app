<?php

namespace App\Notifications;

use App\Models\Discussion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Fired to the course instructor when a student asks a new question. */
class NewDiscussionQuestionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Discussion $discussion) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $course = $this->discussion->course;
        $student = $this->discussion->user;

        return [
            'title' => "New question in \"{$course->title}\"",
            'message' => "{$student->name}: ".Str::limit(strip_tags($this->discussion->body), 100),
        ];
    }
}
