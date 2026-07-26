<?php

namespace Tests\Feature\Learning;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §4.6 — the certificate gate: all lessons complete AND, if any quiz counts_toward_certificate, its average passes. */
class CertificateQuizGateTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudentWithOneLesson(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        return [$course, $lesson, $student, $enrollment];
    }

    public function test_finishing_all_lessons_without_passing_a_gating_quiz_does_not_issue_a_certificate(): void
    {
        Storage::fake('local');
        [$course, $lesson, $student, $enrollment] = $this->enrolledStudentWithOneLesson();
        $course->quizzes()->create([
            'title' => 'Final', 'pass_percent' => 70, 'is_published' => true, 'counts_toward_certificate' => true,
        ]);

        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]));

        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertSame(0, Certificate::where('enrollment_id', $enrollment->id)->count());
    }

    public function test_passing_the_gating_quiz_after_lessons_are_already_done_issues_the_certificate(): void
    {
        Storage::fake('local');
        [$course, $lesson, $student, $enrollment] = $this->enrolledStudentWithOneLesson();
        $quiz = $course->quizzes()->create([
            'title' => 'Final', 'pass_percent' => 70, 'is_published' => true, 'counts_toward_certificate' => true,
        ]);
        $question = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $question->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);

        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]));
        $this->assertSame(0, Certificate::where('enrollment_id', $enrollment->id)->count());

        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment->fresh());
        $service->answer($attempt, $question, ['selected' => $correct->id]);
        $service->submit($attempt);

        $this->assertSame(1, Certificate::where('enrollment_id', $enrollment->id)->count());
    }

    public function test_completing_lessons_when_the_quiz_gate_is_already_passed_issues_immediately(): void
    {
        Storage::fake('local');
        [$course, $lesson, $student, $enrollment] = $this->enrolledStudentWithOneLesson();
        $quiz = $course->quizzes()->create([
            'title' => 'Final', 'pass_percent' => 70, 'is_published' => true, 'counts_toward_certificate' => true,
        ]);
        $question = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $question->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $question, ['selected' => $correct->id]);
        $service->submit($attempt);

        $this->post(route('learn.lesson.complete', [$course, $lesson]));

        $this->assertSame(1, Certificate::where('enrollment_id', $enrollment->id)->count());
    }

    public function test_a_failed_gating_quiz_still_blocks_the_certificate(): void
    {
        Storage::fake('local');
        [$course, $lesson, $student, $enrollment] = $this->enrolledStudentWithOneLesson();
        $quiz = $course->quizzes()->create([
            'title' => 'Final', 'pass_percent' => 70, 'is_published' => true, 'counts_toward_certificate' => true,
        ]);
        $question = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $question->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        $wrong = $question->options()->create(['label' => 'B', 'is_correct' => false, 'sort_order' => 1]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $question, ['selected' => $wrong->id]);
        $service->submit($attempt);
        $this->post(route('learn.lesson.complete', [$course, $lesson]));

        $this->assertSame(0, Certificate::where('enrollment_id', $enrollment->id)->count());
    }

    public function test_a_quiz_not_marked_counts_toward_certificate_never_blocks_issuance(): void
    {
        Storage::fake('local');
        [$course, $lesson, $student, $enrollment] = $this->enrolledStudentWithOneLesson();
        $course->quizzes()->create([
            'title' => 'Practice', 'pass_percent' => 70, 'is_published' => true, 'counts_toward_certificate' => false,
        ]);

        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]));

        $this->assertSame(1, Certificate::where('enrollment_id', $enrollment->id)->count());
    }

    public function test_grading_an_unrelated_quiz_does_not_prematurely_certify_before_lessons_are_done(): void
    {
        Storage::fake('local');
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $quiz = $course->quizzes()->create([
            'title' => 'Final', 'pass_percent' => 70, 'is_published' => true, 'counts_toward_certificate' => true,
        ]);
        $question = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q1', 'points' => 1, 'sort_order' => 0]);
        $correct = $question->options()->create(['label' => 'A', 'is_correct' => true, 'sort_order' => 0]);

        $this->actingAs($student);
        $service = app(QuizService::class);
        $attempt = $service->start($quiz, $enrollment);
        $service->answer($attempt, $question, ['selected' => $correct->id]);
        $service->submit($attempt);

        // Only one of two lessons is done — the enrollment is nowhere near 100%.
        $this->assertSame(0, Certificate::where('enrollment_id', $enrollment->id)->count());
    }
}
