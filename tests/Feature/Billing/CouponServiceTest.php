<?php

namespace Tests\Feature\Billing;

use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Course;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Coupon validation + redemption at course-invoice creation. */
class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_percent_coupon_computes_the_correct_discount_and_increments_used_count(): void
    {
        $course = Course::factory()->create(['price' => '100.00']);
        $coupon = Coupon::create(['code' => 'SAVE20', 'type' => 'percent', 'value' => '20.00']);

        $result = app(CouponService::class)->redeem('SAVE20', $course, '100.00');

        $this->assertEquals('20.00', $result['discount']);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_a_flat_amount_coupon_never_discounts_below_zero_relative_to_subtotal(): void
    {
        $course = Course::factory()->create(['price' => '10.00']);
        Coupon::create(['code' => 'FLAT50', 'type' => 'amount', 'value' => '50.00']);

        $result = app(CouponService::class)->redeem('FLAT50', $course, '10.00');

        $this->assertEquals('10.00', $result['discount']);
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $course = Course::factory()->create();

        $this->expectException(InvalidCouponException::class);
        app(CouponService::class)->redeem('NOPE', $course, '100.00');
    }

    public function test_an_inactive_coupon_is_rejected(): void
    {
        $course = Course::factory()->create();
        Coupon::create(['code' => 'OFF', 'type' => 'percent', 'value' => '10.00', 'is_active' => false]);

        $this->expectException(InvalidCouponException::class);
        app(CouponService::class)->redeem('OFF', $course, '100.00');
    }

    public function test_an_expired_coupon_is_rejected(): void
    {
        $course = Course::factory()->create();
        Coupon::create(['code' => 'OLD', 'type' => 'percent', 'value' => '10.00', 'expires_at' => now()->subDay()]);

        $this->expectException(InvalidCouponException::class);
        app(CouponService::class)->redeem('OLD', $course, '100.00');
    }

    public function test_a_coupon_at_its_usage_limit_is_rejected(): void
    {
        $course = Course::factory()->create();
        Coupon::create(['code' => 'ONCE', 'type' => 'percent', 'value' => '10.00', 'max_uses' => 1, 'used_count' => 1]);

        $this->expectException(InvalidCouponException::class);
        app(CouponService::class)->redeem('ONCE', $course, '100.00');
    }

    public function test_a_coupon_scoped_to_a_different_course_is_rejected(): void
    {
        $course = Course::factory()->create();
        $otherCourse = Course::factory()->create();
        Coupon::create(['code' => 'SPECIFIC', 'type' => 'percent', 'value' => '10.00', 'course_id' => $otherCourse->id]);

        $this->expectException(InvalidCouponException::class);
        app(CouponService::class)->redeem('SPECIFIC', $course, '100.00');
    }

    public function test_a_coupon_with_no_course_scope_applies_to_any_course(): void
    {
        $course = Course::factory()->create();
        Coupon::create(['code' => 'ANY', 'type' => 'percent', 'value' => '10.00', 'course_id' => null]);

        $result = app(CouponService::class)->redeem('ANY', $course, '100.00');

        $this->assertEquals('10.00', $result['discount']);
    }
}
