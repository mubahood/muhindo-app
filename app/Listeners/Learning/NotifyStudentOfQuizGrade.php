<?php

namespace App\Listeners\Learning;

use App\Events\Learning\QuizAttemptSubmitted;
use App\Notifications\QuizGradedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyStudentOfQuizGrade implements ShouldQueue
{
    public function handle(QuizAttemptSubmitted $event): void
    {
        $event->attempt->enrollment->user->notify(new QuizGradedNotification($event->attempt));
    }
}
