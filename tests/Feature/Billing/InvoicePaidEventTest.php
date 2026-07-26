<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Events\Billing\InvoicePaid;
use App\Models\Client;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** §7.1 — InvoicePaid fires exactly when an invoice's balance reaches zero, not on a partial payment. */
class InvoicePaidEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_the_full_balance_dispatches_invoice_paid(): void
    {
        Event::fake([InvoicePaid::class]);
        $client = Client::factory()->create();
        $billing = app(BillingService::class);
        $invoice = $billing->generateInvoice($client, [['description' => 'Work', 'unit_price' => '100.00']]);

        $billing->recordPayment($invoice, PaymentMethod::Cash, '100.00');

        Event::assertDispatched(InvoicePaid::class, fn ($event) => $event->invoice->id === $invoice->id);
    }

    public function test_a_partial_payment_does_not_dispatch_invoice_paid(): void
    {
        Event::fake([InvoicePaid::class]);
        $client = Client::factory()->create();
        $billing = app(BillingService::class);
        $invoice = $billing->generateInvoice($client, [['description' => 'Work', 'unit_price' => '100.00']]);

        $billing->recordPayment($invoice, PaymentMethod::Cash, '40.00');

        Event::assertNotDispatched(InvoicePaid::class);
    }
}
