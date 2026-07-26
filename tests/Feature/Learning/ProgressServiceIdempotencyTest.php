<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P0.8 — a stale browser tab re-posting a lesson it already completed (or the
 * final, certificate-earning lesson) must be a safe no-op, not a duplicate row
 * or a second certificate.
 */
class ProgressServiceIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollmentWithOneLesson(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        return [$course, $lesson, $student, $enrollment];
    }

    public function test_a_stale_tab_re_completing_an_already_completed_lesson_does_not_duplicate_the_progress_row(): void
    {
        Storage::fake('local');
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollmentWithOneLesson();

        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]));
        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]));

        $this->assertSame(
            1,
            LessonProgress::where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id)->count(),
        );
    }

    public function test_a_stale_tab_re_completing_the_final_lesson_does_not_issue_a_second_certificate(): void
    {
        Storage::fake('local');
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollmentWithOneLesson();

        // First tab finishes the course and earns the certificate...
        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]));
        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertSame(1, \App\Models\Certificate::where('enrollment_id', $enrollment->id)->count());

        // ...a second, stale tab replays the same POST afterwards.
        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]));

        $this->assertSame(1, \App\Models\Certificate::where('enrollment_id', $enrollment->id)->count());
    }
}
