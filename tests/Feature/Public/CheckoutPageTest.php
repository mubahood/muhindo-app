<?php

namespace Tests\Feature\Public;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GatewayLog;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Gateway\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/** public-w4 — §5.1/§5.2 of PUBLIC_SITE_PLAN.md: checkout order summary and failure-path retry. */
class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_checkout_page_shows_subtotal_and_total_with_no_discount_line_when_no_coupon(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '150000.00', 'currency' => 'UGX']);
        $this->actingAs($student)->post(route('courses.enroll', $course));

        $response = $this->followingRedirects()->get(route('courses.checkout', $course));

        $response->assertOk();
        $response->assertSee('UGX 150,000.00');
        $response->assertDontSee('Coupon discount');
    }

    public function test_the_checkout_page_shows_the_discount_line_when_a_coupon_was_applied(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '150000.00', 'currency' => 'UGX']);
        Coupon::create([
            'code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'course_id' => $course->id,
            'max_uses' => 10, 'used_count' => 0, 'is_active' => true,
        ]);

        $this->actingAs($student)->post(route('courses.enroll', $course), ['coupon_code' => 'SAVE20']);

        $response = $this->followingRedirects()->get(route('courses.checkout', $course));

        $response->assertOk();
        // The one payment screen says "Discount applied" — an invoice discount
        // is not always from a coupon.
        $response->assertSee('Discount applied');
        $response->assertSee('UGX 30,000.00'); // 20% of 150,000
        $response->assertSee('UGX 120,000.00'); // total after discount
    }

    public function test_a_failed_payment_callback_shows_a_retry_link_back_to_the_same_course_checkout(): void
    {
        $fake = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $fake);

        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75000.00', 'currency' => 'UGX']);
        $this->actingAs($student)->post(route('courses.enroll', $course));

        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $invoice = Invoice::find($enrollment->invoice_id);
        $this->post(route('portal.invoice.pay', $invoice))->assertRedirect();
        $txRef = GatewayLog::where('invoice_id', $invoice->id)->value('tx_ref');

        // Guest returns from a cancelled/failed Flutterwave attempt (no successful status).
        $response = $this->get(route('gateway.callback', ['tx_ref' => $txRef, 'status' => 'cancelled']));

        $response->assertOk();
        $response->assertSee('Payment not completed');
        $response->assertSee(route('courses.checkout', $course), false);
        $this->assertSame('pending', $enrollment->fresh()->status);
    }

    public function test_a_successful_payment_callback_shows_no_retry_link(): void
    {
        $fake = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $fake);

        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75000.00', 'currency' => 'UGX']);
        $this->actingAs($student)->post(route('courses.enroll', $course));

        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $invoice = Invoice::find($enrollment->invoice_id);
        $this->post(route('portal.invoice.pay', $invoice))->assertRedirect();
        $txRef = GatewayLog::where('invoice_id', $invoice->id)->value('tx_ref');
        $fake->succeedNext($txRef, '75000.00', 'UGX');

        $response = $this->get(route('gateway.callback', ['transaction_id' => $txRef, 'status' => 'successful', 'tx_ref' => $txRef]));

        $response->assertOk()->assertSee('Payment received');
        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_replaying_the_webhook_for_an_already_settled_invoice_does_not_double_pay(): void
    {
        $fake = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $fake);

        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75000.00', 'currency' => 'UGX']);
        $this->actingAs($student)->post(route('courses.enroll', $course));

        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $invoice = Invoice::find($enrollment->invoice_id);
        $this->post(route('portal.invoice.pay', $invoice))->assertRedirect();
        $txRef = GatewayLog::where('invoice_id', $invoice->id)->value('tx_ref');
        $fake->succeedNext($txRef, '75000.00', 'UGX');

        $this->postJson(route('gateway.webhook'), ['data' => ['id' => $txRef]])->assertOk();
        $this->postJson(route('gateway.webhook'), ['data' => ['id' => $txRef]])->assertOk();

        $this->assertSame(1, $invoice->fresh()->payments()->count());
        $this->assertSame('paid', $invoice->fresh()->status->value);
    }
}
