<?php

namespace App\Events\Billing;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/** §7.1 — fired by BillingService::recordPayment() the moment an invoice's balance reaches zero. */
class InvoicePaid
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
