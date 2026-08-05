<?php

namespace Tests\Feature\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\LearningEventType;
use App\Enums\QuizAttemptStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Nightly rules-first at-risk tagging: inactive, stalled, struggling, missing_work. */
class DetectAtRiskEnrollmentsTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollment(array $overrides = []): Enrollment
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        return Enrollment::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now()->subDays(30),
        ], $overrides));
    }

    public function test_an_enrollment_never_accessed_since_enrolling_long_ago_is_flagged_inactive(): void
    {
        $enrollment = $this->activeEnrollment(['enrolled_at' => now()->subDays(30)]);

        $this->artisan('app:detect-at-risk-enrollments')->assertExitCode(0);

        $this->assertSame('inactive', $enrollment->fresh()->at_risk_reason);
    }

    public function test_a_freshly_enrolled_student_with_no_activity_yet_is_not_flagged(): void
    {
        $enrollment = $this->activeEnrollment(['enrolled_at' => now()->subDay()]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_an_enrollment_inactive_for_over_14_days_is_flagged_inactive(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDays(15)]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertSame('inactive', $enrollment->fresh()->at_risk_reason);
    }

    public function test_an_enrollment_active_with_recent_completions_is_not_flagged(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay()]);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonCompleted->value, 'created_at' => now()->subDay()]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_an_enrollment_active_but_with_no_completions_in_3_weeks_is_flagged_stalled(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay()]);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonViewed->value, 'created_at' => now()->subDays(2)]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertSame('stalled', $enrollment->fresh()->at_risk_reason);
    }

    public function test_a_previously_flagged_enrollment_is_cleared_once_it_becomes_healthy_again(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay(), 'at_risk_reason' => 'stalled']);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonCompleted->value, 'created_at' => now()->subDay()]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_a_completed_enrollment_is_never_flagged(): void
    {
        $enrollment = $this->activeEnrollment(['status' => 'completed', 'last_accessed_at' => now()->subDays(60)]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_a_below_pass_mark_quiz_average_flags_struggling(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay()]);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonCompleted->value, 'created_at' => now()->subDay()]);
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'is_published' => true]);
        $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::Graded, 'started_at' => now(), 'submitted_at' => now(), 'graded_at' => now(),
            'score_percent' => 50.0, 'score_points' => 50, 'max_points' => 100, 'passed' => false,
        ]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertSame('struggling', $enrollment->fresh()->at_risk_reason);
    }

    public function test_two_consecutive_failed_attempts_flag_struggling_even_above_average(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay()]);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonCompleted->value, 'created_at' => now()->subDay()]);
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'is_published' => true]);
        $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::Graded, 'started_at' => now(), 'submitted_at' => now(), 'graded_at' => now(),
            'score_percent' => 69.0, 'score_points' => 69, 'max_points' => 100, 'passed' => false,
        ]);
        $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 2,
            'status' => QuizAttemptStatus::Graded, 'started_at' => now(), 'submitted_at' => now(), 'graded_at' => now(),
            'score_percent' => 69.0, 'score_points' => 69, 'max_points' => 100, 'passed' => false,
        ]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertSame('struggling', $enrollment->fresh()->at_risk_reason);
    }

    public function test_an_in_progress_quiz_attempt_does_not_count_toward_struggling(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay()]);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonCompleted->value, 'created_at' => now()->subDay()]);
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'is_published' => true]);
        $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::InProgress, 'started_at' => now(),
        ]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_a_past_due_assignment_with_no_submission_flags_missing_work(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay()]);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonCompleted->value, 'created_at' => now()->subDay()]);
        $enrollment->course->assignments()->create([
            'title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text',
            'is_published' => true, 'due_at' => now()->subDay(),
        ]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertSame('missing_work', $enrollment->fresh()->at_risk_reason);
    }

    public function test_a_submitted_assignment_clears_missing_work_even_past_due(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay()]);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonCompleted->value, 'created_at' => now()->subDay()]);
        $assignment = $enrollment->course->assignments()->create([
            'title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text',
            'is_published' => true, 'due_at' => now()->subDay(),
        ]);
        $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Submitted, 'submitted_at' => now(),
        ]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_a_not_yet_due_assignment_never_flags_missing_work(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay()]);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonCompleted->value, 'created_at' => now()->subDay()]);
        $enrollment->course->assignments()->create([
            'title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text',
            'is_published' => true, 'due_at' => now()->addWeek(),
        ]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_the_dashboard_counter_reflects_the_flagged_count(): void
    {
        $this->activeEnrollment(['enrolled_at' => now()->subDays(30)]);
        $this->activeEnrollment(['enrolled_at' => now()->subDays(30)]);
        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertSame(2, app(\App\Support\Dashboard\DashboardService::class)->atRiskEnrollmentsCount());
    }
}
