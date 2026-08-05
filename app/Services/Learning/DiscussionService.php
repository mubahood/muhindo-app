<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\DiscussionRepliedNotification;
use App\Notifications\NewDiscussionQuestionNotification;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Q&A: per-lesson (or course-wide) threads. Authorization (enrollment/admin access) is
 * the caller's responsibility, matching GradebookService/QuizAnalysisService's convention for
 * services that aren't the sole gate on money or grades.
 */
class DiscussionService
{
    public function ask(User $user, Course $course, ?Lesson $lesson, string $body): Discussion
    {
        $discussion = $course->discussions()->create([
            'lesson_id' => $lesson?->id,
            'user_id' => $user->id,
            'body' => $body,
        ]);

        $course->createdBy?->notify(new NewDiscussionQuestionNotification($discussion));

        return $discussion;
    }

    public function reply(User $user, Discussion $thread, string $body): Discussion
    {
        if (! $thread->isTopLevel()) {
            throw new HttpException(422, 'Replies can only be added to the original question, not to another reply.');
        }

        $reply = Discussion::create([
            'course_id' => $thread->course_id,
            'lesson_id' => $thread->lesson_id,
            'user_id' => $user->id,
            'parent_id' => $thread->id,
            'body' => $body,
            'is_instructor_answer' => $user->isAdmin(),
        ]);

        if ($thread->user_id !== $user->id) {
            $thread->user->notify(new DiscussionRepliedNotification($reply));
        }

        return $reply;
    }

    public function resolve(Discussion $thread): void
    {
        $thread->update(['resolved_at' => now()]);
    }
}
