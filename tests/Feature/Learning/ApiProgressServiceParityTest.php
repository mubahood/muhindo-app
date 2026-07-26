<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * L14 — the API must never drift from the web player's completion rules.
 * Both now go through the same ProgressService.
 */
class ApiProgressServiceParityTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithOneLesson(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);

        return [$course, $lesson];
    }

    private function enrollmentWithStatus(User $student, Course $course, string $status): Enrollment
    {
        return Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => $status,
            'source' => 'self',
            'enrolled_at' => now(),
        ]);
    }

    public function test_a_pending_enrollment_cannot_complete_a_lesson_through_the_api(): void
    {
        [$course, $lesson] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->enrollmentWithStatus($student, $course, 'pending');
        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/lessons/{$lesson->id}/complete")->assertForbidden();

        $this->assertDatabaseMissing('lesson_progress', ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
    }

    public function test_a_cancelled_enrollment_cannot_complete_a_lesson_through_the_api(): void
    {
        [$course, $lesson] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollmentWithStatus($student, $course, 'cancelled');
        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/lessons/{$lesson->id}/complete")->assertForbidden();
    }

    public function test_completing_the_only_lesson_through_the_api_also_issues_a_certificate(): void
    {
        Storage::fake('local');
        [$course, $lesson] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->enrollmentWithStatus($student, $course, 'active');
        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/lessons/{$lesson->id}/complete")->assertOk();

        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertDatabaseHas('certificates', ['enrollment_id' => $enrollment->id]);
    }

    public function test_an_active_enrollment_can_complete_a_lesson_through_the_api(): void
    {
        [$course, $lesson] = $this->courseWithOneLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->enrollmentWithStatus($student, $course, 'active');
        $token = $student->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/lessons/{$lesson->id}/complete")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('lesson_progress', ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
    }
}
