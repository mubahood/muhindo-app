<?php

namespace Tests\Feature\Learning;

use App\Enums\QuizAttemptStatus;
use App\Models\AttemptAnswer;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §5.2/§7 — the student-facing quiz runner: start → run (AJAX autosave + plain-form fallback) → submit → review. */
class QuizRunnerTest extends TestCase
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

    /**
     * Regression test: the plain 2-segment GET {course:slug}/quizzes route once collided with
     * the generic GET {course:slug}/{lesson} route registered earlier in routes/web.php — since
     * Laravel matches GET routes in registration order before model binding runs, "quizzes" was
     * being swallowed as a failed Lesson lookup (404) and this route was unreachable dead code
     * until the routes were reordered (literal-prefix routes must precede the {lesson} wildcard).
     */
    public function test_the_quiz_list_page_renders(): void
    {
        [$course, $student, , $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student)->get(route('learn.quizzes.index', $course))
            ->assertOk()->assertSee($quiz->title);
    }

    public function test_the_quiz_intro_page_renders_with_a_start_button(): void
    {
        [$course, $student, , $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student)->get(route('learn.quiz.show', [$course, $quiz]))
            ->assertOk()->assertSee('Start quiz');
    }

    public function test_starting_creates_an_attempt_and_redirects_to_the_runner(): void
    {
        [$course, $student, , $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $response = $this->actingAs($student)->post(route('learn.quiz.start', [$course, $quiz]));

        $attempt = QuizAttempt::first();
        $response->assertRedirect(route('learn.quiz.attempt', [$course, $quiz, $attempt]));
        $this->assertSame(QuizAttemptStatus::InProgress, $attempt->status);
    }

    public function test_the_runner_page_renders_every_question_type(): void
    {
        [$course, $student, , $quiz] = $this->enrolledStudent();
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Pick one', 'points' => 1, 'sort_order' => 0]);
        $mcq->options()->create(['label' => 'Alpha', 'is_correct' => true, 'sort_order' => 0]);
        $quiz->questions()->create(['type' => 'numeric', 'prompt' => 'A number', 'points' => 1, 'sort_order' => 1]);
        $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Write something', 'points' => 5, 'sort_order' => 2]);

        $this->actingAs($student);
        $attempt = app(\App\Services\Learning\QuizService::class)->start($quiz, Enrollment::first());

        $this->get(route('learn.quiz.attempt', [$course, $quiz, $attempt]))
            ->assertOk()
            ->assertSee('Pick one')
            ->assertSee('Alpha')
            ->assertSee('A number')
            ->assertSee('Write something');
    }

    public function test_ajax_answering_a_question_autosaves_it(): void
    {
        [$course, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $mcq->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);

        $this->actingAs($student);
        $attempt = app(\App\Services\Learning\QuizService::class)->start($quiz, $enrollment);

        $this->postJson(
            route('learn.quiz.answer', [$course, $quiz, $attempt, $mcq]),
            ['answer' => ['selected' => (string) $correct->id]],
        )->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, AttemptAnswer::where('quiz_attempt_id', $attempt->id)->count());
        $this->assertEquals($correct->id, AttemptAnswer::first()->answer['selected']);
    }

    public function test_immediate_feedback_mode_returns_a_grading_preview_on_answer(): void
    {
        [$course, $student, $enrollment, $quiz] = $this->enrolledStudent(['feedback_mode' => 'immediate']);
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $mcq->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);

        $this->actingAs($student);
        $attempt = app(\App\Services\Learning\QuizService::class)->start($quiz, $enrollment);

        $response = $this->postJson(
            route('learn.quiz.answer', [$course, $quiz, $attempt, $mcq]),
            ['answer' => ['selected' => (string) $correct->id]],
        );

        $response->assertJson(['preview' => ['is_correct' => true]]);
    }

    public function test_submitting_via_a_plain_form_post_with_bulk_answers_grades_the_attempt(): void
    {
        [$course, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $mcq->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        $wrong = $mcq->options()->create(['label' => 'B', 'is_correct' => false, 'sort_order' => 1]);

        $this->actingAs($student);
        $attempt = app(\App\Services\Learning\QuizService::class)->start($quiz, $enrollment);

        // No prior AJAX autosave at all — simulates JS being completely disabled.
        $response = $this->post(route('learn.quiz.submit', [$course, $quiz, $attempt]), [
            'answers' => [$mcq->id => ['selected' => $correct->id]],
        ]);

        $attempt->refresh();
        $response->assertRedirect(route('learn.quiz.review', [$course, $quiz, $attempt]));
        $this->assertSame(QuizAttemptStatus::Graded, $attempt->status);
        $this->assertEquals(100.0, (float) $attempt->score_percent);
        $this->assertNotEquals($wrong->id, AttemptAnswer::first()->answer['selected']);
    }

    public function test_submitting_an_ordering_question_from_position_inputs_grades_correctly(): void
    {
        [$course, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $ordering = $quiz->questions()->create(['type' => 'ordering', 'prompt' => 'Order them', 'points' => 2, 'sort_order' => 0]);
        $first = $ordering->options()->create(['label' => 'First', 'sort_order' => 0]);
        $second = $ordering->options()->create(['label' => 'Second', 'sort_order' => 1]);

        $this->actingAs($student);
        $attempt = app(\App\Services\Learning\QuizService::class)->start($quiz, $enrollment);

        // Position-map shape exactly as the no-JS <input type=number> fields would submit.
        $this->post(route('learn.quiz.submit', [$course, $quiz, $attempt]), [
            'answers' => [$ordering->id => ['order' => [$first->id => '1', $second->id => '2']]],
        ]);

        $attempt->refresh();
        $this->assertEquals(2.0, (float) $attempt->score_points);
    }

    public function test_the_review_page_shows_per_question_feedback_once_graded(): void
    {
        [$course, $student, $enrollment, $quiz] = $this->enrolledStudent(['feedback_mode' => 'after_submit']);
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0, 'explanation' => 'Because.']);
        $correct = $mcq->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(\App\Services\Learning\QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $mcq, ['selected' => $correct->id]);
        $service->submit($attempt);

        $this->get(route('learn.quiz.review', [$course, $quiz, $attempt]))
            ->assertOk()->assertSee('Correct')->assertSee('Because.');
    }

    public function test_the_review_page_for_a_none_feedback_quiz_shows_score_only(): void
    {
        [$course, $student, $enrollment, $quiz] = $this->enrolledStudent(['feedback_mode' => 'none']);
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(\App\Services\Learning\QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->submit($attempt);

        $this->get(route('learn.quiz.review', [$course, $quiz, $attempt]))
            ->assertOk()->assertSee('scored only')->assertDontSee('Correct');
    }

    public function test_a_student_cannot_access_another_students_attempt(): void
    {
        [$course, $owner, $enrollment, $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $this->actingAs($owner);
        $attempt = app(\App\Services\Learning\QuizService::class)->start($quiz, $enrollment);

        $intruder = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $intruder->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($intruder)->get(route('learn.quiz.attempt', [$course, $quiz, $attempt]))->assertNotFound();
        $this->assertNotSame($intruder->id, $owner->id);
    }

    public function test_a_non_enrolled_user_cannot_view_the_quiz(): void
    {
        [$course, , , $quiz] = $this->enrolledStudent();
        $stranger = User::factory()->create(['role' => 'student']);

        $this->actingAs($stranger)->get(route('learn.quiz.show', [$course, $quiz]))->assertNotFound();
    }
}
