<?php

namespace Tests\Feature\Api;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** P5.4, API v1 parity: the assignment turn-in flow, routed through the same AssignmentService the web controller uses. */
class AssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        return [$course, $student];
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_the_assignment_index_lists_published_assignments(): void
    {
        [$course, $student] = $this->enrolledStudent();
        $assignment = $course->assignments()->create(['title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);

        $this->withToken($this->token($student))
            ->getJson("/api/v1/courses/{$course->slug}/assignments")
            ->assertOk()
            ->assertJsonPath('data.0.assignment.id', $assignment->id);
    }

    public function test_saving_a_draft_creates_a_draft_submission(): void
    {
        [$course, $student] = $this->enrolledStudent();
        $assignment = $course->assignments()->create(['title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);

        $this->withToken($this->token($student))
            ->postJson("/api/v1/courses/{$course->slug}/assignments/{$assignment->id}/draft", ['body' => 'work in progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_submitting_creates_a_submitted_submission(): void
    {
        [$course, $student] = $this->enrolledStudent();
        $assignment = $course->assignments()->create(['title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);

        $response = $this->withToken($this->token($student))
            ->postJson("/api/v1/courses/{$course->slug}/assignments/{$assignment->id}/submit", ['body' => 'final answer'])
            ->assertOk();

        $this->assertSame(AssignmentSubmissionStatus::Submitted->value, $response->json('data.status'));
        $this->assertDatabaseHas('assignment_submissions', ['assignment_id' => $assignment->id, 'status' => 'submitted']);
    }

    public function test_a_pending_enrollment_cannot_submit(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $assignment = $course->assignments()->create(['title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'pending', 'source' => 'self',
        ]);

        $this->withToken($this->token($student))
            ->postJson("/api/v1/courses/{$course->slug}/assignments/{$assignment->id}/submit", ['body' => 'x'])
            ->assertForbidden();
    }
}
