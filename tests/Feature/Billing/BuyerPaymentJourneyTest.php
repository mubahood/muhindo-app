<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Gateway\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * Everything a buyer can do with an unpaid order.
 *
 * The single rule this file exists to defend: nothing here unlocks anything.
 * Access comes from a Payment clearing the balance and InvoicePaid firing,
 * never from saying you intend to pay. "I'll pay Muhindo directly" is the
 * obvious place for that to go wrong, so it is tested from both ends: the
 * content stays shut when the promise is made, and it opens when the cash is
 * actually recorded.
 */
class BuyerPaymentJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create(['role' => 'student', 'is_student' => true]);
    }

    private function paidCourse(): Course
    {
        return Course::factory()->create(['is_published' => true, 'price' => '120000.00', 'currency' => 'UGX']);
    }

    /** Enrol in a paid course and return [student, course, invoice]. */
    private function enrolUnpaid(): array
    {
        $student = $this->student();
        $course = $this->paidCourse();

        $this->actingAs($student)->post(route('courses.enroll', $course));

        return [$student, $course, Invoice::firstOrFail()];
    }

    // Getting to the payment screen

    public function test_enrolling_in_a_paid_course_leads_to_the_payment_screen(): void
    {
        [$student, $course] = $this->enrolUnpaid();

        $this->actingAs($student)
            ->post(route('courses.enroll', $course))
            ->assertRedirect(route('courses.checkout', $course));

        $this->actingAs($student)->followingRedirects()
            ->get(route('courses.checkout', $course))
            ->assertOk()
            ->assertSee('Complete your payment')
            ->assertSee('UGX 120,000.00');
    }

    public function test_opening_an_unpaid_course_sends_you_to_pay_rather_than_forbidding_you(): void
    {
        [$student, $course, $invoice] = $this->enrolUnpaid();

        // A 403 here would be a dead end for somebody who is one payment away.
        $this->actingAs($student)->get(route('learn.course', $course))
            ->assertRedirect(route('payments.show', $invoice));
    }

    public function test_someone_elses_order_is_still_forbidden(): void
    {
        [, , $invoice] = $this->enrolUnpaid();

        $this->actingAs($this->student())->get(route('payments.show', $invoice))->assertForbidden();
        $this->actingAs($this->student())->post(route('payments.cancel', $invoice))->assertForbidden();
        $this->actingAs($this->student())->post(route('payments.direct', $invoice))->assertForbidden();
    }

    // "I will pay Muhindo directly"

    public function test_paying_directly_lets_you_through_to_the_dashboard(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        $this->actingAs($student)->post(route('payments.direct', $invoice))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertNotNull($invoice->fresh()->direct_payment_at);
        $this->actingAs($student)->get(route('dashboard'))->assertOk();
    }

    public function test_paying_directly_unlocks_absolutely_nothing(): void
    {
        [$student, $course, $invoice] = $this->enrolUnpaid();

        $this->actingAs($student)->post(route('payments.direct', $invoice));

        // The invoice is untouched as an invoice: still owed, still payable.
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('120000.00', (string) $invoice->balance);
        $this->assertTrue($invoice->isOutstanding());

        // The enrollment is still pending and the course is still shut.
        $this->assertSame('pending', Enrollment::firstOrFail()->status);
        $this->actingAs($student)->get(route('learn.course', $course))
            ->assertRedirect(route('payments.show', $invoice));
    }

    public function test_the_promise_is_visible_afterwards_rather_than_only_a_flash_message(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        $this->actingAs($student)->post(route('payments.direct', $invoice));

        // Somebody who scrolls past the flash message still has a standing
        // answer to "why will my course not open?" and a way to pay.
        $this->actingAs($student)->get(route('dashboard'))->assertOk()
            ->assertSee('paying Muhindo directly', false)
            ->assertSee(route('payments.show', $invoice), false);
    }

    public function test_recording_the_cash_afterwards_grants_access(): void
    {
        [$student, $course, $invoice] = $this->enrolUnpaid();

        $this->actingAs($student)->post(route('payments.direct', $invoice));

        // Muhindo receives the money and records it, exactly as staff do today.
        app(BillingService::class)->recordPayment($invoice->fresh(), PaymentMethod::Cash, '120000.00');

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('active', Enrollment::firstOrFail()->status);
        $this->actingAs($student)->get(route('learn.course', $course))->assertOk();
    }

    public function test_saying_it_twice_does_not_move_the_goalposts(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        $this->actingAs($student)->post(route('payments.direct', $invoice));
        $first = $invoice->fresh()->direct_payment_at;

        $this->travel(2)->days();
        $this->actingAs($student)->post(route('payments.direct', $invoice));

        // A second click must not reset how overdue this looks to whoever chases it.
        $this->assertEquals($first, $invoice->fresh()->direct_payment_at);
    }

    public function test_you_can_change_your_mind_and_pay_online(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        $this->actingAs($student)->post(route('payments.direct', $invoice));
        $this->actingAs($student)->post(route('payments.online', $invoice))
            ->assertRedirect(route('payments.show', $invoice));

        $this->assertNull($invoice->fresh()->direct_payment_at);
    }

    // Cancelling

    public function test_cancelling_voids_the_invoice_and_frees_the_enrollment(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        $this->actingAs($student)->post(route('payments.cancel', $invoice))
            ->assertRedirect(route('payments.index'));

        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->cancelled_at);
        $this->assertSame('cancelled', Enrollment::firstOrFail()->status);
    }

    public function test_you_can_buy_the_same_course_again_after_cancelling(): void
    {
        [$student, $course, $invoice] = $this->enrolUnpaid();

        $this->actingAs($student)->post(route('payments.cancel', $invoice));

        // A leftover pending enrollment pointing at a void invoice would send
        // them to pay an order that can never be paid.
        $this->actingAs($student)->post(route('courses.enroll', $course));

        $fresh = Enrollment::firstOrFail();
        $this->assertSame('pending', $fresh->status);
        $this->assertNotSame($invoice->id, $fresh->invoice_id);
        $this->assertTrue(Invoice::findOrFail($fresh->invoice_id)->isOutstanding());
    }

    public function test_a_part_paid_order_cannot_be_cancelled_away(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        app(BillingService::class)->recordPayment($invoice, PaymentMethod::Cash, '20000.00');

        $this->actingAs($student)->post(route('payments.cancel', $invoice))
            ->assertSessionHas('error');

        // Voiding this would silently strand the 20,000 already handed over.
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
    }

    public function test_a_paid_order_cannot_be_cancelled(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        app(BillingService::class)->recordPayment($invoice, PaymentMethod::Cash, '120000.00');

        $this->actingAs($student)->post(route('payments.cancel', $invoice))
            ->assertSessionHas('error');

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('active', Enrollment::firstOrFail()->status);
    }

    public function test_cancelling_twice_is_not_an_error(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        $this->actingAs($student)->post(route('payments.cancel', $invoice));
        $this->actingAs($student)->post(route('payments.cancel', $invoice))
            ->assertRedirect(route('payments.index'))
            ->assertSessionHasNoErrors();
    }

    // Recovering a payment whose callback never arrived

    public function test_a_stuck_payment_can_be_recovered_on_demand(): void
    {
        [$student, $course, $invoice] = $this->enrolUnpaid();

        $gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $gateway);

        // The payer reaches Flutterwave and pays, then the browser never
        // comes back and no webhook is configured.
        $this->actingAs($student)->post(route('portal.invoice.pay', $invoice));
        $txRef = \App\Models\GatewayLog::firstOrFail()->tx_ref;
        $gateway->succeedNext($txRef, '120000.00', 'UGX');

        $this->assertSame('pending', Enrollment::firstOrFail()->status);

        $this->actingAs($student)->post(route('payments.recheck', $invoice))
            ->assertRedirect(route('payments.index'))
            ->assertSessionHas('success');

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('active', Enrollment::firstOrFail()->status);
        $this->actingAs($student)->get(route('learn.course', $course))->assertOk();
    }

    public function test_rechecking_an_unpaid_order_does_not_invent_a_payment(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);

        // Nothing was ever started.
        $this->actingAs($student)->post(route('payments.recheck', $invoice))
            ->assertSessionHas('error');

        // Started, but never actually paid at the gateway.
        $this->actingAs($student)->post(route('portal.invoice.pay', $invoice));
        $this->actingAs($student)->post(route('payments.recheck', $invoice))
            ->assertSessionHas('error');

        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        $this->assertSame('0.00', (string) $invoice->fresh()->amount_paid);
    }

    // The same journey for source code

    public function test_source_code_uses_the_very_same_payment_screen(): void
    {
        $buyer = $this->student();
        $product = Product::factory()->create(['is_published' => true, 'price' => '50000.00', 'currency' => 'UGX']);

        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($buyer)->post(route('checkout.place'));

        $invoice = Invoice::firstOrFail();

        $this->actingAs($buyer)->get(route('payments.show', $invoice))->assertOk()
            ->assertSee('Complete your payment')
            ->assertSee('I will pay Mr. Muhindo Mubaraka directly')
            ->assertSee('UGX 50,000.00');
    }

    public function test_paying_directly_for_source_code_withholds_the_download(): void
    {
        $buyer = $this->student();
        $product = Product::factory()->create(['is_published' => true, 'price' => '50000.00', 'currency' => 'UGX']);

        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($buyer)->post(route('checkout.place'));
        $invoice = Invoice::firstOrFail();

        $this->actingAs($buyer)->post(route('payments.direct', $invoice))->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('product_licenses', ['user_id' => $buyer->id, 'product_id' => $product->id]);
        $this->actingAs($buyer)->get(route('shop.download', $product))->assertForbidden();
    }

    // The orders list

    public function test_my_orders_separates_what_is_owed_from_what_is_done(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        $this->actingAs($student)->get(route('payments.index'))->assertOk()
            ->assertSee('Waiting for payment')
            ->assertSee(route('payments.show', $invoice), false);

        app(BillingService::class)->recordPayment($invoice, PaymentMethod::Cash, '120000.00');

        $this->actingAs($student)->get(route('payments.index'))->assertOk()
            ->assertSee('Nothing outstanding');
    }

    public function test_my_orders_shows_only_my_own(): void
    {
        [, , $invoice] = $this->enrolUnpaid();

        $this->actingAs($this->student())->get(route('payments.index'))->assertOk()
            ->assertDontSee(route('payments.show', $invoice), false);
    }

    // Project invoices, billed to a Client rather than the User

    /**
     * The branch that shipped broken. A plain whereHas on the billable MorphTo
     * ran "where user_id = ?" against the users table as well as the clients
     * table; MySQL rejects that outright, and the dashboard 500'd for every
     * signed-in person. SQLite executes the same SQL happily, so nothing in
     * this suite noticed, hence a test that exercises the Client branch by
     * value rather than by whether the query merely runs.
     */
    public function test_a_project_invoice_belongs_to_the_client_behind_it(): void
    {
        $owner = $this->student();
        $client = \App\Models\Client::factory()->create(['user_id' => $owner->id]);
        $stranger = $this->student();

        $invoice = app(\App\Services\BillingService::class)->generateInvoice(
            $client,
            [['description' => 'Clinic management system', 'quantity' => 1, 'unit_price' => '900000.00']],
        );

        $this->assertTrue(Invoice::ownedBy($owner)->whereKey($invoice->id)->exists());
        $this->assertFalse(Invoice::ownedBy($stranger)->whereKey($invoice->id)->exists());

        // And it reaches both screens it is supposed to reach.
        $this->actingAs($owner)->get(route('payments.index'))->assertOk()
            ->assertSee('Clinic management system');
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()
            ->assertSee('Clinic management system');
        $this->actingAs($stranger)->get(route('payments.index'))->assertOk()
            ->assertDontSee('Clinic management system');
    }

    public function test_a_course_invoice_is_not_claimed_by_an_unrelated_client_owner(): void
    {
        [$student, , $invoice] = $this->enrolUnpaid();

        // Somebody who merely has a client record must not inherit other
        // people's course invoices through the Client branch.
        $other = $this->student();
        \App\Models\Client::factory()->create(['user_id' => $other->id]);

        $this->assertTrue(Invoice::ownedBy($student)->whereKey($invoice->id)->exists());
        $this->assertFalse(Invoice::ownedBy($other)->whereKey($invoice->id)->exists());
    }

    /**
     * A structural guard, because a behavioural one cannot do this job here.
     *
     * The suite runs SQLite while production runs MySQL, and SQLite executes
     * the broken form of this query without complaint, so no amount of
     * "does the right invoice come back" testing catches it. This asserts the
     * shape instead: the client rule must be scoped to the clients table and
     * must never be applied to users.
     */
    public function test_the_ownership_query_never_applies_the_client_rule_to_users(): void
    {
        $sql = Invoice::ownedBy($this->student())->toSql();

        // Quoting differs between drivers, so match either.
        $this->assertMatchesRegularExpression('/from ["`]clients["`]/', $sql,
            'the client branch must query the clients table');
        $this->assertDoesNotMatchRegularExpression('/from ["`]users["`]/', $sql,
            'user_id does not exist on users, MySQL rejects this, SQLite hides it');
    }
}
