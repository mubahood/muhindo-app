<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §9 — a completed enrollment's progress_percent freezes at 100; it must never regress just because the curriculum grew afterward. */
class CompletedEnrollmentFreezeTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_a_lesson_after_completion_does_not_regress_progress_percent_when_a_new_lesson_is_later_added(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lessonOne = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $this->actingAs($student);

        app(ProgressService::class)->completeLesson($enrollment, $lessonOne);
        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
        $this->assertSame(100, $enrollment->progress_percent);

        // The curriculum grows after completion — a new, unfinished lesson.
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);

        // The student revisits the lesson they already completed.
        app(ProgressService::class)->recordView($enrollment, $lessonOne);

        $this->assertSame(100, $enrollment->fresh()->progress_percent);
        $this->assertSame('completed', $enrollment->fresh()->status);
    }

    public function test_an_active_not_yet_completed_enrollments_progress_percent_still_updates_normally(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lessonOne = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $this->actingAs($student);

        app(ProgressService::class)->completeLesson($enrollment, $lessonOne);

        app(ProgressService::class)->recordView($enrollment, $lessonOne);

        $this->assertSame(50, $enrollment->fresh()->progress_percent);
        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_the_student_dashboard_displays_the_frozen_stored_percent_not_a_live_recompute(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lessonOne = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $this->actingAs($student);
        app(ProgressService::class)->completeLesson($enrollment, $lessonOne);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);

        $this->get(route('dashboard'))->assertOk()->assertSee('100% complete');
    }
}
