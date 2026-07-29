<?php

namespace Tests\Feature\Billing;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Gateway\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/** §7.1 — enroll() on a priced course routes through checkout instead of enrolling directly. */
class CourseCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test: courses.show() originally treated ANY existing enrollment row (including
     * a pending, unpaid one) as "enrolled," rendering "Continue learning" — which routes into
     * EnrollmentPolicy::access() and 404s/403s since pending isn't active/completed. Introducing
     * self-serve paid checkout made this reachable for the first time; fixed by only extending
     * $enrollment to active/completed and showing a distinct "Complete checkout" state for pending.
     */
    public function test_the_course_page_shows_complete_checkout_not_continue_learning_while_pending(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75.00', 'currency' => 'UGX']);
        $this->actingAs($student)->post(route('courses.enroll', $course));

        $this->get(route('courses.show', $course))
            ->assertOk()->assertSee('Complete checkout')->assertDontSee('Continue learning');
    }

    public function test_enrolling_in_a_paid_course_creates_a_pending_enrollment_and_invoice_then_redirects_to_checkout(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75.00', 'currency' => 'UGX']);

        $this->actingAs($student)->post(route('courses.enroll', $course))
            ->assertRedirect(route('courses.checkout', $course));

        $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first();
        $this->assertNotNull($enrollment);
        $this->assertSame('pending', $enrollment->status);
        $this->assertNotNull($enrollment->invoice_id);

        $invoice = Invoice::find($enrollment->invoice_id);
        $this->assertEquals('75.00', (string) $invoice->total);
        $this->assertSame(User::class, $invoice->billable_type);
        $this->assertSame($student->id, $invoice->billable_id);
    }

    public function test_enrolling_again_while_pending_reuses_the_same_invoice(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75.00', 'currency' => 'UGX']);
        $this->actingAs($student);

        $this->post(route('courses.enroll', $course));
        $firstInvoiceId = Enrollment::first()->invoice_id;

        $this->post(route('courses.enroll', $course))->assertRedirect(route('courses.checkout', $course));

        $this->assertSame(1, Enrollment::count());
        $this->assertSame(1, Invoice::count());
        $this->assertSame($firstInvoiceId, Enrollment::first()->invoice_id);
    }

    public function test_the_checkout_page_shows_the_invoice_and_a_pay_button(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75.00', 'currency' => 'UGX']);
        $this->actingAs($student)->post(route('courses.enroll', $course));

        // The pay button names the amount, so nobody is one click from a charge
        // whose size they have to scroll up to confirm.
        $this->get(route('courses.checkout', $course))
            ->assertOk()->assertSee($course->title)->assertSee('Pay UGX 75.00 with Flutterwave');
    }

    public function test_the_checkout_page_redirects_away_with_no_pending_invoice(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75.00', 'currency' => 'UGX']);

        $this->actingAs($student)->get(route('courses.checkout', $course))
            ->assertRedirect(route('courses.show', $course));
    }

    public function test_an_already_active_enrollment_short_circuits_to_already_enrolled(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75.00', 'currency' => 'UGX']);
        Enrollment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($student)->post(route('courses.enroll', $course))
            ->assertRedirect(route('learn.course', $course));

        $this->assertSame(0, Invoice::count(), 'no invoice should be created for an already-active enrollment');
    }

    public function test_paying_via_flutterwave_end_to_end_activates_the_enrollment(): void
    {
        $fake = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $fake);

        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '75.00', 'currency' => 'UGX']);
        $this->actingAs($student)->post(route('courses.enroll', $course));

        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $invoice = Invoice::find($enrollment->invoice_id);

        $this->post(route('portal.invoice.pay', $invoice))->assertRedirect();

        $txRef = \App\Models\GatewayLog::where('invoice_id', $invoice->id)->value('tx_ref');
        $fake->succeedNext($txRef, '75.00', 'UGX');

        $this->postJson(route('gateway.webhook'), ['data' => ['id' => $txRef]])->assertOk();

        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status->value);
    }
}
