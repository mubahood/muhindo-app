<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CourseStudents;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** The instructor's Course -> Students workhorse. */
class CourseStudentsTabTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function enrollStudent(Course $course, string $name, string $status = 'active'): Enrollment
    {
        $student = User::factory()->create(['role' => 'student', 'name' => $name]);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => $status,
            'source' => 'self',
            'enrolled_at' => now(),
        ]);
    }

    public function test_a_non_admin_cannot_view_the_students_tab(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.courses.students', $course))->assertRedirect(route('login'));
    }

    /**
     * Regression: layouts.admin's <title> used only @yield('title'), which Livewire's
     * full-page ->title() never populates (it passes a $title layout param instead),
     * a full-page Livewire route silently kept the layout's "Dashboard" browser-tab
     * title. Caught by manual smoke test, fixed in the layout; this pins it.
     */
    public function test_the_browser_tab_title_reflects_the_course_name(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create(['title' => 'Laravel Basics']);

        $this->actingAs($admin)->get(route('admin.courses.students', $course))
            ->assertSee('<title>Laravel Basics | Students · Muhindo Mubaraka</title>', false);
    }

    public function test_an_admin_sees_every_enrolled_student(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $this->enrollStudent($course, 'Alice Nakato');
        $this->enrollStudent($course, 'Brian Okello');

        $this->actingAs($admin)->get(route('admin.courses.students', $course))
            ->assertOk()
            ->assertSee('Alice Nakato')
            ->assertSee('Brian Okello');
    }

    public function test_searching_by_student_name_filters_the_list(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $this->enrollStudent($course, 'Alice Nakato');
        $this->enrollStudent($course, 'Brian Okello');

        Livewire::actingAs($admin)
            ->test(CourseStudents::class, ['course' => $course])
            ->set('search', 'Alice')
            ->assertSee('Alice Nakato')
            ->assertDontSee('Brian Okello');
    }

    public function test_filtering_by_status_only_shows_matching_enrollments(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $this->enrollStudent($course, 'Alice Nakato', 'active');
        $this->enrollStudent($course, 'Brian Okello', 'pending');

        Livewire::actingAs($admin)
            ->test(CourseStudents::class, ['course' => $course])
            ->set('statusFilter', 'pending')
            ->assertSee('Brian Okello')
            ->assertDontSee('Alice Nakato');
    }

    public function test_a_students_progress_percent_is_visible_in_the_row(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $enrollment = $this->enrollStudent($course, 'Alice Nakato');
        $enrollment->update(['progress_percent' => 42]);

        $this->actingAs($admin)->get(route('admin.courses.students', $course))->assertSee('42%');
    }

    public function test_a_students_from_a_different_course_are_not_listed(): void
    {
        $admin = $this->admin();
        $courseA = Course::factory()->create();
        $courseB = Course::factory()->create();
        $this->enrollStudent($courseA, 'Alice Nakato');
        $this->enrollStudent($courseB, 'Brian Okello');

        $this->actingAs($admin)->get(route('admin.courses.students', $courseA))
            ->assertSee('Alice Nakato')
            ->assertDontSee('Brian Okello');
    }

    public function test_an_at_risk_enrollment_shows_the_at_risk_badge(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $enrollment = $this->enrollStudent($course, 'Alice Nakato');
        $enrollment->update(['at_risk_reason' => 'inactive']);

        $this->actingAs($admin)->get(route('admin.courses.students', $course))->assertSee('Inactive');
    }
}
