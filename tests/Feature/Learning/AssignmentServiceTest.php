<?php

namespace Tests\Feature\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** §5.1/§5.3 — AssignmentService's draft/submit lifecycle, resubmission rules, and late handling. */
class AssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(array $assignmentAttrs = []): array
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
        $assignment = $course->assignments()->create(array_merge([
            'title' => 'Assignment', 'points' => 100, 'max_file_mb' => 20,
            'allowed_types' => 'text,link,pdf', 'is_published' => true,
        ], $assignmentAttrs));

        return [$course, $student, $enrollment, $assignment];
    }

    public function test_saving_a_draft_creates_attempt_one_in_draft_status(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent();
        $this->actingAs($student);

        $submission = app(AssignmentService::class)->saveDraft($enrollment, $assignment, ['body' => 'work in progress'], null);

        $this->assertSame(1, $submission->attempt_no);
        $this->assertSame(AssignmentSubmissionStatus::Draft, $submission->status);
        $this->assertSame('work in progress', $submission->body);
    }

    public function test_saving_a_draft_twice_updates_the_same_row_not_a_new_attempt(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent();
        $this->actingAs($student);
        $service = app(AssignmentService::class);

        $service->saveDraft($enrollment, $assignment, ['body' => 'v1'], null);
        $service->saveDraft($enrollment, $assignment, ['body' => 'v2'], null);

        $this->assertSame(1, AssignmentSubmission::count());
        $this->assertSame('v2', AssignmentSubmission::first()->body);
    }

    public function test_submitting_a_draft_finalizes_the_same_row(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent();
        $this->actingAs($student);
        $service = app(AssignmentService::class);

        $draft = $service->saveDraft($enrollment, $assignment, ['body' => 'v1'], null);
        $submitted = $service->submit($enrollment, $assignment, ['body' => 'final'], null);

        $this->assertSame($draft->id, $submitted->id);
        $this->assertSame(AssignmentSubmissionStatus::Submitted, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
        $this->assertFalse($submitted->is_late);
    }

    public function test_submitting_past_due_marks_the_submission_late_when_late_is_allowed(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent(['due_at' => now()->subDay(), 'allow_late' => true]);
        $this->actingAs($student);

        $submitted = app(AssignmentService::class)->submit($enrollment, $assignment, ['body' => 'late work'], null);

        $this->assertTrue($submitted->is_late);
        $this->assertSame(AssignmentSubmissionStatus::Submitted, $submitted->status);
    }

    public function test_submitting_past_due_is_rejected_when_late_is_not_allowed(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent(['due_at' => now()->subDay(), 'allow_late' => false]);
        $this->actingAs($student);

        $this->expectException(HttpException::class);
        app(AssignmentService::class)->submit($enrollment, $assignment, ['body' => 'too late'], null);
    }

    public function test_resubmission_creates_a_new_attempt_when_allowed(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent(['resubmit_until_graded' => true]);
        $this->actingAs($student);
        $service = app(AssignmentService::class);

        $first = $service->submit($enrollment, $assignment, ['body' => 'v1'], null);
        $second = $service->submit($enrollment, $assignment, ['body' => 'v2'], null);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $second->attempt_no);
        $this->assertSame(2, AssignmentSubmission::count());
    }

    public function test_resubmission_is_rejected_when_disallowed(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent(['resubmit_until_graded' => false]);
        $this->actingAs($student);
        $service = app(AssignmentService::class);

        $service->submit($enrollment, $assignment, ['body' => 'v1'], null);

        $this->expectException(HttpException::class);
        $service->submit($enrollment, $assignment, ['body' => 'v2'], null);
    }

    public function test_a_returned_submission_can_never_be_resubmitted_regardless_of_the_flag(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent(['resubmit_until_graded' => true]);
        $this->actingAs($student);
        $service = app(AssignmentService::class);

        $submission = $service->submit($enrollment, $assignment, ['body' => 'v1'], null);
        $submission->update(['status' => AssignmentSubmissionStatus::Returned, 'points_awarded' => 90, 'graded_at' => now()]);

        $this->expectException(HttpException::class);
        $service->submit($enrollment, $assignment, ['body' => 'v2'], null);
    }

    public function test_a_file_upload_is_stored_on_the_private_disk_and_replaced_on_resubmission(): void
    {
        Storage::fake('local');
        [, $student, $enrollment, $assignment] = $this->enrolledStudent(['resubmit_until_graded' => true]);
        $this->actingAs($student);
        $service = app(AssignmentService::class);

        $first = $service->submit($enrollment, $assignment, [], UploadedFile::fake()->create('essay.pdf', 100));
        $firstPath = $first->file_path;
        Storage::disk('local')->assertExists($firstPath);

        $second = $service->submit($enrollment, $assignment, [], UploadedFile::fake()->create('essay-v2.pdf', 100));

        Storage::disk('local')->assertExists($second->file_path);
        $this->assertNotSame($firstPath, $second->file_path);
    }

    public function test_a_disallowed_submission_type_is_silently_ignored(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent(['allowed_types' => 'text']);
        $this->actingAs($student);

        $submitted = app(AssignmentService::class)->submit($enrollment, $assignment, ['body' => 'ok', 'link_url' => 'https://example.com'], null);

        $this->assertSame('ok', $submitted->body);
        $this->assertNull($submitted->link_url);
    }

    public function test_a_student_cannot_submit_to_another_students_enrollment(): void
    {
        [, , $enrollment, $assignment] = $this->enrolledStudent();
        $intruder = User::factory()->create(['role' => 'student']);
        $this->actingAs($intruder);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(AssignmentService::class)->submit($enrollment, $assignment, ['body' => 'x'], null);
    }

    public function test_returning_a_submitted_assignment_grades_it(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent();
        $grader = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($student);
        $submission = app(AssignmentService::class)->submit($enrollment, $assignment, ['body' => 'my work'], null);

        $returned = app(AssignmentService::class)->return($submission, 42.5, 'Well argued.', $grader);

        $this->assertSame(AssignmentSubmissionStatus::Returned, $returned->status);
        $this->assertEquals(42.5, (float) $returned->points_awarded);
        $this->assertSame('Well argued.', $returned->feedback);
        $this->assertSame($grader->id, $returned->graded_by);
        $this->assertNotNull($returned->graded_at);
    }

    public function test_returning_a_draft_or_already_returned_submission_is_rejected(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent();
        $grader = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($student);
        $draft = app(AssignmentService::class)->saveDraft($enrollment, $assignment, ['body' => 'wip'], null);

        $this->expectException(HttpException::class);
        app(AssignmentService::class)->return($draft, 10, null, $grader);
    }

    public function test_returning_rejects_points_above_the_assignments_max(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent(['points' => 20]);
        $grader = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($student);
        $submission = app(AssignmentService::class)->submit($enrollment, $assignment, ['body' => 'x'], null);

        $this->expectException(HttpException::class);
        app(AssignmentService::class)->return($submission, 21, null, $grader);
    }

    public function test_submitting_notifies_the_courses_instructor(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $instructor = User::factory()->create(['role' => 'super_admin']);
        [$course, $student, $enrollment, $assignment] = $this->enrolledStudent();
        $course->update(['created_by' => $instructor->id]);
        $this->actingAs($student);

        app(AssignmentService::class)->submit($enrollment, $assignment, ['body' => 'final'], null);

        \Illuminate\Support\Facades\Notification::assertSentTo($instructor, \App\Notifications\AssignmentSubmittedNotification::class);
    }

    public function test_submitting_feeds_the_learning_events_stream_but_a_draft_save_does_not(): void
    {
        [, $student, $enrollment, $assignment] = $this->enrolledStudent();
        $this->actingAs($student);
        $service = app(AssignmentService::class);

        $service->saveDraft($enrollment, $assignment, ['body' => 'wip'], null);
        $this->assertSame(0, $enrollment->learningEvents()->where('event', \App\Enums\LearningEventType::AssignmentSubmitted)->count());

        $service->submit($enrollment, $assignment, ['body' => 'final'], null);
        $this->assertSame(1, $enrollment->learningEvents()->where('event', \App\Enums\LearningEventType::AssignmentSubmitted)->count());
    }
}
