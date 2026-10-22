<?php

namespace App\Events\Billing;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by BillingService::recordPayment() when money lands on an invoice that
 * still has a balance.
 *
 * InvoicePaid is the end of a sale. This is the middle of one, and it exists
 * because a course bought in instalments has to open at the first payment
 * rather than the last. Anything that must only ever happen once, at the end,
 * belongs on InvoicePaid: this fires again on every instalment.
 */
class InvoicePartPaid
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
