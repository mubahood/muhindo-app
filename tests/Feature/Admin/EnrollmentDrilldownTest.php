<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\EnrollmentDrilldown;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\StudentNudgeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** §6.3.2 — the per-student drill-down: timeline, lesson checklist, private notes, nudge. */
class EnrollmentDrilldownTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function enrollmentWithLessons(): Enrollment
    {
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Alice Nakato']);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);
    }

    public function test_a_non_admin_cannot_view_the_drilldown(): void
    {
        $enrollment = $this->enrollmentWithLessons();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.enrollments.show', $enrollment))->assertRedirect(route('login'));
    }

    public function test_an_admin_sees_the_students_lesson_checklist_and_activity(): void
    {
        $admin = $this->admin();
        $enrollment = $this->enrollmentWithLessons();
        $enrollment->learningEvents()->create(['event' => 'lesson.viewed']);

        $this->actingAs($admin)->get(route('admin.enrollments.show', $enrollment))
            ->assertOk()
            ->assertSee('Alice Nakato')
            ->assertSee('L1')
            ->assertSee('Viewed a lesson');
    }

    public function test_an_admin_can_add_a_private_note(): void
    {
        $admin = $this->admin();
        $enrollment = $this->enrollmentWithLessons();

        Livewire::actingAs($admin)
            ->test(EnrollmentDrilldown::class, ['enrollment' => $enrollment])
            ->set('newNote', 'Struggling with arrays, needs a check-in.')
            ->call('addNote')
            ->assertSee('Struggling with arrays');

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $admin->id,
            'note' => 'Struggling with arrays, needs a check-in.',
        ]);
    }

    public function test_an_empty_note_is_rejected(): void
    {
        $admin = $this->admin();
        $enrollment = $this->enrollmentWithLessons();

        Livewire::actingAs($admin)
            ->test(EnrollmentDrilldown::class, ['enrollment' => $enrollment])
            ->set('newNote', '')
            ->call('addNote')
            ->assertHasErrors(['newNote' => 'required']);
    }

    public function test_sending_a_nudge_notifies_the_student_and_shows_a_confirmation(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $enrollment = $this->enrollmentWithLessons();

        Livewire::actingAs($admin)
            ->test(EnrollmentDrilldown::class, ['enrollment' => $enrollment])
            ->call('sendNudge')
            ->assertSee('Nudge sent');

        Notification::assertSentTo($enrollment->user, StudentNudgeNotification::class);
    }
}
