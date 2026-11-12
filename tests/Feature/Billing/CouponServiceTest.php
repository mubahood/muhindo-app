<?php

namespace Tests\Feature\Billing;

use App\Enums\CouponScope;
use App\Enums\RedemptionStatus;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\Product;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Coupon validation and the reserve/confirm life of one use. */
class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CouponService
    {
        return app(CouponService::class);
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create($attributes + [
            'code' => 'SAVE20',
            'scope' => CouponScope::Any,
            'type' => 'percent',
            'value' => '20.00',
        ]);
    }

    // ---- the discount itself -------------------------------------------------

    public function test_a_percent_coupon_computes_the_discount_and_holds_a_use(): void
    {
        $course = Course::factory()->create(['price' => '100.00']);
        $coupon = $this->coupon();
        $user = User::factory()->create();

        $result = $this->service()->reserve('SAVE20', $course, '100.00', $user);

        $this->assertEquals('20.00', $result['discount']);
        $this->assertSame(RedemptionStatus::Reserved, $result['redemption']->status);

        // Held, not spent: the cached counter only moves on payment.
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_a_flat_amount_coupon_never_exceeds_the_subtotal(): void
    {
        $course = Course::factory()->create(['price' => '10.00']);
        $this->coupon(['code' => 'FLAT50', 'type' => 'amount', 'value' => '50.00']);

        $result = $this->service()->reserve('FLAT50', $course, '10.00', User::factory()->create());

        $this->assertEquals('10.00', $result['discount']);
    }

    public function test_a_code_is_matched_regardless_of_case_or_padding(): void
    {
        $course = Course::factory()->create();
        $this->coupon();

        $result = $this->service()->reserve('  save20 ', $course, '100.00', User::factory()->create());

        $this->assertEquals('20.00', $result['discount']);
    }

    // ---- rejections ---------------------------------------------------------

    public function test_an_unknown_code_is_rejected(): void
    {
        $this->expectException(InvalidCouponException::class);

        $this->service()->reserve('NOPE', Course::factory()->create(), '100.00', User::factory()->create());
    }

    public function test_an_inactive_coupon_is_rejected(): void
    {
        $this->coupon(['is_active' => false]);

        $this->expectException(InvalidCouponException::class);
        $this->service()->reserve('SAVE20', Course::factory()->create(), '100.00', User::factory()->create());
    }

    public function test_an_expired_coupon_is_rejected(): void
    {
        $this->coupon(['expires_at' => now()->subDay()]);

        $this->expectException(InvalidCouponException::class);
        $this->service()->reserve('SAVE20', Course::factory()->create(), '100.00', User::factory()->create());
    }

    public function test_a_coupon_that_has_not_started_is_rejected(): void
    {
        $this->coupon(['starts_at' => now()->addDay()]);

        $this->expectExceptionMessage('not available yet');
        $this->service()->reserve('SAVE20', Course::factory()->create(), '100.00', User::factory()->create());
    }

    public function test_a_scheduled_coupon_works_once_its_start_passes(): void
    {
        $this->coupon(['starts_at' => now()->subMinute()]);

        $result = $this->service()->reserve('SAVE20', Course::factory()->create(), '100.00', User::factory()->create());

        $this->assertEquals('20.00', $result['discount']);
    }

    public function test_a_subtotal_below_the_minimum_is_rejected(): void
    {
        $this->coupon(['min_subtotal' => '200.00']);

        $this->expectExceptionMessage('at least');
        $this->service()->reserve('SAVE20', Course::factory()->create(), '100.00', User::factory()->create());
    }

    /**
     * The cap is counted from the ledger, not from the cached column, so a
     * hand-edited `used_count` cannot let a campaign oversell or lock it out.
     */
    public function test_the_usage_cap_counts_redemption_rows_not_the_cached_counter(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);
        $course = Course::factory()->create();

        $this->service()->reserve('SAVE20', $course, '100.00', User::factory()->create());

        $this->expectExceptionMessage('usage limit');
        $this->service()->reserve('SAVE20', $course, '100.00', User::factory()->create());
    }

    public function test_a_reservation_holds_a_slot_against_the_cap(): void
    {
        $coupon = $this->coupon(['max_uses' => 2]);

        $this->service()->reserve('SAVE20', Course::factory()->create(), '100.00', User::factory()->create());

        $this->assertSame(1, $coupon->fresh()->usesRemaining());
    }

    public function test_a_stale_reservation_returns_its_slot_to_the_pool(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);
        $course = Course::factory()->create();

        $this->service()->reserve('SAVE20', $course, '100.00', User::factory()->create());
        $this->assertSame(0, $coupon->fresh()->usesRemaining());

        // Nobody paid, and the window lapsed.
        CouponRedemption::query()->update([
            'reserved_at' => now()->subHours(CouponRedemption::reservationHours() + 1),
        ]);

        $this->assertSame(1, $coupon->fresh()->usesRemaining());

        // And the next buyer can have it.
        $result = $this->service()->reserve('SAVE20', $course, '100.00', User::factory()->create());
        $this->assertEquals('20.00', $result['discount']);
    }

    public function test_a_per_user_limit_stops_the_same_person_reusing_a_code(): void
    {
        $this->coupon(['max_uses_per_user' => 1]);
        $course = Course::factory()->create();
        $user = User::factory()->create();

        $this->service()->reserve('SAVE20', $course, '100.00', $user);

        $this->expectExceptionMessage('already used this coupon');
        $this->service()->reserve('SAVE20', $course, '100.00', $user);
    }

    public function test_a_per_user_limit_does_not_stop_a_different_person(): void
    {
        $this->coupon(['max_uses_per_user' => 1]);
        $course = Course::factory()->create();

        $this->service()->reserve('SAVE20', $course, '100.00', User::factory()->create());
        $result = $this->service()->reserve('SAVE20', $course, '100.00', User::factory()->create());

        $this->assertEquals('20.00', $result['discount']);
    }

    // ---- scope --------------------------------------------------------------

    public function test_a_courses_scoped_coupon_is_refused_on_a_product(): void
    {
        $this->coupon(['scope' => CouponScope::Courses]);

        $this->expectExceptionMessage('does not apply');
        $this->service()->reserve('SAVE20', Product::factory()->create(), '100.00', User::factory()->create());
    }

    public function test_a_products_scoped_coupon_discounts_a_product(): void
    {
        $this->coupon(['scope' => CouponScope::Products]);

        $result = $this->service()->reserve('SAVE20', Product::factory()->create(), '500.00', User::factory()->create());

        $this->assertEquals('100.00', $result['discount']);
    }

    public function test_a_products_scoped_coupon_is_refused_on_a_course(): void
    {
        $this->coupon(['scope' => CouponScope::Products]);

        $this->expectExceptionMessage('does not apply');
        $this->service()->reserve('SAVE20', Course::factory()->create(), '100.00', User::factory()->create());
    }

    public function test_an_any_scoped_coupon_discounts_both_halves_of_the_catalogue(): void
    {
        $this->coupon(['scope' => CouponScope::Any]);

        $this->assertEquals('20.00',
            $this->service()->reserve('SAVE20', Course::factory()->create(), '100.00', User::factory()->create())['discount']);
        $this->assertEquals('20.00',
            $this->service()->reserve('SAVE20', Product::factory()->create(), '100.00', User::factory()->create())['discount']);
    }

    public function test_a_targeted_coupon_applies_only_to_its_own_item(): void
    {
        $course = Course::factory()->create();
        $other = Course::factory()->create();
        $this->coupon([
            'scope' => CouponScope::Target,
            'target_type' => $course->getMorphClass(),
            'target_id' => $course->id,
        ]);

        $this->assertEquals('20.00',
            $this->service()->reserve('SAVE20', $course, '100.00', User::factory()->create())['discount']);

        $this->expectExceptionMessage('does not apply');
        $this->service()->reserve('SAVE20', $other, '100.00', User::factory()->create());
    }

    public function test_a_coupon_targeting_a_product_applies_to_that_product(): void
    {
        $product = Product::factory()->create();
        $this->coupon([
            'scope' => CouponScope::Target,
            'target_type' => $product->getMorphClass(),
            'target_id' => $product->id,
        ]);

        $result = $this->service()->reserve('SAVE20', $product, '550000.00', User::factory()->create());

        $this->assertEquals('110000.00', $result['discount']);
    }

    // ---- a quote spends nothing ---------------------------------------------

    public function test_a_quote_returns_the_discount_without_consuming_a_use(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);

        $quote = $this->service()->quote('SAVE20', Course::factory()->create(), '100.00', User::factory()->create());

        $this->assertEquals('20.00', $quote['discount']);
        $this->assertSame(1, $coupon->fresh()->usesRemaining());
        $this->assertSame(0, CouponRedemption::count());
    }

    public function test_a_quote_refuses_an_invalid_code_the_same_way_a_reservation_does(): void
    {
        $this->coupon(['expires_at' => now()->subDay()]);

        $this->expectException(InvalidCouponException::class);
        $this->service()->quote('SAVE20', Course::factory()->create(), '100.00', User::factory()->create());
    }
}
