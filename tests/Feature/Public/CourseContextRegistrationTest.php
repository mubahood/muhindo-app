<?php

namespace Tests\Feature\Public;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
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
            'account_type' => 'student',
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
            'account_type' => 'student',
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
            'account_type' => 'student',
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
            'account_type' => 'student',
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

    /** W7 walkthrough finding — a guest with a coupon had no way to enter it before an account existed. */
    public function test_the_buy_box_shows_a_coupon_field_for_guests_on_a_paid_course(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 100000]);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('name="coupon_code"', false);
    }

    public function test_the_buy_box_shows_no_coupon_field_for_guests_on_a_free_course(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 0]);

        $response = $this->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertDontSee('name="coupon_code"', false);
    }

    public function test_registering_as_a_guest_with_a_coupon_applies_the_discount_to_the_first_invoice(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 100000, 'currency' => 'UGX']);
        Coupon::create([
            'code' => 'GUEST20', 'type' => 'percent', 'value' => 20, 'course_id' => $course->id,
            'max_uses' => 10, 'used_count' => 0, 'is_active' => true,
        ]);

        $response = $this->post(route('register'), [
            'name' => 'Coupon Guest',
            'email' => 'coupon.guest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
            'account_type' => 'student',
            'intended_course' => $course->slug,
            'coupon_code' => 'GUEST20',
        ]);

        $response->assertRedirect(route('courses.checkout', $course));

        $enrollment = Enrollment::where('course_id', $course->id)->firstOrFail();
        $invoice = Invoice::findOrFail($enrollment->invoice_id);
        $this->assertSame('20000.00', (string) $invoice->discount);
        $this->assertSame('80000.00', (string) $invoice->total);
        $this->assertSame(1, Coupon::where('code', 'GUEST20')->value('used_count'));
    }

    public function test_signing_in_as_a_guest_with_a_coupon_applies_the_discount_to_the_first_invoice(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 100000, 'currency' => 'UGX']);
        $student = User::factory()->create(['role' => 'student', 'password' => Hash::make('password123')]);
        Coupon::create([
            'code' => 'SIGNIN20', 'type' => 'percent', 'value' => 20, 'course_id' => $course->id,
            'max_uses' => 10, 'used_count' => 0, 'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'email' => $student->email,
            'password' => 'password123',
            'intended_course' => $course->slug,
            'coupon_code' => 'SIGNIN20',
        ]);

        $response->assertRedirect(route('courses.checkout', $course));

        $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->firstOrFail();
        $invoice = Invoice::findOrFail($enrollment->invoice_id);
        $this->assertSame('80000.00', (string) $invoice->total);
    }

    public function test_an_invalid_coupon_from_a_guest_does_not_block_registration_it_falls_through_to_checkout_with_the_error_shown(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => 100000]);

        $response = $this->post(route('register'), [
            'name' => 'Bad Coupon Guest',
            'email' => 'badcoupon.guest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
            'account_type' => 'student',
            'intended_course' => $course->slug,
            'coupon_code' => 'DOES-NOT-EXIST',
        ]);

        // The account is created and the guest is authenticated regardless — an invalid
        // coupon must never block registration itself, only fail to discount the invoice.
        $this->assertAuthenticated();
        $response->assertRedirect(route('courses.show', $course));
        $response->assertSessionHas('error');
    }
}
