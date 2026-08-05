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

/** L2. A pending/cancelled enrollment must never grant lesson/material/completion access. */
class EnrollmentAccessPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithLesson(): array
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

    public function test_a_pending_unpaid_enrollment_cannot_view_the_course_player(): void
    {
        [$course] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollmentWithStatus($student, $course, 'pending');

        $this->actingAs($student)->get(route('learn.course', $course))->assertForbidden();
    }

    public function test_a_pending_unpaid_enrollment_cannot_view_a_lesson(): void
    {
        [$course, $lesson] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollmentWithStatus($student, $course, 'pending');

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))->assertForbidden();
    }

    public function test_a_pending_enrollment_cannot_post_lesson_completion(): void
    {
        [$course, $lesson] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->enrollmentWithStatus($student, $course, 'pending');

        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]))->assertForbidden();

        $this->assertDatabaseMissing('lesson_progress', ['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
    }

    public function test_a_cancelled_enrollment_cannot_view_the_course_player(): void
    {
        [$course] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollmentWithStatus($student, $course, 'cancelled');

        $this->actingAs($student)->get(route('learn.course', $course))->assertForbidden();
    }

    public function test_an_active_enrollment_can_view_the_course_player(): void
    {
        [$course] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollmentWithStatus($student, $course, 'active');

        $this->actingAs($student)->get(route('learn.course', $course))->assertRedirect();
    }

    public function test_a_completed_enrollment_can_still_review_the_course(): void
    {
        [$course] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollmentWithStatus($student, $course, 'completed');

        $this->actingAs($student)->get(route('learn.course', $course))->assertRedirect();
    }

    /**
     * Final-walkthrough finding: the pending badge alone was a dead end. A student who
     * abandoned checkout had no way back to it from "My Courses" and would have had to
     * independently remember to revisit the course's public page.
     */
    public function test_the_my_courses_list_shows_a_pending_badge_with_a_way_back_to_checkout_not_a_dead_continue_link(): void
    {
        [$course] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollmentWithStatus($student, $course, 'pending');

        $response = $this->actingAs($student)->get(route('learn.index'));

        $response->assertOk()->assertSee('Payment pending')->assertDontSee('Continue');

        // This enrollment carries no invoice, so the way forward is back to
        // the course to complete enrolling. A pending enrollment that DOES
        // have one links straight to the payment screen instead, covered in
        // StudentNavigationTest.
        $response->assertSee(route('courses.show', $course), false);
    }

    public function test_an_expired_active_enrollment_cannot_view_the_course_player(): void
    {
        [$course] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->enrollmentWithStatus($student, $course, 'active');
        $enrollment->update(['expires_at' => now()->subDay()]);

        $this->actingAs($student)->get(route('learn.course', $course))->assertForbidden();
    }

    public function test_an_active_enrollment_with_a_future_expiry_can_still_view_the_course_player(): void
    {
        [$course] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->enrollmentWithStatus($student, $course, 'active');
        $enrollment->update(['expires_at' => now()->addDay()]);

        $this->actingAs($student)->get(route('learn.course', $course))->assertRedirect();
    }

    public function test_a_super_admin_can_view_any_enrollments_player_regardless_of_status(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);

        [$course] = $this->courseWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollmentWithStatus($student, $course, 'pending');
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        // The admin has no enrollment of their own, accessing another user's course
        // player is out of scope for the `access` ability (it's per-owner), but the
        // super_admin `before` bypass on the policy still short-circuits any check
        // performed directly against a specific Enrollment instance they are given.
        $enrollment = \App\Models\Enrollment::where('user_id', $student->id)->first();
        $this->assertTrue($admin->can('access', $enrollment));
    }
}
