<?php

namespace Tests\Feature\Billing;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** §7.1 — a coupon code entered at checkout discounts the generated invoice. */
class CouponCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolling_with_a_valid_coupon_discounts_the_invoice(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '100.00', 'currency' => 'UGX']);
        Coupon::create(['code' => 'SAVE20', 'type' => 'percent', 'value' => '20.00']);

        $this->actingAs($student)->post(route('courses.enroll', $course), ['coupon_code' => 'save20'])
            ->assertRedirect(route('courses.checkout', $course));

        $enrollment = Enrollment::first();
        $invoice = Invoice::find($enrollment->invoice_id);
        $this->assertEquals('20.00', (string) $invoice->discount);
        $this->assertEquals('80.00', (string) $invoice->total);
    }

    public function test_enrolling_with_an_invalid_coupon_creates_no_invoice_and_flashes_an_error(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '100.00', 'currency' => 'UGX']);

        $this->actingAs($student)->post(route('courses.enroll', $course), ['coupon_code' => 'BOGUS'])
            ->assertRedirect(route('courses.show', $course))
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Enrollment::count());
    }

    public function test_a_second_student_cannot_exceed_a_coupons_max_uses(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => '100.00', 'currency' => 'UGX']);
        Coupon::create(['code' => 'LIMITED', 'type' => 'percent', 'value' => '10.00', 'max_uses' => 1]);

        $first = User::factory()->create(['role' => 'student']);
        $this->actingAs($first)->post(route('courses.enroll', $course), ['coupon_code' => 'LIMITED'])
            ->assertRedirect(route('courses.checkout', $course));

        $second = User::factory()->create(['role' => 'student']);
        $this->actingAs($second)->post(route('courses.enroll', $course), ['coupon_code' => 'LIMITED'])
            ->assertSessionHas('error');

        $this->assertSame(1, Invoice::count());
    }
}
