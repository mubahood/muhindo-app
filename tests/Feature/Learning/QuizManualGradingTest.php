<?php

namespace Tests\Feature\Learning;

use App\Enums\QuizAttemptStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** §5.2/§5.2.4 — QuizService::gradeManual() finalizing an attempt, and feedback_mode-gated review data. */
class QuizManualGradingTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(array $quizAttrs = []): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);
        $quiz = $course->quizzes()->create(array_merge([
            'title' => 'Quiz', 'pass_percent' => 70, 'grading_method' => 'highest',
            'feedback_mode' => 'after_submit', 'is_published' => true,
        ], $quizAttrs));

        return [$course, $student, $enrollment, $quiz];
    }

    public function test_grading_the_only_pending_essay_finalizes_the_attempt(): void
    {
        Event::fake([\App\Events\Learning\QuizAttemptSubmitted::class]);

        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $mcq->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        $essay = $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Q2', 'points' => 4, 'sort_order' => 1]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $mcq, ['selected' => $correct->id]);
        $service->answer($attempt, $essay, ['text' => 'A thoughtful essay.']);
        $submitted = $service->submit($attempt);

        $this->assertSame(QuizAttemptStatus::Submitted, $submitted->status);
        Event::assertNotDispatched(\App\Events\Learning\QuizAttemptSubmitted::class);

        $graded = $service->gradeManual($submitted, $essay, 3.0, 'Solid argument, thin evidence.');
        $final = $submitted->fresh();

        $this->assertEquals(3.0, (float) $graded->points_awarded);
        $this->assertSame(QuizAttemptStatus::Graded, $final->status);
        $this->assertEquals(4.0, (float) $final->score_points); // 1 (mcq) + 3 (essay)
        $this->assertEquals(5.0, (float) $final->max_points);
        $this->assertEquals(80.0, (float) $final->score_percent);
        $this->assertTrue($final->passed);
        Event::assertDispatched(\App\Events\Learning\QuizAttemptSubmitted::class);
    }

    public function test_the_attempt_stays_submitted_while_a_second_essay_is_still_pending(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $essay1 = $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Q1', 'points' => 5, 'sort_order' => 0]);
        $essay2 = $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Q2', 'points' => 5, 'sort_order' => 1]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $essay1, ['text' => 'Answer one.']);
        $service->answer($attempt, $essay2, ['text' => 'Answer two.']);
        $submitted = $service->submit($attempt);

        $service->gradeManual($submitted, $essay1, 4.0);

        $this->assertSame(QuizAttemptStatus::Submitted, $submitted->fresh()->status);
        $this->assertNull($submitted->fresh()->score_points);
    }

    public function test_grade_manual_rejects_an_attempt_that_is_not_awaiting_review(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $graded = $service->submit($attempt); // fully auto-graded already

        $this->expectException(HttpException::class);
        $service->gradeManual($graded, $mcq, 1.0);
    }

    public function test_grade_manual_rejects_points_outside_the_questions_max(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $essay = $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Q1', 'points' => 5, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $essay, ['text' => 'Answer.']);
        $submitted = $service->submit($attempt);

        $this->expectException(HttpException::class);
        $service->gradeManual($submitted, $essay, 9.0);
    }

    public function test_preview_grade_does_not_persist_anything(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $mcq->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);

        $preview = $service->previewGrade($mcq, ['selected' => $correct->id]);

        $this->assertTrue($preview['is_correct']);
        $this->assertSame(0, $attempt->answers()->count());
    }

    public function test_feedback_is_hidden_while_the_attempt_is_still_in_progress(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent(['feedback_mode' => 'immediate']);
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $attempt = app(QuizService::class)->start($quiz, $enrollment);

        $this->assertNull(app(QuizService::class)->feedbackFor($attempt));
    }

    public function test_feedback_none_mode_never_reveals_anything(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent(['feedback_mode' => 'none']);
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $mcq->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $submitted = $service->submit($attempt);

        $this->assertNull($service->feedbackFor($submitted));
    }

    public function test_feedback_after_submit_mode_reveals_once_graded(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent(['feedback_mode' => 'after_submit']);
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0, 'explanation' => 'Because reasons.']);
        $correct = $mcq->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $mcq, ['selected' => $correct->id]);
        $submitted = $service->submit($attempt);

        $feedback = $service->feedbackFor($submitted);

        $this->assertNotNull($feedback);
        $this->assertTrue($feedback[0]['is_correct']);
        $this->assertSame('Because reasons.', $feedback[0]['explanation']);
    }

    public function test_feedback_after_close_mode_stays_hidden_until_available_until_passes(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent([
            'feedback_mode' => 'after_close', 'available_until' => now()->addDay(),
        ]);
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $submitted = $service->submit($attempt);

        $this->assertNull($service->feedbackFor($submitted));

        $quiz->update(['available_until' => now()->subMinute()]);

        $this->assertNotNull($service->feedbackFor($submitted->fresh()));
    }
}
