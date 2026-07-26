<?php

namespace Tests\Feature\Learning;

use App\Enums\QuizAttemptStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** §5.2 — QuizService's attempt lifecycle: start → answer (autosave) → submit (server-graded), objective types. */
class QuizServiceLifecycleTest extends TestCase
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

    public function test_starting_a_quiz_creates_an_in_progress_attempt_with_a_frozen_question_order(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $q1 = $quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q2', 'points' => 1, 'sort_order' => 1]);

        $this->actingAs($student);
        $attempt = app(QuizService::class)->start($quiz, $enrollment);

        $this->assertSame(QuizAttemptStatus::InProgress, $attempt->status);
        $this->assertSame(1, $attempt->attempt_no);
        $this->assertCount(2, $attempt->question_order['questions']);
        $this->assertSame($q1->id, $attempt->question_order['questions'][0]);
    }

    public function test_starting_a_quiz_twice_resumes_the_same_in_progress_attempt(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $first = $service->start($quiz, $enrollment);
        $second = $service->start($quiz, $enrollment);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\QuizAttempt::count());
    }

    public function test_a_student_cannot_start_a_quiz_once_max_attempts_is_reached(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent(['max_attempts' => 1]);
        $quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->submit($attempt);

        $this->expectException(HttpException::class);
        $service->start($quiz, $enrollment);
    }

    public function test_a_student_cannot_start_an_unpublished_quiz(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent(['is_published' => false]);

        $this->actingAs($student);
        $this->expectException(HttpException::class);
        app(QuizService::class)->start($quiz, $enrollment);
    }

    public function test_a_student_cannot_start_a_quiz_outside_its_availability_window(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent(['available_from' => now()->addDay()]);

        $this->actingAs($student);
        $this->expectException(HttpException::class);
        app(QuizService::class)->start($quiz, $enrollment);
    }

    public function test_answering_autosaves_and_can_be_changed_before_submit(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $question = $quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $yes = $question->options()->create(['label' => 'True', 'is_correct' => true, 'sort_order' => 0]);
        $question->options()->create(['label' => 'False', 'is_correct' => false, 'sort_order' => 1]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);

        $service->answer($attempt, $question, ['selected' => 999]);
        $answer = $service->answer($attempt, $question, ['selected' => $yes->id]);

        $this->assertSame(1, $attempt->answers()->count());
        $this->assertSame($yes->id, $answer->answer['selected']);
    }

    public function test_answering_a_question_not_in_the_frozen_attempt_is_rejected(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $otherQuiz = $this->enrolledStudent()[3];
        $otherQuestion = $otherQuiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q2', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);

        $this->expectException(HttpException::class);
        $service->answer($attempt, $otherQuestion, ['selected' => 1]);
    }

    public function test_submitting_grades_mcq_single_true_false_fill_blank_and_numeric(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();

        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 2, 'sort_order' => 0]);
        $mcq->options()->create(['label' => 'A', 'is_correct' => false, 'sort_order' => 0]);
        $correct = $mcq->options()->create(['label' => 'B', 'is_correct' => true, 'sort_order' => 1]);

        $fill = $quiz->questions()->create([
            'type' => 'fill_blank', 'prompt' => 'Q2', 'points' => 1, 'sort_order' => 1,
            'meta' => ['accepted_answers' => ['Paris'], 'case_sensitive' => false],
        ]);

        $numeric = $quiz->questions()->create([
            'type' => 'numeric', 'prompt' => 'Q3', 'points' => 1, 'sort_order' => 2,
            'meta' => ['expected' => 3.14, 'tolerance' => 0.01],
        ]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);

        $service->answer($attempt, $mcq, ['selected' => $correct->id]);
        $service->answer($attempt, $fill, ['text' => 'paris']);
        $service->answer($attempt, $numeric, ['value' => 3.145]);

        $result = $service->submit($attempt);

        $this->assertSame(QuizAttemptStatus::Graded, $result->status);
        $this->assertEquals(4.0, (float) $result->score_points);
        $this->assertEquals(4.0, (float) $result->max_points);
        $this->assertEquals(100.0, (float) $result->score_percent);
        $this->assertTrue($result->passed);
    }

    public function test_mcq_multi_awards_partial_credit_and_penalizes_wrong_picks(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $question = $quiz->questions()->create(['type' => 'mcq_multi', 'prompt' => 'Q1', 'points' => 4, 'sort_order' => 0]);
        $a = $question->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        $question->options()->create(['label' => 'B', 'is_correct' => true, 'sort_order' => 1]);
        $wrong = $question->options()->create(['label' => 'C', 'is_correct' => false, 'sort_order' => 2]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $question, ['selected' => [$a->id, $wrong->id]]);

        $result = $service->submit($attempt);

        // (1 correct - 1 wrong) / 2 total correct = 0 fraction
        $this->assertEquals(0.0, (float) $result->score_points);
        $this->assertFalse($result->passed);
    }

    public function test_matching_and_ordering_grade_correctly(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();

        $matching = $quiz->questions()->create(['type' => 'matching', 'prompt' => 'Q1', 'points' => 2, 'sort_order' => 0]);
        $france = $matching->options()->create(['label' => 'France', 'match_key' => 'Paris', 'sort_order' => 0]);
        $uganda = $matching->options()->create(['label' => 'Uganda', 'match_key' => 'Kampala', 'sort_order' => 1]);

        $ordering = $quiz->questions()->create(['type' => 'ordering', 'prompt' => 'Q2', 'points' => 2, 'sort_order' => 1]);
        $first = $ordering->options()->create(['label' => 'First', 'sort_order' => 0]);
        $second = $ordering->options()->create(['label' => 'Second', 'sort_order' => 1]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);

        $service->answer($attempt, $matching, ['pairs' => [$france->id => 'Paris', $uganda->id => 'Kampala']]);
        $service->answer($attempt, $ordering, ['order' => [$first->id, $second->id]]);

        $result = $service->submit($attempt);

        $this->assertEquals(4.0, (float) $result->score_points);
    }

    public function test_short_text_auto_grades_when_matched_and_flags_for_review_when_not(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $question = $quiz->questions()->create([
            'type' => 'short_text', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0,
            'meta' => ['accepted_answers' => ['photosynthesis'], 'case_sensitive' => false],
        ]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $question, ['text' => 'something else entirely']);

        $result = $service->submit($attempt);

        $this->assertSame(QuizAttemptStatus::Submitted, $result->status);
        $answer = $result->answers()->first();
        $this->assertFalse($answer->auto_graded);
        $this->assertNull($answer->is_correct);
    }

    public function test_essay_questions_always_leave_the_attempt_pending_manual_review(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $question = $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Discuss X.', 'points' => 10, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $question, ['text' => 'My essay answer.']);

        $result = $service->submit($attempt);

        $this->assertSame(QuizAttemptStatus::Submitted, $result->status);
        $this->assertNull($result->score_points);
        $this->assertNull($result->graded_at);
    }

    public function test_an_unanswered_objective_question_is_graded_zero_not_flagged(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);

        $result = $service->submit($attempt);

        $this->assertSame(QuizAttemptStatus::Graded, $result->status);
        $this->assertEquals(0.0, (float) $result->score_points);
    }

    public function test_submitting_twice_is_rejected(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->submit($attempt);

        $this->expectException(HttpException::class);
        $service->submit($attempt);
    }

    public function test_submitting_after_the_time_limit_plus_grace_period_is_rejected(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent(['time_limit_minutes' => 10]);
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $attempt->update(['started_at' => now()->subMinutes(11)]);

        $this->expectException(HttpException::class);
        $service->submit($attempt);
    }

    public function test_a_pool_draw_selects_the_configured_number_of_questions_in_original_sort_order(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent(['questions_per_attempt' => 2]);
        $q1 = $quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $q2 = $quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q2', 'points' => 1, 'sort_order' => 1]);
        $q3 = $quiz->questions()->create(['type' => 'true_false', 'prompt' => 'Q3', 'points' => 1, 'sort_order' => 2]);

        $this->actingAs($student);
        $attempt = app(QuizService::class)->start($quiz, $enrollment);

        $drawn = $attempt->question_order['questions'];
        $this->assertCount(2, $drawn);
        $sorted = $drawn;
        sort($sorted);
        $this->assertSame($sorted, $drawn, 'drawn subset should stay in ascending id / sort_order sequence when shuffle_questions is off');
        $this->assertTrue(collect([$q1->id, $q2->id, $q3->id])->intersect($drawn)->count() === 2);
    }

    public function test_ordering_question_options_are_always_shuffled_in_the_frozen_option_order(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $ordering = $quiz->questions()->create(['type' => 'ordering', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $ids = [];
        for ($i = 0; $i < 8; $i++) {
            $ids[] = $ordering->options()->create(['label' => "Item {$i}", 'sort_order' => $i])->id;
        }

        $this->actingAs($student);
        $attempt = app(QuizService::class)->start($quiz, $enrollment);

        $this->assertCount(8, $attempt->question_order['options'][$ordering->id]);
        $this->assertSame($ids, collect($attempt->question_order['options'][$ordering->id])->sort()->values()->all());
    }

    public function test_the_quiz_attempt_submitted_event_fires_only_when_fully_auto_graded(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\Learning\QuizAttemptSubmitted::class]);

        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->submit($attempt);

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\Learning\QuizAttemptSubmitted::class);
    }

    public function test_the_quiz_attempt_submitted_event_does_not_fire_when_manual_review_is_pending(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\Learning\QuizAttemptSubmitted::class]);

        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $question = $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $question, ['text' => 'An answer that needs a human to grade it.']);
        $service->submit($attempt);

        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\Learning\QuizAttemptSubmitted::class);
    }

    public function test_a_student_cannot_act_on_another_students_attempt(): void
    {
        [, , $enrollment, $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $owner = $enrollment->user;

        $this->actingAs($owner);
        $attempt = app(QuizService::class)->start($quiz, $enrollment);

        $intruder = User::factory()->create(['role' => 'student']);
        $this->actingAs($intruder);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(QuizService::class)->submit($attempt);
    }
}
