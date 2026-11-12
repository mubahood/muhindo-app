<?php

namespace Tests\Feature\Billing;

use App\Enums\CouponScope;
use App\Enums\PaymentMethod;
use App\Enums\RedemptionStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A coupon use is spent when the money lands, not when somebody clicks.
 *
 * This is the defect these tests exist for. A use was previously consumed at
 * invoice creation, so a campaign limited to N uses could be emptied by N
 * people who never paid — and on this site only 4 invoices in 19 are ever
 * paid, so the counter was measuring clicks.
 */
class CouponRedemptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function billing(): BillingService
    {
        return app(BillingService::class);
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create($attributes + [
            'code' => 'LAUNCH20',
            'scope' => CouponScope::Any,
            'type' => 'percent',
            'value' => '20.00',
        ]);
    }

    public function test_raising_an_invoice_holds_the_use_and_discounts_the_total(): void
    {
        $coupon = $this->coupon();
        $student = User::factory()->create();
        $course = Course::factory()->create(['price' => '130000.00', 'currency' => 'UGX']);

        $invoice = $this->billing()->generateCourseInvoice($student, $course, 'LAUNCH20');

        $this->assertEquals('130000.00', $invoice->subtotal);
        $this->assertEquals('26000.00', $invoice->discount);
        $this->assertEquals('104000.00', $invoice->total);

        $redemption = CouponRedemption::sole();
        $this->assertSame(RedemptionStatus::Reserved, $redemption->status);
        $this->assertSame($invoice->id, $redemption->invoice_id);
        $this->assertSame($student->id, $redemption->user_id);
        $this->assertEquals('26000.00', $redemption->discount);

        // Not yet a use.
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_paying_the_invoice_turns_the_hold_into_a_real_use(): void
    {
        $coupon = $this->coupon();
        $student = User::factory()->create();
        $course = Course::factory()->create(['price' => '130000.00', 'currency' => 'UGX']);

        $invoice = $this->billing()->generateCourseInvoice($student, $course, 'LAUNCH20');
        $this->billing()->recordPayment($invoice, PaymentMethod::MobileMoney, '104000.00');

        $redemption = CouponRedemption::sole();
        $this->assertSame(RedemptionStatus::Confirmed, $redemption->status);
        $this->assertNotNull($redemption->confirmed_at);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_a_part_payment_does_not_yet_confirm_the_use(): void
    {
        $coupon = $this->coupon();
        $course = Course::factory()->create(['price' => '130000.00', 'currency' => 'UGX']);

        $invoice = $this->billing()->generateCourseInvoice(User::factory()->create(), $course, 'LAUNCH20');
        $this->billing()->recordPayment($invoice, PaymentMethod::MobileMoney, '26000.00');

        $this->assertSame(RedemptionStatus::Reserved, CouponRedemption::sole()->status);
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_an_unpaid_invoice_never_spends_the_use_and_the_slot_comes_back(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);
        $course = Course::factory()->create(['price' => '130000.00', 'currency' => 'UGX']);

        $this->billing()->generateCourseInvoice(User::factory()->create(), $course, 'LAUNCH20');

        // While the buyer is still at the payment screen the slot is held, so
        // a one-use campaign cannot oversell.
        $this->assertSame(0, $coupon->fresh()->usesRemaining());

        CouponRedemption::query()->update([
            'reserved_at' => now()->subHours(CouponRedemption::reservationHours() + 1),
        ]);

        $this->assertSame(1, $coupon->fresh()->usesRemaining());
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_refunding_a_sale_hands_the_use_back(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);
        $course = Course::factory()->create(['price' => '130000.00', 'currency' => 'UGX']);

        $invoice = $this->billing()->generateCourseInvoice(User::factory()->create(), $course, 'LAUNCH20');
        $this->billing()->recordPayment($invoice, PaymentMethod::MobileMoney, '104000.00');
        $this->assertSame(1, $coupon->fresh()->used_count);

        $this->billing()->refund($invoice->fresh());

        $redemption = CouponRedemption::sole();
        $this->assertSame(RedemptionStatus::Released, $redemption->status);
        $this->assertNotNull($redemption->released_at);
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertSame(1, $coupon->fresh()->usesRemaining());
    }

    public function test_confirming_twice_counts_one_use(): void
    {
        $coupon = $this->coupon();
        $course = Course::factory()->create(['price' => '130000.00', 'currency' => 'UGX']);

        $invoice = $this->billing()->generateCourseInvoice(User::factory()->create(), $course, 'LAUNCH20');
        $this->billing()->recordPayment($invoice, PaymentMethod::MobileMoney, '104000.00');

        // A gateway webhook can arrive twice; the second must be a no-op.
        app(\App\Services\CouponService::class)->confirmFor($invoice->fresh());

        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertSame(1, CouponRedemption::where('status', RedemptionStatus::Confirmed)->count());
    }

    public function test_a_coupon_that_zeroes_the_total_still_confirms_its_use(): void
    {
        $coupon = $this->coupon(['type' => 'percent', 'value' => '100.00']);
        $course = Course::factory()->create(['price' => '130000.00', 'currency' => 'UGX']);

        $invoice = $this->billing()->generateCourseInvoice(User::factory()->create(), $course, 'LAUNCH20');
        $this->assertEquals('0.00', $invoice->total);

        // A free order is settled without a Payment row, and must still spend
        // the use — otherwise a 100%-off campaign is unlimited in practice.
        $this->billing()->settleFreeInvoice($invoice);

        $this->assertSame(RedemptionStatus::Confirmed, CouponRedemption::sole()->status);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_performance_reports_what_the_campaign_gave_away(): void
    {
        $coupon = $this->coupon();
        $course = Course::factory()->create(['price' => '130000.00', 'currency' => 'UGX']);

        $paid = $this->billing()->generateCourseInvoice(User::factory()->create(), $course, 'LAUNCH20');
        $this->billing()->recordPayment($paid, PaymentMethod::MobileMoney, '104000.00');

        $this->billing()->generateCourseInvoice(User::factory()->create(), $course, 'LAUNCH20');

        $performance = $coupon->fresh()->performance();

        $this->assertSame(1, $performance['confirmed']);
        $this->assertEquals('26000.00', $performance['confirmed_discount']);
        $this->assertSame(1, $performance['reserved']);
        $this->assertSame(0, $performance['released']);
    }
}
