<?php

namespace App\Services\Learning;

use App\Events\Learning\EnrollmentCreated;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Every change an administrator can make to an enrollment, in one place.
 *
 * These transitions are written here rather than in the controller and the
 * Livewire drill-down separately, because those two already disagreed about
 * what "cancel" means once, and a status column that different screens set
 * differently is how a student ends up with a course they cannot open and an
 * invoice nobody is chasing.
 *
 * The rules that matter:
 *
 *  - Activating is an override, not a payment. It grants access without a
 *    penny changing hands, which is exactly what a scholarship or a
 *    comped seat needs, so it is allowed, it is logged by the model's
 *    activity log, and the caller is told when it leaves an invoice unpaid
 *    rather than being allowed to assume the money is handled.
 *  - Nothing here silently deletes an invoice. Access and billing are
 *    separate facts about a student, and conflating them loses money.
 */
class EnrollmentAdminService
{
    /** The statuses an administrator may set, and what each one means. */
    public const STATUSES = [
        'pending' => 'Pending, waiting on payment, no access',
        'active' => 'Active, full access to the course',
        'completed' => 'Completed, finished the course',
        'cancelled' => 'Cancelled, access revoked',
    ];

    public function __construct(private readonly BillingService $billing) {}

    /**
     * Move an enrollment to a status, applying the side effects that status
     * implies. Returns a human-readable note about anything the administrator
     * should know as a result.
     */
    public function setStatus(Enrollment $enrollment, string $status, ?int $by = null): string
    {
        if (! array_key_exists($status, self::STATUSES)) {
            throw new RuntimeException("Unknown enrollment status: {$status}");
        }

        $was = $enrollment->status;

        if ($was === $status) {
            return 'Nothing changed. It was already '.$status.'.';
        }

        // Read before the update: enrolled_at is the only record of whether
        // this student has ever had access, and setting it is part of the
        // very change we are about to make.
        $firstActivation = $enrollment->enrolled_at === null;

        return DB::transaction(function () use ($enrollment, $status, $was, $firstActivation) {
            $attributes = ['status' => $status];

            if ($status === 'active') {
                // enrolled_at is when access actually began. A pending row has
                // none, and progress reporting reads it as the start date.
                $attributes['enrolled_at'] = $enrollment->enrolled_at ?? now();
                $attributes['completed_at'] = null;

                if ($enrollment->expires_at === null) {
                    $attributes['expires_at'] = $enrollment->course?->enrollmentExpiresAt();
                }
            }

            if ($status === 'completed') {
                $attributes['enrolled_at'] = $enrollment->enrolled_at ?? now();
                $attributes['completed_at'] = $enrollment->completed_at ?? now();
            }

            if ($status === 'pending') {
                // Going back to pending withdraws access, so the enrolment date
                // is no longer true. The invoice stays exactly as it is.
                $attributes['enrolled_at'] = null;
                $attributes['completed_at'] = null;
            }

            $enrollment->update($attributes);

            // First time this student has ever had access, the welcome mail,
            // progress records and everything else hang off this event, and
            // it must not fire again on a later re-activation.
            if ($status === 'active' && $was === 'pending' && $firstActivation) {
                EnrollmentCreated::dispatch($enrollment->fresh());
            }

            return $this->noteFor($enrollment->fresh(), $status);
        });
    }

    /**
     * Raise an invoice for an enrollment that has none, so a student who was
     * added by hand can still be asked to pay.
     */
    public function createInvoice(Enrollment $enrollment, ?string $couponCode = null, ?int $by = null): Invoice
    {
        $course = $enrollment->course;

        if ($course === null) {
            throw new RuntimeException('This enrollment has no course to bill for.');
        }

        if ($course->isFree()) {
            throw new RuntimeException('This course is free. There is nothing to invoice.');
        }

        if ($enrollment->invoice_id !== null) {
            $existing = Invoice::find($enrollment->invoice_id);

            // Only refuse if the existing one is still live. A void or
            // cancelled invoice should not block raising a fresh one.
            if ($existing !== null && $existing->status->isPayable()) {
                throw new RuntimeException('This enrollment already has an unpaid invoice.');
            }
        }

        return DB::transaction(function () use ($enrollment, $course, $couponCode, $by) {
            $invoice = $this->billing->generateCourseInvoice($enrollment->user, $course, $couponCode, $by);

            $enrollment->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    /** Enrol a student by hand, from the admin list. */
    public function enrol(Course $course, int $userId, string $status = 'active'): Enrollment
    {
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'course_id' => $course->id,
            'status' => $status,
            'source' => 'admin',
            'enrolled_at' => $status === 'pending' ? null : now(),
            'expires_at' => $status === 'pending' ? null : $course->enrollmentExpiresAt(),
        ]);

        if ($status === 'active') {
            EnrollmentCreated::dispatch($enrollment);
        }

        return $enrollment;
    }

    /** What the administrator needs to know after the change. */
    private function noteFor(Enrollment $enrollment, string $status): string
    {
        $base = 'Enrollment is now '.$status.'.';

        if ($status !== 'active') {
            return $base;
        }

        $invoice = $enrollment->invoice_id ? Invoice::find($enrollment->invoice_id) : null;

        if ($invoice !== null && $invoice->isOutstanding()) {
            return $base.' Note that '.$invoice->invoice_no.' is still unpaid ('
                .$invoice->currency.' '.number_format((float) $invoice->balance, 2)
                .'). Access has been granted anyway.';
        }

        if ($invoice === null && $enrollment->course !== null && ! $enrollment->course->isFree()) {
            return $base.' This is a paid course with no invoice, so nothing is being billed.';
        }

        return $base;
    }
}
