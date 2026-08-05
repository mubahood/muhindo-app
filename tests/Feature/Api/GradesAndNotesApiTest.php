<?php

namespace Tests\Feature\Api;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** P5.4, API v1 parity: grades (GradebookService) and lesson notes. */
class GradesAndNotesApiTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(): array
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

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_the_grades_endpoint_matches_the_gradebook_service(): void
    {
        [$course, , $student, $enrollment] = $this->enrolledStudent();
        $assignment = $course->assignments()->create(['title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true]);
        $assignment->submissions()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => AssignmentSubmissionStatus::Returned, 'submitted_at' => now(), 'points_awarded' => 40, 'graded_at' => now(),
        ]);

        $this->withToken($this->token($student))
            ->getJson("/api/v1/courses/{$course->slug}/grades")
            ->assertOk()
            ->assertJsonPath('data.course_grade_percent', 80);
    }

    public function test_a_note_can_be_created_listed_and_deleted(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudent();
        $token = $this->token($student);

        $created = $this->withToken($token)
            ->postJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->id}/notes", ['body' => 'Remember this', 'seconds' => 90])
            ->assertCreated();
        $noteId = $created->json('data.id');

        $this->withToken($token)
            ->getJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->id}/notes")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Remember this');

        $this->withToken($token)
            ->deleteJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->id}/notes/{$noteId}")
            ->assertOk();

        $this->assertDatabaseMissing('lesson_notes', ['id' => $noteId]);
    }

    public function test_a_student_cannot_delete_another_students_note(): void
    {
        [$course, $lesson, $owner] = $this->enrolledStudent();
        $note = Enrollment::where('user_id', $owner->id)->first()->lessonNotes()->create(['lesson_id' => $lesson->id, 'body' => 'mine']);
        $outsider = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $outsider->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->withToken($this->token($outsider))
            ->deleteJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->id}/notes/{$note->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('lesson_notes', ['id' => $note->id]);
    }
}
