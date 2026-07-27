<?php

namespace Tests\Feature\Public;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** public-w3 — §3.2/§3.3 of PUBLIC_SITE_PLAN.md: contextual auth continuation. */
class CourseContextRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_with_an_intended_free_course_enrols_and_redirects_into_lesson_1(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);

        $response = $this->post(route('register'), [
            'name' => 'Jane Student',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
            'intended_course' => $course->slug,
        ]);

        $response->assertRedirect(route('learn.course', $course));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    public function test_registering_with_no_intended_course_still_goes_to_the_dashboard(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Jane Student',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_registration_requires_accepting_the_terms(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Jane Student',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
    }

    public function test_an_unpublished_intended_course_is_ignored_not_trusted(): void
    {
        $course = Course::factory()->create(['is_published' => false, 'price' => 0]);

        $response = $this->post(route('register'), [
            'name' => 'Jane Student',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
            'intended_course' => $course->slug,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('enrollments', ['course_id' => $course->id]);
    }

    public function test_a_nonexistent_intended_course_slug_is_ignored_not_500(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Jane Student',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
            'intended_course' => 'does-not-exist',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_the_register_page_shows_the_course_context_banner(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'title' => 'Laravel From Scratch']);

        $response = $this->get(route('register', ['intended_course' => $course->slug]));

        $response->assertOk()->assertSee('Laravel From Scratch');
    }

    public function test_signing_in_with_an_intended_course_enrols_and_redirects_into_lesson_1(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $student = User::factory()->create(['role' => 'student', 'password' => Hash::make('password123')]);

        $response = $this->post(route('login'), [
            'email' => $student->email,
            'password' => 'password123',
            'intended_course' => $course->slug,
        ]);

        $response->assertRedirect(route('learn.course', $course));
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_signing_in_already_enrolled_with_intended_course_still_redirects_to_the_course_not_double_enrolling(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $student = User::factory()->create(['role' => 'student', 'password' => Hash::make('password123')]);
        Enrollment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $response = $this->post(route('login'), [
            'email' => $student->email,
            'password' => 'password123',
            'intended_course' => $course->slug,
        ]);

        $response->assertRedirect(route('learn.course', $course));
        $this->assertSame(1, Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->count());
    }
}
