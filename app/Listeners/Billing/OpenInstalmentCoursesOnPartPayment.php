<?php

namespace App\Listeners\Billing;

use App\Events\Billing\InvoicePartPaid;
use App\Events\Learning\EnrollmentCreated;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\Catalog\InstalmentPlan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * The first instalment opens the course.
 *
 * ActivateCourseEnrollmentsOnInvoicePaid is the same last mile for a course
 * bought outright, and the two are deliberately separate rather than one
 * listener with a branch: that one runs once, when nothing is owed, and this
 * one runs on every part payment and must decide each time whether anything
 * has actually changed.
 *
 * Only courses sold in instalments are opened here. A part payment against a
 * course bought outright buys nothing yet, and must not: letting a shilling
 * unlock a course would make the instalment feature a way around the price.
 *
 * Idempotent. A webhook and a browser callback settling the same instalment
 * both land here, and an already-active enrollment is left alone.
 */
class OpenInstalmentCoursesOnPartPayment implements ShouldQueue
{
    public function handle(InvoicePartPaid $event): void
    {
        $invoice = $event->invoice;

        if ($invoice->billable_type !== User::class) {
            return;
        }

        foreach ($invoice->items()->where('source_type', Course::class)->get() as $item) {
            $course = Course::find($item->source_id);

            if ($course === null || ! $course->allowsInstalments()) {
                continue;
            }

            // Not one shilling: a whole part, or the course stays shut.
            $covered = InstalmentPlan::partsCoveredBy(
                (string) $invoice->amount_paid,
                (string) $invoice->total,
            );

            if ($covered < 1) {
                continue;
            }

            $enrollment = Enrollment::firstOrCreate(
                ['user_id' => $invoice->billable_id, 'course_id' => $course->id],
                ['uuid' => (string) Str::uuid(), 'source' => 'self', 'status' => 'pending'],
            );

            if (in_array($enrollment->status, ['active', 'completed'], true)) {
                // Already in. A later instalment widens what they can reach,
                // which is read from the invoice and needs nothing written here.
                continue;
            }

            $enrollment->update([
                'status' => 'active',
                'invoice_id' => $invoice->id,
                'enrolled_at' => $enrollment->enrolled_at ?? now(),
                'expires_at' => $course->enrollmentExpiresAt(),
            ]);

            EnrollmentCreated::dispatch($enrollment->fresh());
        }
    }
}
