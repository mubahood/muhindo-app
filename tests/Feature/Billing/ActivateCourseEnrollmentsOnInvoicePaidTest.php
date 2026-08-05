<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Events\Learning\EnrollmentCreated;
use App\Models\Client;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/** A paid course invoice activates the matching enrollment(s); everything else is a no-op. */
class ActivateCourseEnrollmentsOnInvoicePaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_a_pending_enrollments_invoice_in_full_activates_it_and_dispatches_enrollment_created(): void
    {
        Event::fake([EnrollmentCreated::class]);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '50.00', 'currency' => 'UGX']);
        $billing = app(BillingService::class);
        $invoice = $billing->generateCourseInvoice($student, $course);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'invoice_id' => $invoice->id, 'status' => 'pending', 'source' => 'self',
        ]);

        $billing->recordPayment($invoice, PaymentMethod::Cash, '50.00');

        $enrollment->refresh();
        $this->assertSame('active', $enrollment->status);
        $this->assertNotNull($enrollment->enrolled_at);
        Event::assertDispatched(EnrollmentCreated::class, fn ($event) => $event->enrollment->id === $enrollment->id);
    }

    public function test_it_creates_the_enrollment_if_none_existed_yet(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '50.00', 'currency' => 'UGX']);
        $billing = app(BillingService::class);
        $invoice = $billing->generateCourseInvoice($student, $course);

        // No enrollment row created up front, e.g. an admin raised the invoice directly.
        $billing->recordPayment($invoice, PaymentMethod::Cash, '50.00');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active',
        ]);
    }

    public function test_settling_the_same_invoice_twice_does_not_double_enroll_or_double_dispatch(): void
    {
        Event::fake([EnrollmentCreated::class]);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '50.00', 'currency' => 'UGX']);
        $billing = app(BillingService::class);
        $invoice = $billing->generateCourseInvoice($student, $course);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'invoice_id' => $invoice->id, 'status' => 'pending', 'source' => 'self',
        ]);

        $billing->recordPayment($invoice, PaymentMethod::Cash, '50.00');

        // Manually re-fire the listener against the same (now Paid) invoice to simulate a race,
        // BillingService itself would refuse a second recordPayment() on a Paid invoice, but the
        // listener's own idempotency is what's under test here.
        app(\App\Listeners\Billing\ActivateCourseEnrollmentsOnInvoicePaid::class)
            ->handle(new \App\Events\Billing\InvoicePaid($invoice->fresh()));

        $this->assertSame(1, Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->count());
        Event::assertDispatchedTimes(EnrollmentCreated::class, 1);
    }

    public function test_a_client_project_invoice_never_touches_enrollments(): void
    {
        $client = Client::factory()->create();
        $billing = app(BillingService::class);
        $invoice = $billing->generateInvoice($client, [['description' => 'Work', 'unit_price' => '100.00']]);

        $billing->recordPayment($invoice, PaymentMethod::Cash, '100.00');

        $this->assertSame(0, Enrollment::count());
    }

    public function test_a_course_invoice_with_no_course_line_item_is_a_no_op(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $billing = app(BillingService::class);
        $invoice = $billing->generateInvoice($student, [['description' => 'Consulting', 'unit_price' => '20.00']]);

        $billing->recordPayment($invoice, PaymentMethod::Cash, '20.00');

        $this->assertSame(0, Enrollment::count());
    }
}
