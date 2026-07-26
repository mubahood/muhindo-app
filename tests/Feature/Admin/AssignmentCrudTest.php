<?php

namespace Tests\Feature\Admin;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** §5.1/§5.3 — admin CRUD for course assignments. */
class AssignmentCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_an_admin_can_create_an_assignment(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();

        $this->actingAs($admin)->post(route('admin.courses.assignments.store', $course), [
            'title' => 'Essay 1',
            'points' => 50,
            'max_file_mb' => 10,
            'allowed_types' => ['text', 'pdf'],
            'is_published' => '1',
        ])->assertRedirect(route('admin.courses.show', $course));

        $assignment = Assignment::where('title', 'Essay 1')->first();
        $this->assertSame(50, $assignment->points);
        $this->assertSame('text,pdf', $assignment->allowed_types);
        $this->assertTrue($assignment->is_published);
    }

    public function test_an_admin_can_update_an_assignment(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $assignment = $course->assignments()->create([
            'title' => 'Old title', 'points' => 100, 'max_file_mb' => 20, 'allowed_types' => 'text,link',
        ]);

        $this->actingAs($admin)->put(route('admin.assignments.update', $assignment), [
            'title' => 'New title', 'points' => 75, 'max_file_mb' => 20, 'allowed_types' => ['link'],
        ])->assertRedirect(route('admin.courses.show', $course));

        $assignment->refresh();
        $this->assertSame('New title', $assignment->title);
        $this->assertSame(75, $assignment->points);
        $this->assertSame('link', $assignment->allowed_types);
    }

    public function test_an_admin_can_delete_an_assignment(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $assignment = $course->assignments()->create(['title' => 'X', 'points' => 100, 'max_file_mb' => 20, 'allowed_types' => 'text']);

        $this->actingAs($admin)->delete(route('admin.assignments.destroy', $assignment))
            ->assertRedirect(route('admin.courses.show', $course));

        $this->assertSoftDeleted('assignments', ['id' => $assignment->id]);
    }

    public function test_a_non_admin_cannot_create_an_assignment(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();

        $this->actingAs($student)->post(route('admin.courses.assignments.store', $course), [
            'title' => 'X', 'points' => 100, 'max_file_mb' => 20, 'allowed_types' => ['text'],
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('assignments', ['title' => 'X']);
    }
}
