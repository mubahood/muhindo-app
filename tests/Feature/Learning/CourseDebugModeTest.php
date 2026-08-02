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

/**
 * The per-course authoring switch that lifts the pacing gates.
 *
 * Two things are worth defending. It has to actually work — a lesson with a
 * minimum time and a required quiz must complete immediately — and it must
 * never be quiet about it, because while it is on, anyone enrolled can finish
 * the course and earn its certificate without doing the work.
 */
class CourseDebugModeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Course,2:Lesson,3:Enrollment} */
    private function gatedCourse(bool $debug = false): array
    {
        $student = User::factory()->create(['role' => 'student', 'is_student' => true]);
        $course = Course::factory()->create(['is_published' => true, 'price' => 0, 'debug_mode' => $debug]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module 1', 'sort_order' => 1]);

        $lesson = Lesson::create([
            'course_module_id' => $module->id,
            'title' => 'A long lesson',
            'content' => 'Read this carefully.',
            'sort_order' => 1,
            'is_published' => true,
            // Ten minutes of focused time before it may be completed.
            'min_active_seconds' => 600,
        ]);

        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        return [$student, $course, $lesson, $enrollment];
    }

    // ── Off by default ──────────────────────────────────────────────────────

    public function test_a_course_is_not_in_debug_mode_unless_it_is_turned_on(): void
    {
        [, $course, $lesson, $enrollment] = $this->gatedCourse();

        $this->assertFalse($course->debug_mode);

        $blockers = app(ProgressService::class)->completionBlockers($enrollment, $lesson);
        $this->assertNotEmpty($blockers);
        $this->assertSame('min_time', $blockers[0]['type']);
    }

    public function test_without_it_the_lesson_refuses_to_complete_early(): void
    {
        [$student, $course, $lesson] = $this->gatedCourse();

        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]));

        $this->assertDatabaseMissing('lesson_progress', [
            'lesson_id' => $lesson->id,
            'completed_at' => null,
        ], null);
        $this->assertNull(
            \App\Models\LessonProgress::where('lesson_id', $lesson->id)->value('completed_at'),
            'the minimum time was not served, so this must not be complete'
        );
    }

    // ── On ──────────────────────────────────────────────────────────────────

    public function test_with_it_on_every_gate_is_lifted(): void
    {
        [, , $lesson, $enrollment] = $this->gatedCourse(debug: true);

        $this->assertSame([], app(ProgressService::class)->completionBlockers($enrollment, $lesson));
    }

    public function test_with_it_on_the_student_moves_straight_to_the_next_topic(): void
    {
        [$student, $course, $lesson] = $this->gatedCourse(debug: true);

        // No time served at all.
        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull(
            \App\Models\LessonProgress::where('lesson_id', $lesson->id)->value('completed_at'),
            'debug mode should have allowed this to complete immediately'
        );
    }

    public function test_the_countdown_is_not_sent_to_the_page_either(): void
    {
        [$student, $course, $lesson] = $this->gatedCourse(debug: true);

        // The button is disabled by a client-side countdown of its own, so the
        // server lifting the gate is not enough on its own.
        $html = (string) $this->actingAs($student)
            ->get(route('learn.lesson', [$course, $lesson]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/minActiveSeconds(\\\\u0022|&quot;|")\s*:\s*0/', $html,
            'the page must not ship a countdown that keeps the button disabled');
    }

    public function test_it_says_so_on_the_lesson_rather_than_changing_things_quietly(): void
    {
        [$student, $course, $lesson] = $this->gatedCourse(debug: true);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))->assertOk()
            ->assertSee('Debug mode is on for this course');
    }

    public function test_an_ordinary_course_shows_no_such_notice(): void
    {
        [$student, $course, $lesson] = $this->gatedCourse();

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))->assertOk()
            ->assertDontSee('Debug mode is on for this course');
    }

    // ── Who may turn it on ──────────────────────────────────────────────────

    public function test_staff_can_turn_it_on_and_off_from_the_course_form(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        [, $course] = $this->gatedCourse();

        // Mirrors what the form actually posts. The slug matters: it is the
        // course's route key, and leaving it out makes the controller
        // regenerate it from the title — which would change the URL this very
        // test then posts to.
        $payload = [
            'title' => $course->title,
            'slug' => $course->slug,
            'description' => $course->description ?: 'A course.',
            'price' => (string) $course->price,
            'currency' => $course->currency,
            'level' => 'beginner',
            'debug_mode' => '1',
        ];

        $this->actingAs($admin)->put(route('admin.courses.update', $course), $payload)
            ->assertSessionHasNoErrors();
        $this->assertTrue($course->fresh()->debug_mode);

        // Unchecking a checkbox sends nothing at all, so absence has to mean off.
        unset($payload['debug_mode']);
        $this->actingAs($admin)->put(route('admin.courses.update', $course), $payload)
            ->assertSessionHasNoErrors();
        $this->assertFalse($course->fresh()->debug_mode);
    }

    public function test_a_student_cannot_turn_it_on_for_themselves(): void
    {
        [$student, $course] = $this->gatedCourse();

        // It is a way to earn a certificate without doing the work, so this
        // being staff-only is the whole safety of the feature.
        $this->actingAs($student)->put(route('admin.courses.update', $course), [
            'title' => $course->title, 'debug_mode' => '1',
        ])->assertRedirect();

        $this->assertFalse($course->fresh()->debug_mode);
    }

    public function test_it_is_confined_to_the_course_it_is_set_on(): void
    {
        [$student, , $lesson] = $this->gatedCourse(debug: true);
        [, $otherCourse, $otherLesson, $otherEnrollment] = $this->gatedCourse();

        // Enrolled on both; only the debug course is unlocked.
        Enrollment::where('user_id', $otherEnrollment->user_id)->update(['user_id' => $student->id]);

        $this->assertSame([], app(ProgressService::class)->completionBlockers(
            Enrollment::where('course_id', $lesson->module->course_id)->firstOrFail(), $lesson
        ));

        $this->assertNotEmpty(app(ProgressService::class)->completionBlockers(
            Enrollment::where('course_id', $otherCourse->id)->firstOrFail(), $otherLesson
        ));
    }
}
