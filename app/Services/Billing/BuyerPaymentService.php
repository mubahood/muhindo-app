<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Enrollment;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * What a buyer is allowed to do with their own unpaid invoice, in one place.
 *
 * Everything here is deliberately blunt about one thing: neither action moves
 * money and neither grants access. Access is granted exactly one way in this
 * application — a Payment clears the balance, InvoicePaid fires, and its
 * listeners activate enrollments and grant product licences. Declaring an
 * intention to pay in cash must not become a second, quieter door into the
 * same place, or "I'll pay you directly" is just a way to take the course free.
 *
 * Ownership is NOT re-checked here. InvoicePolicy already knows that a course
 * invoice is billable to a User while a project invoice is billable to a
 * Client whose user_id is the buyer; a second copy of that rule in this class
 * would be a second chance to get it wrong. Callers authorise first.
 */
class BuyerPaymentService
{
    /**
     * "I'll pay Mr. Muhindo Mubaraka directly."
     *
     * Marks the arrangement and lets them leave. The invoice stays Issued and
     * fully payable online, so changing their mind later costs nothing.
     */
    public function payDirectly(Invoice $invoice): Invoice
    {
        $this->assertOutstanding($invoice);

        // Idempotent: a double-submit must not restart the clock that decides
        // how overdue this looks to whoever ends up chasing it.
        if ($invoice->direct_payment_at === null) {
            $invoice->forceFill(['direct_payment_at' => now()])->save();
        }

        return $invoice->refresh();
    }

    /** Undo the arrangement — for when they come back and pay online after all. */
    public function cancelDirectArrangement(Invoice $invoice): Invoice
    {
        $this->assertOutstanding($invoice);

        $invoice->forceFill(['direct_payment_at' => null])->save();

        return $invoice->refresh();
    }

    /**
     * Cancel an unpaid order.
     *
     * Voids the invoice and releases anything being held for it. A part-paid
     * invoice is refused rather than voided: money has already changed hands,
     * so it needs a refund decision by a person, not a self-service button
     * that would silently strand the amount already paid.
     */
    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Void) {
            return $invoice;   // idempotent — a double-submitted cancel is not an error
        }

        if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Refunded], true)) {
            throw new RuntimeException('This order is already paid. Ask Muhindo about a refund instead.');
        }

        if (bccomp((string) $invoice->amount_paid, '0', 2) > 0) {
            throw new RuntimeException(
                'Part of this order has already been paid, so it cannot be cancelled here. Message Muhindo and he will sort it out.'
            );
        }

        return DB::transaction(function () use ($invoice) {
            /** @var Invoice $locked */
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            // Re-check under the lock. A gateway callback can settle this
            // invoice between the button being rendered and this running, and
            // voiding a just-paid invoice would take away what they bought.
            if (! $locked->status->isPayable() || bccomp((string) $locked->amount_paid, '0', 2) > 0) {
                throw new RuntimeException('This order can no longer be cancelled — it has just been paid.');
            }

            $locked->forceFill([
                'status' => InvoiceStatus::Void,
                'cancelled_at' => now(),
                'direct_payment_at' => null,
                'balance' => '0.00',
            ])->save();

            // A pending enrollment exists only to hold a seat against this
            // invoice. Left behind, it would block buying the course again:
            // enroll() treats a pending enrollment that has an invoice as
            // "just go and pay it" — and that invoice is now void.
            Enrollment::where('invoice_id', $locked->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'invoice_id' => null]);

            return $locked;
        });
    }

    private function assertOutstanding(Invoice $invoice): void
    {
        if (! $invoice->isOutstanding()) {
            throw new RuntimeException('This order has nothing outstanding to pay.');
        }
    }
}
