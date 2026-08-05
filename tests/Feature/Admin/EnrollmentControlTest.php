<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Events\Learning\EnrollmentCreated;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Learning\EnrollmentAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Everything an administrator can do to an enrollment.
 *
 * The rule these tests defend is that access and billing stay separate facts.
 * Granting access must not pretend an invoice is paid, and removing access
 * must not quietly erase a debt, either would lose real money.
 */
class EnrollmentControlTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student', 'is_student' => true]);
    }

    private function paidCourse(): Course
    {
        return Course::factory()->create(['is_published' => true, 'price' => '150000.00', 'currency' => 'UGX']);
    }

    private function enrollment(string $status = 'pending', ?Course $course = null, ?User $student = null): Enrollment
    {
        return Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => ($student ?? $this->student())->id,
            'course_id' => ($course ?? $this->paidCourse())->id,
            'status' => $status,
            'source' => 'self',
            'enrolled_at' => $status === 'pending' ? null : now(),
        ]);
    }

    // The list

    public function test_the_list_links_to_the_full_record(): void
    {
        $e = $this->enrollment();

        // The drill-down existed but nothing on the list pointed at it, so all
        // of its controls were unreachable.
        $this->actingAs($this->admin())->get(route('admin.enrollments.index'))->assertOk()
            ->assertSee(route('admin.enrollments.show', $e), false);
    }

    public function test_each_status_gets_its_own_badge(): void
    {
        $course = $this->paidCourse();
        $this->enrollment('pending', $course);
        $this->enrollment('active', $course);
        $this->enrollment('cancelled', $course);

        // Every row used to render badge-active regardless of status, so a
        // cancelled enrollment looked identical to a live one.
        $html = (string) $this->actingAs($this->admin())
            ->get(route('admin.enrollments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('badge-pending', $html);
        $this->assertStringContainsString('badge-active', $html);
        $this->assertStringContainsString('badge-danger', $html);
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $wanted = $this->student();
        $wanted->update(['name' => 'Aisha Nakalema', 'email' => 'aisha@example.com']);
        $other = $this->student();
        $other->update(['name' => 'Tudeeka Kasujja', 'email' => 'tudeeka@example.com']);

        $course = $this->paidCourse();
        $hers = $this->enrollment('pending', $course, $wanted);
        $theirs = $this->enrollment('active', $course, $other);

        $admin = $this->admin();

        // Asserted on each row's own URL rather than on the student's name:
        // every student's name also appears in the "enroll a student" dropdown,
        // so a name-based assertion passes or fails for the wrong reason.
        $rowOf = fn ($enrollment) => route('admin.enrollments.show', $enrollment);

        $this->actingAs($admin)->get(route('admin.enrollments.index', ['q' => 'aisha']))->assertOk()
            ->assertSee($rowOf($hers), false)->assertDontSee($rowOf($theirs), false);

        // By email too, since whoever is searching has whichever they were given.
        $this->actingAs($admin)->get(route('admin.enrollments.index', ['q' => 'tudeeka@example.com']))->assertOk()
            ->assertSee($rowOf($theirs), false)->assertDontSee($rowOf($hers), false);

        $this->actingAs($admin)->get(route('admin.enrollments.index', ['status' => 'active']))->assertOk()
            ->assertSee($rowOf($theirs), false)->assertDontSee($rowOf($hers), false);
    }

    public function test_the_unpaid_filter_finds_enrollments_with_money_outstanding(): void
    {
        $student = $this->student();
        $course = $this->paidCourse();

        $this->actingAs($student)->post(route('courses.enroll', $course));
        $owing = Enrollment::firstOrFail();

        $settled = $this->enrollment('active', $course, $this->student());

        $this->actingAs($this->admin())->get(route('admin.enrollments.index', ['billing' => 'unpaid']))->assertOk()
            ->assertSee(route('admin.enrollments.show', $owing), false)
            ->assertDontSee(route('admin.enrollments.show', $settled), false);
    }

    // Status control

    public function test_an_admin_can_grant_access_without_a_payment(): void
    {
        $e = $this->enrollment('pending');

        $this->actingAs($this->admin())
            ->patch(route('admin.enrollments.update', $e), ['status' => 'active'])
            ->assertSessionHas('success');

        $e->refresh();
        $this->assertSame('active', $e->status);
        $this->assertNotNull($e->enrolled_at, 'activating must record when access began');

        // A comped seat is legitimate; pretending it was paid for is not.
        $this->actingAs($e->user)->get(route('learn.course', $e->course))->assertOk();
    }

    public function test_granting_access_says_plainly_that_the_invoice_is_still_unpaid(): void
    {
        $student = $this->student();
        $course = $this->paidCourse();
        $this->actingAs($student)->post(route('courses.enroll', $course));
        $e = Enrollment::firstOrFail();
        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('admin.enrollments.update', $e), ['status' => 'active']);

        // The debt survives the override, and the message says so rather than
        // letting anyone assume the money was handled.
        $this->assertTrue($invoice->fresh()->isOutstanding());
        $this->assertStringContainsString('still unpaid', session('success'));
        $this->assertStringContainsString($invoice->invoice_no, session('success'));
    }

    public function test_the_welcome_event_fires_once_and_only_on_first_activation(): void
    {
        Event::fake([EnrollmentCreated::class]);
        $e = $this->enrollment('pending');
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.enrollments.update', $e), ['status' => 'active']);
        Event::assertDispatchedTimes(EnrollmentCreated::class, 1);

        // Suspend and restore: the student has already been welcomed, and
        // doing it again would mail them a second "you're enrolled".
        $this->actingAs($admin)->patch(route('admin.enrollments.update', $e), ['status' => 'cancelled']);
        $this->actingAs($admin)->patch(route('admin.enrollments.update', $e), ['status' => 'active']);
        Event::assertDispatchedTimes(EnrollmentCreated::class, 1);
    }

    public function test_cancelling_revokes_access_immediately(): void
    {
        $e = $this->enrollment('active');

        $this->actingAs($this->admin())->patch(route('admin.enrollments.update', $e), ['status' => 'cancelled']);

        $this->assertSame('cancelled', $e->fresh()->status);
        $this->actingAs($e->user)->get(route('learn.course', $e->course))->assertForbidden();
    }

    public function test_marking_completed_records_when(): void
    {
        $e = $this->enrollment('active');

        $this->actingAs($this->admin())->patch(route('admin.enrollments.update', $e), ['status' => 'completed']);

        $e->refresh();
        $this->assertSame('completed', $e->status);
        $this->assertNotNull($e->completed_at);
        $this->actingAs($e->user)->get(route('learn.course', $e->course))->assertOk();
    }

    public function test_sending_back_to_pending_withdraws_access_and_the_enrolment_date(): void
    {
        $e = $this->enrollment('active');

        $this->actingAs($this->admin())->patch(route('admin.enrollments.update', $e), ['status' => 'pending']);

        $e->refresh();
        $this->assertSame('pending', $e->status);
        $this->assertNull($e->enrolled_at, 'a pending enrollment has not started');
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $e = $this->enrollment('pending');

        $this->actingAs($this->admin())
            ->patch(route('admin.enrollments.update', $e), ['status' => 'vip'])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $e->fresh()->status);
    }

    // The access window

    public function test_an_admin_can_set_and_clear_the_expiry(): void
    {
        $e = $this->enrollment('active');
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.enrollments.update', $e), [
            'status' => 'active', 'expires_at' => '2027-01-31',
        ]);
        $this->assertSame('2027-01-31', $e->fresh()->expires_at?->format('Y-m-d'));

        $this->actingAs($admin)->patch(route('admin.enrollments.update', $e), [
            'status' => 'active', 'expires_at' => '2027-01-31', 'clear_expiry' => '1',
        ]);
        // Lifetime access must win over the date still sitting in the field.
        $this->assertNull($e->fresh()->expires_at);
    }

    public function test_an_expired_window_locks_the_course(): void
    {
        $e = $this->enrollment('active');

        $this->actingAs($this->admin())->patch(route('admin.enrollments.update', $e), [
            'status' => 'active', 'expires_at' => now()->subDay()->format('Y-m-d'),
        ]);

        $this->actingAs($e->user)->get(route('learn.course', $e->course))->assertForbidden();
    }

    // Billing control

    public function test_an_admin_can_raise_an_invoice_for_a_hand_added_student(): void
    {
        $e = $this->enrollment('pending');
        $this->assertNull($e->invoice_id);

        $this->actingAs($this->admin())
            ->post(route('admin.enrollments.invoice', $e))
            ->assertSessionHas('success');

        $invoice = Invoice::firstOrFail();
        $this->assertSame($invoice->id, $e->fresh()->invoice_id);
        $this->assertSame('150000.00', (string) $invoice->balance);
    }

    public function test_the_payment_link_is_shown_and_actually_works_for_the_student(): void
    {
        $e = $this->enrollment('pending');
        $this->actingAs($this->admin())->post(route('admin.enrollments.invoice', $e));
        $invoice = Invoice::firstOrFail();

        // The link on the row is the one the student can really use.
        $this->actingAs($this->admin())->get(route('admin.enrollments.index'))->assertOk()
            ->assertSee(route('payments.show', $invoice), false);

        $this->actingAs($e->user)->get(route('payments.show', $invoice))->assertOk()
            ->assertSee('Complete your payment');
    }

    public function test_a_free_course_is_never_invoiced(): void
    {
        $free = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $e = $this->enrollment('pending', $free);

        $this->actingAs($this->admin())->post(route('admin.enrollments.invoice', $e))
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_second_invoice_is_refused_while_the_first_is_still_payable(): void
    {
        $e = $this->enrollment('pending');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.enrollments.invoice', $e));
        $this->actingAs($admin)->post(route('admin.enrollments.invoice', $e))->assertSessionHas('error');

        // Two live invoices for one seat is how a student gets billed twice.
        $this->assertSame(1, Invoice::count());
    }

    public function test_paying_the_invoice_activates_the_enrollment_by_the_normal_path(): void
    {
        $e = $this->enrollment('pending');
        $this->actingAs($this->admin())->post(route('admin.enrollments.invoice', $e));
        $invoice = Invoice::firstOrFail();

        app(BillingService::class)->recordPayment($invoice, PaymentMethod::Cash, '150000.00');

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('active', $e->fresh()->status);
    }

    // Removal

    public function test_removing_an_enrollment_leaves_the_debt_alone(): void
    {
        $student = $this->student();
        $course = $this->paidCourse();
        $this->actingAs($student)->post(route('courses.enroll', $course));
        $e = Enrollment::firstOrFail();
        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->admin())->delete(route('admin.enrollments.destroy', $e))
            ->assertSessionHas('success');

        $this->assertSame(0, Enrollment::count());
        // Deleting access must not wipe out money owed.
        $this->assertTrue($invoice->fresh()->isOutstanding());
        $this->assertStringContainsString('invoice is untouched', session('success'));
    }

    // Who is allowed

    public function test_a_student_cannot_reach_any_of_this(): void
    {
        $e = $this->enrollment('pending');
        $student = $this->student();

        // The admin gate bounces rather than 403s ("Staff credentials
        // required"), so what matters is that none of it takes effect.
        $this->actingAs($student)->get(route('admin.enrollments.index'))->assertRedirect();
        $this->actingAs($student)->patch(route('admin.enrollments.update', $e), ['status' => 'active'])->assertRedirect();
        $this->actingAs($student)->post(route('admin.enrollments.invoice', $e))->assertRedirect();
        $this->actingAs($student)->delete(route('admin.enrollments.destroy', $e))->assertRedirect();

        $this->assertSame('pending', $e->fresh()->status);
        $this->assertSame(0, Invoice::count());
        $this->assertSame(1, Enrollment::count());
    }

    // The service's own guard

    public function test_the_service_refuses_a_status_it_does_not_know(): void
    {
        $this->expectException(\RuntimeException::class);

        app(EnrollmentAdminService::class)->setStatus($this->enrollment('pending'), 'promoted');
    }
}
