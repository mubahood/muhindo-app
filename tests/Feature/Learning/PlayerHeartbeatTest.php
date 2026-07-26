<?php

namespace Tests\Feature\Learning;

use App\Enums\CompletionRule;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §6.2/§7.3 — the player heartbeat is the only source of truth for watch time and min_watch completion. */
class PlayerHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollmentWithLesson(array $lessonOverrides = []): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(array_merge([
            'course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0,
        ], $lessonOverrides));
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

    public function test_a_heartbeat_records_watch_seconds_and_position(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollmentWithLesson();

        $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lesson]), [
            'seconds_delta' => 15, 'position_seconds' => 45,
        ])->assertOk()->assertJson(['success' => true, 'watch_seconds' => 15, 'last_position_seconds' => 45]);

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id, 'watch_seconds' => 15,
        ]);
        $this->assertSame(15, $enrollment->fresh()->total_watch_seconds);
    }

    public function test_repeated_heartbeats_accumulate_watch_seconds(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollmentWithLesson();

        $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lesson]), ['seconds_delta' => 15, 'position_seconds' => 15]);
        $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lesson]), ['seconds_delta' => 15, 'position_seconds' => 30]);

        $this->assertSame(30, $enrollment->fresh()->total_watch_seconds);
    }

    public function test_a_client_reported_delta_beyond_one_heartbeat_interval_is_clamped(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollmentWithLesson();

        // A dishonest client claims a wildly inflated delta in one call.
        $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lesson]), [
            'seconds_delta' => 60, 'position_seconds' => 60,
        ])->assertOk();

        $this->assertSame(30, $enrollment->fresh()->total_watch_seconds);
    }

    public function test_a_position_beyond_the_lessons_duration_is_clamped(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollmentWithLesson(['duration_minutes' => 1]);

        $response = $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lesson]), [
            'seconds_delta' => 10, 'position_seconds' => 999,
        ]);

        $response->assertJson(['last_position_seconds' => 60]);
    }

    public function test_min_watch_auto_completes_the_lesson_once_the_threshold_is_crossed(): void
    {
        Storage::fake('local');
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollmentWithLesson([
            'duration_minutes' => 1, 'completion_rule' => CompletionRule::MinWatch->value, 'completion_threshold' => 80,
        ]);

        // 80% of 60s = 48s.
        $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lesson]), [
            'seconds_delta' => 30, 'position_seconds' => 30,
        ])->assertJson(['completed' => false]);

        $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lesson]), [
            'seconds_delta' => 20, 'position_seconds' => 50,
        ])->assertJson(['completed' => true]);

        $this->assertDatabaseHas('lesson_progress', ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
        $progress = $enrollment->progressRecords()->where('lesson_id', $lesson->id)->first();
        $this->assertNotNull($progress->completed_at);
    }

    public function test_manual_completion_rule_never_auto_completes_from_watch_time_alone(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollmentWithLesson(['duration_minutes' => 1]);

        $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lesson]), [
            'seconds_delta' => 30, 'position_seconds' => 60,
        ])->assertJson(['completed' => false]);

        $progress = $enrollment->progressRecords()->where('lesson_id', $lesson->id)->first();
        $this->assertNotNull($progress);
        $this->assertNull($progress->completed_at);
    }

    public function test_a_pending_enrollment_cannot_send_a_heartbeat(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollmentWithLesson();
        $enrollment->update(['status' => 'pending']);

        $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lesson]), [
            'seconds_delta' => 15, 'position_seconds' => 15,
        ])->assertForbidden();
    }

    public function test_a_heartbeat_for_a_locked_lesson_in_a_sequential_course_is_rejected(): void
    {
        [$course, , $student] = $this->activeEnrollmentWithLesson();
        $course->update(['progression' => \App\Enums\CourseProgression::Sequential]);
        $module = $course->fresh()->modules->first();
        $lessonTwo = Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);

        $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lessonTwo]), [
            'seconds_delta' => 15, 'position_seconds' => 15,
        ])->assertForbidden();
    }
}
