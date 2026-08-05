<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Admin CRUD + publish flow for course announcements. */
class AnnouncementCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function activeStudent(Course $course): User
    {
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        return $student;
    }

    public function test_creating_a_draft_does_not_notify_anyone(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $course = Course::factory()->create();
        $student = $this->activeStudent($course);

        $this->actingAs($admin)->post(route('admin.courses.announcements.store', $course), [
            'title' => 'Draft update', 'body' => 'Coming soon.',
        ])->assertRedirect(route('admin.courses.show', $course));

        $announcement = Announcement::first();
        $this->assertNull($announcement->published_at);
        Notification::assertNothingSent();
    }

    public function test_publishing_immediately_notifies_every_active_student(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $course = Course::factory()->create();
        $student = $this->activeStudent($course);
        $pendingStudent = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $pendingStudent->id, 'course_id' => $course->id,
            'status' => 'pending', 'source' => 'self',
        ]);

        $this->actingAs($admin)->post(route('admin.courses.announcements.store', $course), [
            'title' => 'Live now', 'body' => 'We just launched module 3!', 'publish_now' => '1',
        ]);

        $announcement = Announcement::first();
        $this->assertNotNull($announcement->published_at);
        Notification::assertSentTo($student, AnnouncementPublishedNotification::class);
        Notification::assertNotSentTo($pendingStudent, AnnouncementPublishedNotification::class);
    }

    public function test_publishing_a_draft_later_notifies_students_exactly_once(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $course = Course::factory()->create();
        $student = $this->activeStudent($course);
        $announcement = $course->announcements()->create(['title' => 'Draft', 'body' => 'Body', 'published_at' => null]);

        $this->actingAs($admin)->post(route('admin.announcements.publish', $announcement))
            ->assertRedirect(route('admin.courses.show', $course));

        $this->assertNotNull($announcement->fresh()->published_at);
        Notification::assertSentToTimes($student, AnnouncementPublishedNotification::class, 1);
    }

    public function test_publishing_an_already_published_announcement_does_not_renotify(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $course = Course::factory()->create();
        $this->activeStudent($course);
        $announcement = $course->announcements()->create(['title' => 'Live', 'body' => 'Body', 'published_at' => now()]);

        $this->actingAs($admin)->post(route('admin.announcements.publish', $announcement))
            ->assertSessionHas('error');

        Notification::assertNothingSent();
    }

    public function test_editing_a_published_announcement_does_not_renotify(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $course = Course::factory()->create();
        $this->activeStudent($course);
        $announcement = $course->announcements()->create(['title' => 'Live', 'body' => 'Original', 'published_at' => now()]);

        $this->actingAs($admin)->put(route('admin.announcements.update', $announcement), [
            'title' => 'Live (edited)', 'body' => 'Updated body',
        ])->assertRedirect(route('admin.courses.show', $course));

        $this->assertSame('Live (edited)', $announcement->fresh()->title);
        Notification::assertNothingSent();
    }

    public function test_an_admin_can_delete_an_announcement(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $announcement = $course->announcements()->create(['title' => 'X', 'body' => 'Y']);

        $this->actingAs($admin)->delete(route('admin.announcements.destroy', $announcement))
            ->assertRedirect(route('admin.courses.show', $course));

        $this->assertSoftDeleted('announcements', ['id' => $announcement->id]);
    }

    public function test_a_non_admin_cannot_create_an_announcement(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();

        $this->actingAs($student)->post(route('admin.courses.announcements.store', $course), [
            'title' => 'X', 'body' => 'Y',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('announcements', ['title' => 'X']);
    }
}
