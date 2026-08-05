<?php

namespace App\Listeners\Billing;

use App\Events\Billing\InvoicePaid;
use App\Events\Learning\EnrollmentCreated;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * The "last mile" of course checkout: an invoice reaching Paid (via Flutterwave
 * or an admin-recorded cash/bank payment, recordPayment() is the one shared chokepoint,
 * so both paths activate access identically) turns every course line item on it into an
 * active enrollment. Idempotent: re-running against an already-active enrollment is a no-op,
 * so a webhook/callback race settling the same invoice twice never double-enrolls or
 * double-notifies.
 */
class ActivateCourseEnrollmentsOnInvoicePaid implements ShouldQueue
{
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        if ($invoice->billable_type !== User::class) {
            return;
        }

        foreach ($invoice->items()->where('source_type', Course::class)->get() as $item) {
            $enrollment = Enrollment::firstOrCreate(
                ['user_id' => $invoice->billable_id, 'course_id' => $item->source_id],
                ['uuid' => (string) Str::uuid(), 'source' => 'self', 'status' => 'pending'],
            );

            if (in_array($enrollment->status, ['active', 'completed'], true)) {
                continue;
            }

            $course = Course::find($item->source_id);

            $enrollment->update([
                'status' => 'active',
                'invoice_id' => $invoice->id,
                'enrolled_at' => $enrollment->enrolled_at ?? now(),
                'expires_at' => $course?->enrollmentExpiresAt(),
            ]);

            EnrollmentCreated::dispatch($enrollment->fresh());
        }
    }
}
