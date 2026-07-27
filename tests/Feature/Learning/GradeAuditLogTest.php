<?php

namespace Tests\Feature\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/** §9 — "grades are auditable": grade changes and enrollment mutations are logged via spatie/laravel-activitylog. */
class GradeAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function enrollment(): Enrollment
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
    }

    public function test_a_status_change_on_an_enrollment_is_logged(): void
    {
        $enrollment = $this->enrollment();
        $enrollment->update(['status' => 'cancelled']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'enrollment',
            'subject_type' => Enrollment::class,
            'subject_id' => $enrollment->id,
        ]);

        $activity = Activity::where('subject_id', $enrollment->id)->where('log_name', 'enrollment')->where('description', 'updated')->first();
        $this->assertSame('active', $activity->changes['old']['status']);
        $this->assertSame('cancelled', $activity->changes['attributes']['status']);
    }

    public function test_routine_progress_ticks_produce_no_audit_noise_beyond_the_creation_entry(): void
    {
        $enrollment = $this->enrollment();

        // progress_percent/total_watch_seconds tick constantly and aren't in logOnly(), so a
        // routine progress update must never add a second entry beyond the one creation log.
        $enrollment->update(['progress_percent' => 40, 'total_watch_seconds' => 300]);

        $this->assertSame(1, Activity::where('subject_type', Enrollment::class)->where('subject_id', $enrollment->id)->count());
    }

    public function test_grading_a_quiz_attempt_is_logged(): void
    {
        $enrollment = $this->enrollment();
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'is_published' => true]);
        $attempt = $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::InProgress, 'started_at' => now(),
        ]);

        $attempt->update(['status' => QuizAttemptStatus::Graded, 'score_percent' => 85.0, 'passed' => true]);

        $activity = Activity::where('subject_id', $attempt->id)->where('log_name', 'grading')->where('description', 'updated')->first();
        $this->assertNotNull($activity);
        $this->assertSame('85.00', (string) $activity->changes['attributes']['score_percent']);
        $this->assertSame('in_progress', $activity->changes['old']['status']);
    }

    public function test_grading_an_assignment_submission_is_logged(): void
    {
        $enrollment = $this->enrollment();
        $assignment = $enrollment->course->assignments()->create(['title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);
        $grader = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $submission = $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Submitted, 'submitted_at' => now(),
        ]);

        $submission->update(['status' => AssignmentSubmissionStatus::Returned, 'points_awarded' => 45, 'graded_by' => $grader->id]);

        $activity = Activity::where('subject_id', $submission->id)->where('log_name', 'grading')->where('description', 'updated')->first();
        $this->assertNotNull($activity);
        $this->assertSame('returned', $activity->changes['attributes']['status']);
        $this->assertSame('submitted', $activity->changes['old']['status']);
    }
}
