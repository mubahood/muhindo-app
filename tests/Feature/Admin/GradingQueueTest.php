<?php

namespace Tests\Feature\Admin;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\QuizAttemptStatus;
use App\Livewire\Admin\GradingQueue;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\QuizGradedNotification;
use App\Notifications\SubmissionGradedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** The grading queue: every ungraded quiz answer + submitted assignment, oldest first. */
class GradingQueueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function enrollment(): Enrollment
    {
        $course = Course::factory()->create(['is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
    }

    public function test_a_non_admin_cannot_view_the_queue(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.grading-queue'))->assertRedirect(route('login'));
    }

    public function test_the_queue_shows_pending_quiz_answers_and_assignment_submissions(): void
    {
        $admin = $this->admin();
        $enrollment = $this->enrollment();
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Midterm', 'pass_percent' => 70, 'is_published' => true]);
        $question = $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Discuss X', 'points' => 10, 'sort_order' => 0]);
        $attempt = $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::Submitted, 'started_at' => now(), 'submitted_at' => now(), 'max_points' => 10,
        ]);
        $attempt->answers()->create(['question_id' => $question->id, 'answer' => ['text' => 'My essay'], 'auto_graded' => false]);

        $assignment = $enrollment->course->assignments()->create(['title' => 'Essay 1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);
        $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Submitted, 'submitted_at' => now(), 'body' => 'my work',
        ]);

        $this->actingAs($admin)->get(route('admin.grading-queue'))
            ->assertOk()->assertSee('Midterm')->assertSee('Essay 1')->assertSee('Discuss X');
    }

    public function test_grading_a_quiz_answer_finalizes_the_attempt_and_notifies_the_student(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $enrollment = $this->enrollment();
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Midterm', 'pass_percent' => 70, 'is_published' => true]);
        $question = $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Discuss X', 'points' => 10, 'sort_order' => 0]);
        $attempt = $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::Submitted, 'started_at' => now(), 'submitted_at' => now(), 'max_points' => 10,
        ]);
        $answer = $attempt->answers()->create(['question_id' => $question->id, 'answer' => ['text' => 'My essay'], 'auto_graded' => false]);

        Livewire::actingAs($admin)
            ->test(GradingQueue::class)
            ->call('openGrading', 'quiz_answer', $answer->id)
            ->set('points', '8')
            ->set('feedback', 'Solid.')
            ->call('submitGrade');

        $attempt->refresh();
        $this->assertSame(QuizAttemptStatus::Graded, $attempt->status);
        $this->assertEquals(80.0, (float) $attempt->score_percent);
        Notification::assertSentTo($enrollment->user, QuizGradedNotification::class);
    }

    public function test_returning_an_assignment_submission_notifies_the_student(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $enrollment = $this->enrollment();
        $assignment = $enrollment->course->assignments()->create(['title' => 'Essay 1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);
        $submission = $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Submitted, 'submitted_at' => now(), 'body' => 'my work',
        ]);

        Livewire::actingAs($admin)
            ->test(GradingQueue::class)
            ->call('openGrading', 'submission', $submission->id)
            ->set('points', '45')
            ->set('feedback', 'Great work.')
            ->call('submitGrade');

        $submission->refresh();
        $this->assertSame(AssignmentSubmissionStatus::Returned, $submission->status);
        $this->assertEquals(45.0, (float) $submission->points_awarded);
        $this->assertSame($admin->id, $submission->graded_by);
        Notification::assertSentTo($enrollment->user, SubmissionGradedNotification::class);
    }

    public function test_grading_rejects_points_above_the_max(): void
    {
        $admin = $this->admin();
        $enrollment = $this->enrollment();
        $assignment = $enrollment->course->assignments()->create(['title' => 'Essay 1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);
        $submission = $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Submitted, 'submitted_at' => now(), 'body' => 'my work',
        ]);

        Livewire::actingAs($admin)
            ->test(GradingQueue::class)
            ->call('openGrading', 'submission', $submission->id)
            ->set('points', '999')
            ->call('submitGrade')
            ->assertStatus(422);

        $this->assertSame(AssignmentSubmissionStatus::Submitted, $submission->fresh()->status);
        $this->assertNull($submission->fresh()->points_awarded);
    }

    public function test_the_empty_state_shows_when_nothing_is_pending(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.grading-queue'))
            ->assertOk()->assertSee('inbox zero');
    }
}
