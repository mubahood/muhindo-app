<?php

namespace Tests\Feature\Admin;

use App\Mail\WelcomeCredentials;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §7.5 — bulk enroll: paste emails, unknown ones get a real account + WelcomeCredentials mail. */
class BulkEnrollTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_a_non_admin_cannot_bulk_enroll(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();

        $this->actingAs($student)->post(route('admin.courses.bulk-enroll.store', $course), ['emails' => 'x@example.com'])
            ->assertRedirect(route('login'));
    }

    public function test_unknown_emails_get_a_new_account_and_a_welcome_email(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $course = Course::factory()->create();

        $this->actingAs($admin)->post(route('admin.courses.bulk-enroll.store', $course), [
            'emails' => "new1@example.com\nnew2@example.com",
        ])->assertRedirect(route('admin.courses.show', $course));

        $this->assertDatabaseHas('users', ['email' => 'new1@example.com', 'role' => 'student']);
        $this->assertDatabaseHas('users', ['email' => 'new2@example.com', 'role' => 'student']);
        $this->assertSame(2, Enrollment::where('course_id', $course->id)->count());
        Mail::assertSent(WelcomeCredentials::class, 2);
    }

    public function test_an_existing_users_email_is_enrolled_without_creating_a_duplicate_account_or_emailing_credentials(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $course = Course::factory()->create();
        $existing = User::factory()->create(['role' => 'student', 'email' => 'already@example.com']);

        $this->actingAs($admin)->post(route('admin.courses.bulk-enroll.store', $course), ['emails' => 'already@example.com']);

        $this->assertSame(1, User::where('email', 'already@example.com')->count());
        $this->assertDatabaseHas('enrollments', ['user_id' => $existing->id, 'course_id' => $course->id]);
        Mail::assertNothingSent();
    }

    public function test_an_already_enrolled_student_is_not_enrolled_twice(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $existing = User::factory()->create(['role' => 'student', 'email' => 'already@example.com']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $existing->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.courses.bulk-enroll.store', $course), ['emails' => 'already@example.com'])
            ->assertSessionHas('success');

        $this->assertSame(1, Enrollment::where('user_id', $existing->id)->where('course_id', $course->id)->count());
    }

    public function test_invalid_emails_are_skipped_and_reported(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.courses.bulk-enroll.store', $course), [
            'emails' => 'valid@example.com, not-an-email',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'valid@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'not-an-email']);
        $response->assertSessionHas('success', fn ($message) => str_contains($message, 'not-an-email'));
    }

    public function test_comma_and_newline_separated_lists_both_parse(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();

        $this->actingAs($admin)->post(route('admin.courses.bulk-enroll.store', $course), [
            'emails' => "a@example.com, b@example.com\nc@example.com",
        ]);

        $this->assertSame(3, Enrollment::where('course_id', $course->id)->count());
    }
}
