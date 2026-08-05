<?php

namespace Tests\Feature\Api;

use App\Enums\QuizAttemptStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** P5.4, API v1 parity: the quiz runner (start -> answer -> submit -> review), routed through the same QuizService the web runner uses. */
class QuizApiTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(array $quizAttrs = []): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $quiz = $course->quizzes()->create(array_merge([
            'title' => 'Quiz', 'pass_percent' => 70, 'grading_method' => 'highest',
            'feedback_mode' => 'after_submit', 'is_published' => true,
        ], $quizAttrs));

        return [$course, $student, $enrollment, $quiz];
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_the_quiz_index_lists_published_quizzes_with_the_latest_attempt(): void
    {
        [$course, $student, , $quiz] = $this->enrolledStudent();

        $this->withToken($this->token($student))
            ->getJson("/api/v1/courses/{$course->slug}/quizzes")
            ->assertOk()
            ->assertJsonPath('data.0.quiz.id', $quiz->id)
            ->assertJsonPath('data.0.latest_attempt', null);
    }

    public function test_starting_a_quiz_creates_an_in_progress_attempt(): void
    {
        [$course, $student, , $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $response = $this->withToken($this->token($student))
            ->postJson("/api/v1/courses/{$course->slug}/quizzes/{$quiz->id}/start")
            ->assertCreated();

        $attempt = QuizAttempt::first();
        $this->assertSame(QuizAttemptStatus::InProgress, $attempt->status);
        $this->assertSame($attempt->uuid, $response->json('data.uuid'));
    }

    public function test_the_run_endpoint_never_leaks_which_option_is_correct(): void
    {
        [$course, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Pick one', 'points' => 1, 'sort_order' => 0]);
        $mcq->options()->create(['label' => 'Alpha', 'is_correct' => true, 'sort_order' => 0]);
        $mcq->options()->create(['label' => 'Beta', 'is_correct' => false, 'sort_order' => 1]);
        $this->actingAs($student);
        $attempt = app(\App\Services\Learning\QuizService::class)->start($quiz, $enrollment);
        auth()->logout(); // otherwise Sanctum treats this test request as stateful and the session user silently wins over the token below

        $response = $this->withToken($this->token($student))
            ->getJson("/api/v1/courses/{$course->slug}/quizzes/{$quiz->id}/attempts/{$attempt->uuid}")
            ->assertOk();

        $response->assertJsonPath('data.questions.0.prompt', 'Pick one');
        $this->assertArrayNotHasKey('is_correct', $response->json('data.questions.0.options.0'));
        $this->assertStringNotContainsString('is_correct', $response->getContent());
    }

    public function test_answering_autosaves_and_immediate_feedback_returns_a_preview(): void
    {
        [$course, $student, $enrollment, $quiz] = $this->enrolledStudent(['feedback_mode' => 'immediate']);
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $mcq->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        $this->actingAs($student);
        $attempt = app(\App\Services\Learning\QuizService::class)->start($quiz, $enrollment);
        auth()->logout();

        $response = $this->withToken($this->token($student))
            ->postJson(
                "/api/v1/courses/{$course->slug}/quizzes/{$quiz->id}/attempts/{$attempt->uuid}/questions/{$mcq->id}/answer",
                ['answer' => ['selected' => (string) $correct->id]],
            )->assertOk();

        $response->assertJsonPath('data.preview.is_correct', true);
    }

    public function test_submitting_grades_the_attempt_and_review_shows_feedback(): void
    {
        [$course, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $mcq = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $mcq->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        $this->actingAs($student);
        $attempt = app(\App\Services\Learning\QuizService::class)->start($quiz, $enrollment);
        app(\App\Services\Learning\QuizService::class)->answer($attempt, $mcq, ['selected' => (string) $correct->id]);
        auth()->logout();
        $token = $this->token($student);

        $this->withToken($token)
            ->postJson("/api/v1/courses/{$course->slug}/quizzes/{$quiz->id}/attempts/{$attempt->uuid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'graded')
            ->assertJsonPath('data.score_percent', '100.00');

        $this->withToken($token)
            ->getJson("/api/v1/courses/{$course->slug}/quizzes/{$quiz->id}/attempts/{$attempt->uuid}/review")
            ->assertOk()
            ->assertJsonPath('data.feedback.0.is_correct', true);
    }

    public function test_a_student_cannot_act_on_another_students_attempt(): void
    {
        [$course, $owner, $enrollment, $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $this->actingAs($owner);
        $attempt = app(\App\Services\Learning\QuizService::class)->start($quiz, $enrollment);
        // Sanctum treats this test request as "stateful", so a session-authenticated user (from
        // actingAs() above) would otherwise silently win over the outsider's bearer token below.
        auth()->logout();
        $outsider = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $outsider->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->withToken($this->token($outsider))
            ->getJson("/api/v1/courses/{$course->slug}/quizzes/{$quiz->id}/attempts/{$attempt->uuid}")
            ->assertNotFound();
    }
}
