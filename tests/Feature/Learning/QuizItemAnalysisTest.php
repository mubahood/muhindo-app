<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\QuizAnalysisService;
use App\Services\Learning\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Per-question correct-rate across every attempt that has answered it. */
class QuizItemAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $quiz = $course->quizzes()->create(['title' => 'Quiz', 'pass_percent' => 70, 'is_published' => true]);

        return [$course, $student, $enrollment, $quiz];
    }

    public function test_a_question_with_no_answers_yet_reports_a_null_correct_rate(): void
    {
        [, , , $quiz] = $this->enrolledStudent();
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);

        $rows = app(QuizAnalysisService::class)->itemAnalysisFor($quiz);

        $this->assertSame(0, $rows[0]['total_answered']);
        $this->assertNull($rows[0]['correct_rate']);
    }

    public function test_correct_rate_is_computed_across_multiple_students_attempts(): void
    {
        [$course, , , $quiz] = $this->enrolledStudent();
        $question = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $question->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        $wrong = $question->options()->create(['label' => 'B', 'is_correct' => false, 'sort_order' => 1]);

        // Three students: two answer correctly, one incorrectly.
        foreach ([true, true, false] as $answeredCorrectly) {
            $student = User::factory()->create(['role' => 'student']);
            $enrollment = Enrollment::create([
                'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
                'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
            ]);
            $this->actingAs($student);
            $service = app(QuizService::class);
            $attempt = $service->start($quiz, $enrollment);
            $service->answer($attempt, $question, ['selected' => $answeredCorrectly ? $correct->id : $wrong->id]);
            $service->submit($attempt);
        }

        $rows = app(QuizAnalysisService::class)->itemAnalysisFor($quiz);

        $this->assertSame(3, $rows[0]['total_answered']);
        $this->assertSame(2, $rows[0]['correct_count']);
        $this->assertEquals(66.7, $rows[0]['correct_rate']);
    }

    public function test_essay_questions_never_contribute_to_the_correct_rate_since_theyre_never_auto_graded(): void
    {
        [, $student, $enrollment, $quiz] = $this->enrolledStudent();
        $essay = $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Discuss X', 'points' => 10, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $essay, ['text' => 'An answer.']);
        $service->submit($attempt);

        $rows = app(QuizAnalysisService::class)->itemAnalysisFor($quiz);

        $this->assertSame(0, $rows[0]['total_answered']);
        $this->assertNull($rows[0]['correct_rate']);
    }
}
