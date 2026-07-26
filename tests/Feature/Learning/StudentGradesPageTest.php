<?php

namespace Tests\Feature\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §5.4 — the student's "Grades" tab. */
class StudentGradesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_grades_page_shows_the_course_grade_and_item_list(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $assignment = $course->assignments()->create(['title' => 'Essay 1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);
        $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Returned, 'submitted_at' => now(), 'points_awarded' => 45, 'graded_at' => now(),
        ]);

        $this->actingAs($student)->get(route('learn.grades', $course))
            ->assertOk()->assertSee('Essay 1')->assertSee('90%');
    }

    public function test_a_non_enrolled_user_cannot_view_grades(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $stranger = User::factory()->create(['role' => 'student']);

        $this->actingAs($stranger)->get(route('learn.grades', $course))->assertNotFound();
    }
}
