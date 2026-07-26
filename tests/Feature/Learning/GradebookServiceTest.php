<?php

namespace Tests\Feature\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\GradebookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §5.4 — GradebookService: per-quiz grading_method selection, assignment late penalty, course average. */
class GradebookServiceTest extends TestCase
{
    use RefreshDatabase;

    private function enrollment(): Enrollment
    {
        $course = Course::factory()->create(['is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
    }

    public function test_an_item_with_no_activity_at_all_is_excluded_from_the_average(): void
    {
        $enrollment = $this->enrollment();
        $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'is_published' => true]);

        $gradebook = app(GradebookService::class);
        $items = $gradebook->itemsFor($enrollment);

        $this->assertNull($items[0]['percent']);
        $this->assertNull($gradebook->courseGradePercent($enrollment));
    }

    public function test_quiz_grading_method_highest_picks_the_best_of_several_graded_attempts(): void
    {
        $enrollment = $this->enrollment();
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'grading_method' => 'highest', 'is_published' => true]);
        $this->attempt($quiz, $enrollment, 1, 60.0);
        $this->attempt($quiz, $enrollment, 2, 90.0);

        $percent = app(GradebookService::class)->itemsFor($enrollment)[0]['percent'];

        $this->assertEquals(90.0, $percent);
    }

    public function test_quiz_grading_method_average_averages_all_graded_attempts(): void
    {
        $enrollment = $this->enrollment();
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'grading_method' => 'average', 'is_published' => true]);
        $this->attempt($quiz, $enrollment, 1, 60.0);
        $this->attempt($quiz, $enrollment, 2, 80.0);

        $percent = app(GradebookService::class)->itemsFor($enrollment)[0]['percent'];

        $this->assertEquals(70.0, $percent);
    }

    public function test_quiz_grading_method_first_and_latest_pick_the_correct_attempt(): void
    {
        $enrollment = $this->enrollment();
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'grading_method' => 'first', 'is_published' => true]);
        $this->attempt($quiz, $enrollment, 1, 40.0);
        $this->attempt($quiz, $enrollment, 2, 90.0);

        $this->assertEquals(40.0, app(GradebookService::class)->itemsFor($enrollment)[0]['percent']);

        $quiz->update(['grading_method' => 'latest']);
        $this->assertEquals(90.0, app(GradebookService::class)->itemsFor($enrollment)[0]['percent']);
    }

    public function test_an_in_progress_attempt_is_ignored_by_the_gradebook(): void
    {
        $enrollment = $this->enrollment();
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'is_published' => true]);
        $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::InProgress, 'started_at' => now(),
        ]);

        $this->assertNull(app(GradebookService::class)->itemsFor($enrollment)[0]['percent']);
    }

    public function test_an_assignment_grade_is_the_latest_returned_submissions_percent(): void
    {
        $enrollment = $this->enrollment();
        $assignment = $enrollment->course->assignments()->create(['title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);
        $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Returned, 'submitted_at' => now(), 'points_awarded' => 40, 'graded_at' => now(),
        ]);

        $percent = app(GradebookService::class)->itemsFor($enrollment)[0]['percent'];

        $this->assertEquals(80.0, $percent);
    }

    public function test_a_late_penalty_reduces_the_assignment_grade_multiplicatively(): void
    {
        $enrollment = $this->enrollment();
        $assignment = $enrollment->course->assignments()->create([
            'title' => 'A1', 'points' => 100, 'max_file_mb' => 20, 'allowed_types' => 'text',
            'is_published' => true, 'late_penalty_percent' => 20,
        ]);
        $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Returned, 'submitted_at' => now(),
            'points_awarded' => 100, 'is_late' => true, 'graded_at' => now(),
        ]);

        $percent = app(GradebookService::class)->itemsFor($enrollment)[0]['percent'];

        // 100% raw, minus 20% penalty = 80%.
        $this->assertEquals(80.0, $percent);
    }

    public function test_a_submitted_but_not_yet_returned_assignment_is_excluded_not_zero(): void
    {
        $enrollment = $this->enrollment();
        $assignment = $enrollment->course->assignments()->create(['title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);
        $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Submitted, 'submitted_at' => now(),
        ]);

        $this->assertNull(app(GradebookService::class)->itemsFor($enrollment)[0]['percent']);
    }

    public function test_the_course_grade_is_the_equal_weighted_average_of_graded_items_only(): void
    {
        $enrollment = $this->enrollment();
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'is_published' => true]);
        $this->attempt($quiz, $enrollment, 1, 100.0);

        $ungradedQuiz = $enrollment->course->quizzes()->create(['title' => 'Q2', 'pass_percent' => 70, 'is_published' => true]);

        $assignment = $enrollment->course->assignments()->create(['title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);
        $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Returned, 'submitted_at' => now(), 'points_awarded' => 50, 'graded_at' => now(),
        ]);

        // Q1 = 100%, Q2 = ungraded (excluded), A1 = 100% -> average of the two graded items = 100%.
        $this->assertEquals(100.0, app(GradebookService::class)->courseGradePercent($enrollment));
    }

    public function test_certificate_quiz_requirement_is_met_with_no_gating_quizzes_at_all(): void
    {
        $enrollment = $this->enrollment();
        $enrollment->course->quizzes()->create(['title' => 'Practice', 'pass_percent' => 70, 'is_published' => true, 'counts_toward_certificate' => false]);

        $this->assertTrue(app(GradebookService::class)->meetsCertificateQuizRequirement($enrollment));
    }

    public function test_certificate_quiz_requirement_blocks_on_an_unattempted_gating_quiz(): void
    {
        $enrollment = $this->enrollment();
        $enrollment->course->quizzes()->create(['title' => 'Final', 'pass_percent' => 70, 'is_published' => true, 'counts_toward_certificate' => true]);

        $this->assertFalse(app(GradebookService::class)->meetsCertificateQuizRequirement($enrollment));
    }

    public function test_certificate_quiz_requirement_averages_across_multiple_gating_quizzes(): void
    {
        $enrollment = $this->enrollment();
        $quizA = $enrollment->course->quizzes()->create(['title' => 'A', 'pass_percent' => 60, 'is_published' => true, 'counts_toward_certificate' => true]);
        $quizB = $enrollment->course->quizzes()->create(['title' => 'B', 'pass_percent' => 80, 'is_published' => true, 'counts_toward_certificate' => true]);
        $this->attempt($quizA, $enrollment, 1, 90.0); // well above its own 60% mark
        $this->attempt($quizB, $enrollment, 1, 60.0); // below its own 80% mark

        // Average grade (75%) >= average pass mark (70%) -> requirement met even though quiz B alone failed its own mark.
        $this->assertTrue(app(GradebookService::class)->meetsCertificateQuizRequirement($enrollment));
    }

    private function attempt($quiz, Enrollment $enrollment, int $attemptNo, float $percent): void
    {
        $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => $attemptNo,
            'status' => QuizAttemptStatus::Graded, 'started_at' => now(), 'submitted_at' => now(), 'graded_at' => now(),
            'score_percent' => $percent, 'score_points' => $percent, 'max_points' => 100, 'passed' => $percent >= 70,
        ]);
    }
}
