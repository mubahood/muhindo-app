<?php

namespace Tests\Feature\Admin;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §5.4 — the admin per-course grade matrix + CSV export. */
class GradeMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function enrolledStudentWithGrade(Course $course, string $name): Enrollment
    {
        $student = User::factory()->create(['role' => 'student', 'name' => $name]);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $assignment = $course->assignments()->firstOrCreate(
            ['title' => 'Essay 1'],
            ['points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true],
        );
        $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Returned, 'submitted_at' => now(), 'points_awarded' => 40, 'graded_at' => now(),
        ]);

        return $enrollment;
    }

    public function test_a_non_admin_cannot_view_the_gradebook(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.courses.gradebook', $course))->assertRedirect(route('login'));
    }

    public function test_the_matrix_shows_one_row_per_student_with_their_grade(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create(['is_published' => true]);
        $this->enrolledStudentWithGrade($course, 'Alice Nakato');

        $this->actingAs($admin)->get(route('admin.courses.gradebook', $course))
            ->assertOk()->assertSee('Alice Nakato')->assertSee('Essay 1')->assertSee('80%');
    }

    public function test_the_csv_export_contains_a_row_per_student(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create(['is_published' => true]);
        $this->enrolledStudentWithGrade($course, 'Alice Nakato');

        $response = $this->actingAs($admin)->get(route('admin.courses.gradebook.export', $course));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Alice Nakato', $content);
        $this->assertStringContainsString('Essay 1', $content);
    }
}
