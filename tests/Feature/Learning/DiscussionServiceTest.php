<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\DiscussionRepliedNotification;
use App\Notifications\NewDiscussionQuestionNotification;
use App\Services\Learning\DiscussionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** DiscussionService: ask/reply/resolve, the Instructor badge, and who gets notified. */
class DiscussionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_asking_a_question_notifies_the_course_instructor(): void
    {
        Notification::fake();
        $instructor = User::factory()->create(['role' => 'super_admin']);
        $course = Course::factory()->create(['created_by' => $instructor->id]);
        $student = User::factory()->create(['role' => 'student']);

        $discussion = app(DiscussionService::class)->ask($student, $course, null, 'How does this work?');

        $this->assertSame($student->id, $discussion->user_id);
        $this->assertTrue($discussion->isTopLevel());
        Notification::assertSentTo($instructor, NewDiscussionQuestionNotification::class);
    }

    public function test_a_question_can_be_scoped_to_a_lesson(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $student = User::factory()->create(['role' => 'student']);

        $discussion = app(DiscussionService::class)->ask($student, $course, $lesson, 'Question about this lesson');

        $this->assertSame($lesson->id, $discussion->lesson_id);
    }

    public function test_an_admin_replying_is_flagged_as_the_instructor_answer_and_notifies_the_asker(): void
    {
        Notification::fake();
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $thread = app(DiscussionService::class)->ask($student, $course, null, 'Help?');

        $reply = app(DiscussionService::class)->reply($admin, $thread, 'Here is the answer.');

        $this->assertTrue($reply->is_instructor_answer);
        Notification::assertSentTo($student, DiscussionRepliedNotification::class);
    }

    public function test_a_student_replying_is_not_flagged_as_an_instructor_answer(): void
    {
        $course = Course::factory()->create();
        $asker = User::factory()->create(['role' => 'student']);
        $replier = User::factory()->create(['role' => 'student']);
        $thread = app(DiscussionService::class)->ask($asker, $course, null, 'Help?');

        $reply = app(DiscussionService::class)->reply($replier, $thread, 'I think it means X.');

        $this->assertFalse($reply->is_instructor_answer);
    }

    public function test_replying_to_your_own_question_does_not_notify_yourself(): void
    {
        Notification::fake();
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $thread = app(DiscussionService::class)->ask($student, $course, null, 'Help?');

        app(DiscussionService::class)->reply($student, $thread, 'Never mind, figured it out.');

        Notification::assertNothingSent();
    }

    public function test_replying_to_a_reply_is_rejected_threads_stay_flat(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $thread = app(DiscussionService::class)->ask($student, $course, null, 'Help?');
        $reply = app(DiscussionService::class)->reply($student, $thread, 'Bump.');

        $this->expectException(HttpException::class);
        app(DiscussionService::class)->reply($student, $reply, 'Replying to a reply');
    }

    public function test_resolving_sets_resolved_at(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $thread = app(DiscussionService::class)->ask($student, $course, null, 'Help?');

        app(DiscussionService::class)->resolve($thread);

        $this->assertTrue($thread->fresh()->isResolved());
    }
}
