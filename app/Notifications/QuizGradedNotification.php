<?php

namespace App\Notifications;

use App\Models\QuizAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** §4.5/§5.2 — fired by NotifyStudentOfQuizGrade once a QuizAttempt reaches `graded`. */
class QuizGradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public QuizAttempt $attempt) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quiz = $this->attempt->quiz;
        $percent = rtrim(rtrim(number_format((float) $this->attempt->score_percent, 1), '0'), '.');

        return (new MailMessage)
            ->subject("Your quiz \"{$quiz->title}\" has been graded")
            ->greeting("Hi {$notifiable->name},")
            ->line("You scored {$percent}% on \"{$quiz->title}\".")
            ->line($this->attempt->passed ? 'You passed. Nice work!' : 'You did not reach the pass mark this time.')
            ->action('View your results', route('learn.quiz.review', [$quiz->course, $quiz, $this->attempt]));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $quiz = $this->attempt->quiz;
        $percent = rtrim(rtrim(number_format((float) $this->attempt->score_percent, 1), '0'), '.');

        return [
            'title' => "\"{$quiz->title}\" was graded",
            'message' => "You scored {$percent}%".($this->attempt->passed ? ' — passed!' : '.'),
        ];
    }
}
