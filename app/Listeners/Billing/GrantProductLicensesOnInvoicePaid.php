<?php

namespace App\Listeners\Billing;

use App\Events\Billing\InvoicePaid;
use App\Models\Product;
use App\Models\ProductLicense;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The last mile of a shop purchase: an invoice reaching Paid turns every
 * product line on it into a licence, which is what the download route checks.
 *
 * Sits alongside the course listener rather than replacing it, so one invoice
 * containing a course and an e-book fulfils both halves. Idempotent by the
 * unique (user, product) index — the callback and the webhook both settle the
 * same invoice, and the second one must be a no-op rather than a duplicate.
 *
 * Runs on payment, never on redirect: a browser returning from the gateway
 * proves nothing until the server has verified the transaction.
 */
class GrantProductLicensesOnInvoicePaid implements ShouldQueue
{
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        if ($invoice->billable_type !== User::class) {
            return;
        }

        foreach ($invoice->items()->where('source_type', Product::class)->get() as $item) {
            $license = ProductLicense::firstOrCreate(
                ['user_id' => $invoice->billable_id, 'product_id' => $item->source_id],
                ['invoice_id' => $invoice->id, 'granted_at' => now()],
            );

            if ($license->wasRecentlyCreated) {
                Product::whereKey($item->source_id)->increment('purchases_count');
            }
        }
    }
}
