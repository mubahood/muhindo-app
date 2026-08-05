<?php

namespace Tests\Feature\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The student-facing assignment list/show/draft/submit/download HTTP flow. */
class AssignmentSubmissionFlowTest extends TestCase
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

    public function test_the_assignment_list_page_renders(): void
    {
        [$course, $student] = $this->enrolledStudent();

        $this->actingAs($student)->get(route('learn.assignments.index', $course))
            ->assertOk()->assertSee('Assignment');
    }

    public function test_the_assignment_show_page_renders_a_submission_form(): void
    {
        [$course, $student, , $assignment] = $this->enrolledStudent();

        $this->actingAs($student)->get(route('learn.assignment.show', [$course, $assignment]))
            ->assertOk()->assertSee('Save draft')->assertSee('Submit');
    }

    public function test_saving_a_draft_via_http_persists_it(): void
    {
        [$course, $student, , $assignment] = $this->enrolledStudent();

        $this->actingAs($student)->post(route('learn.assignment.draft', [$course, $assignment]), [
            'body' => 'draft text',
        ])->assertRedirect(route('learn.assignment.show', [$course, $assignment]));

        $this->assertSame(AssignmentSubmissionStatus::Draft, AssignmentSubmission::first()->status);
    }

    public function test_submitting_via_http_with_a_file_upload_persists_and_can_be_downloaded_back(): void
    {
        Storage::fake('local');
        [$course, $student, , $assignment] = $this->enrolledStudent();

        $this->actingAs($student)->post(route('learn.assignment.submit', [$course, $assignment]), [
            'body' => 'final answer',
            'file' => UploadedFile::fake()->create('homework.pdf', 500, 'application/pdf'),
        ])->assertRedirect(route('learn.assignment.show', [$course, $assignment]));

        $submission = AssignmentSubmission::first();
        $this->assertSame(AssignmentSubmissionStatus::Submitted, $submission->status);

        $this->get(route('learn.assignment.download', [$course, $assignment, $submission]))->assertOk();
    }

    public function test_the_show_page_hides_the_form_once_returned(): void
    {
        [$course, $student, $enrollment, $assignment] = $this->enrolledStudent(['resubmit_until_graded' => false]);
        AssignmentSubmission::create([
            'uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id,
            'attempt_no' => 1, 'body' => 'x', 'status' => AssignmentSubmissionStatus::Returned,
            'submitted_at' => now(), 'points_awarded' => 88, 'feedback' => 'Great work.', 'graded_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('learn.assignment.show', [$course, $assignment]));

        $response->assertOk()->assertSee('88')->assertSee('Great work.')->assertDontSee('Save draft');
    }

    public function test_a_student_cannot_download_another_students_submission_file(): void
    {
        Storage::fake('local');
        [$course, , , $assignment] = $this->enrolledStudent();
        $owner = User::factory()->create(['role' => 'student']);
        $ownerEnrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $owner->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $submission = AssignmentSubmission::create([
            'uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'enrollment_id' => $ownerEnrollment->id,
            'attempt_no' => 1, 'status' => AssignmentSubmissionStatus::Submitted, 'submitted_at' => now(),
            'file_path' => 'assignments/x/y.pdf', 'file_name' => 'y.pdf',
        ]);

        $intruder = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $intruder->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($intruder)->get(route('learn.assignment.download', [$course, $assignment, $submission]))
            ->assertNotFound();
    }
}
