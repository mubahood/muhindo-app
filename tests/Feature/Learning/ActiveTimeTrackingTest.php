<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Focused-time tracking — total engaged seconds per lesson (reading or watching),
 * fed by the visibility-gated frontend timer, clamped server-side, resumable.
 */
class ActiveTimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollment(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0, 'is_published' => true]);
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

    public function test_a_time_beat_accumulates_active_seconds(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();

        $response = $this->actingAs($student)->postJson(route('learn.lesson.time', [$course, $lesson]), ['active_delta' => 15]);

        $response->assertOk()->assertJson(['success' => true, 'active_seconds' => 15]);
        $this->assertSame(15, $enrollment->progressRecords()->where('lesson_id', $lesson->id)->value('active_seconds'));
    }

    public function test_repeated_beats_accumulate_across_requests(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();

        $this->actingAs($student)->postJson(route('learn.lesson.time', [$course, $lesson]), ['active_delta' => 15]);
        $response = $this->actingAs($student)->postJson(route('learn.lesson.time', [$course, $lesson]), ['active_delta' => 12]);

        $response->assertOk()->assertJson(['active_seconds' => 27]);
    }

    public function test_a_delta_beyond_the_per_beat_clamp_is_capped_at_30(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();

        // 60 passes validation (the beacon path can batch two beats) but the service clamps to 30.
        $this->actingAs($student)->postJson(route('learn.lesson.time', [$course, $lesson]), ['active_delta' => 60])
            ->assertOk()->assertJson(['active_seconds' => 30]);

        $this->actingAs($student)->postJson(route('learn.lesson.time', [$course, $lesson]), ['active_delta' => 500])
            ->assertStatus(422);
    }

    public function test_a_sendbeacon_style_form_post_is_accepted(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();

        // sendBeacon posts multipart form data with _token, no JSON headers.
        $response = $this->actingAs($student)->post(route('learn.lesson.time', [$course, $lesson]), ['active_delta' => 10]);

        $response->assertOk();
        $this->assertSame(10, $enrollment->progressRecords()->where('lesson_id', $lesson->id)->value('active_seconds'));
    }

    public function test_active_time_never_touches_watch_seconds(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();

        $this->actingAs($student)->postJson(route('learn.lesson.time', [$course, $lesson]), ['active_delta' => 20]);

        $progress = $enrollment->progressRecords()->where('lesson_id', $lesson->id)->first();
        $this->assertSame(20, $progress->active_seconds);
        $this->assertSame(0, $progress->watch_seconds);
    }

    public function test_a_pending_enrollment_cannot_record_time(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0, 'is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'pending', 'source' => 'self',
        ]);

        $this->actingAs($student)->postJson(route('learn.lesson.time', [$course, $lesson]), ['active_delta' => 15])
            ->assertForbidden();
    }

    public function test_a_guest_cannot_record_time(): void
    {
        [$course, $lesson] = $this->activeEnrollment();

        $this->postJson(route('learn.lesson.time', [$course, $lesson]), ['active_delta' => 15])->assertUnauthorized();
    }

    public function test_a_lesson_from_a_different_course_404s(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollment();
        $otherCourse = Course::factory()->create(['is_published' => true]);
        $otherModule = CourseModule::create(['course_id' => $otherCourse->id, 'title' => 'M2', 'sort_order' => 0]);
        $otherLesson = Lesson::create(['course_module_id' => $otherModule->id, 'title' => 'L2', 'sort_order' => 0, 'is_published' => true]);

        $this->actingAs($student)->postJson(route('learn.lesson.time', [$course, $otherLesson]), ['active_delta' => 15])
            ->assertNotFound();
    }

    public function test_the_lesson_page_seeds_the_timer_with_accumulated_time(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();
        $enrollment->progressRecords()->create(['lesson_id' => $lesson->id, 'started_at' => now(), 'active_seconds' => 754]);

        $response = $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]));

        $response->assertOk();
        $response->assertSee('12:34'); // 754s server-rendered in the header — resume works without JS
        $response->assertSee('activeSeconds: 754', false);
    }

    public function test_a_fresh_lesson_renders_a_zero_timer(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollment();

        $response = $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]));

        $response->assertOk()->assertSee('0:00');
    }
}
