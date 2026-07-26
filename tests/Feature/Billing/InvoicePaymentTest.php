<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Exceptions\OverpaymentException;
use App\Models\Client;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_an_invoice_computes_totals_from_line_items(): void
    {
        $client = Client::factory()->create();

        $invoice = app(BillingService::class)->generateInvoice(
            billable: $client,
            items: [
                ['description' => 'Design', 'quantity' => 1, 'unit_price' => '500000.00'],
                ['description' => 'Development', 'quantity' => 2, 'unit_price' => '250000.00'],
            ],
            discount: '100000.00',
        );

        $this->assertSame('1000000.00', $invoice->subtotal);
        $this->assertSame('900000.00', $invoice->total);
        $this->assertSame('900000.00', $invoice->balance);
        $this->assertSame(2, $invoice->items()->count());
        $this->assertSame('issued', $invoice->status->value);
    }

    public function test_recording_a_partial_payment_updates_balance_and_status(): void
    {
        $client = Client::factory()->create();
        $invoice = app(BillingService::class)->generateInvoice($client, [
            ['description' => 'Work', 'quantity' => 1, 'unit_price' => '1000000.00'],
        ]);

        $payment = app(BillingService::class)->recordPayment($invoice, PaymentMethod::Cash, '400000.00');

        $this->assertSame('400000.00', $payment->amount);
        $this->assertSame('600000.00', $invoice->fresh()->balance);
        $this->assertSame('partially_paid', $invoice->fresh()->status->value);
    }

    public function test_paying_the_full_balance_marks_the_invoice_paid(): void
    {
        $client = Client::factory()->create();
        $invoice = app(BillingService::class)->generateInvoice($client, [
            ['description' => 'Work', 'quantity' => 1, 'unit_price' => '500000.00'],
        ]);

        app(BillingService::class)->recordPayment($invoice, PaymentMethod::Cash, '500000.00');

        $this->assertSame('paid', $invoice->fresh()->status->value);
        $this->assertSame('0.00', $invoice->fresh()->balance);
    }

    public function test_overpaying_an_invoice_is_rejected(): void
    {
        $client = Client::factory()->create();
        $invoice = app(BillingService::class)->generateInvoice($client, [
            ['description' => 'Work', 'quantity' => 1, 'unit_price' => '100000.00'],
        ]);

        $this->expectException(OverpaymentException::class);

        app(BillingService::class)->recordPayment($invoice, PaymentMethod::Cash, '200000.00');
    }

    public function test_refunding_a_paid_invoice_marks_it_refunded_and_records_who(): void
    {
        $client = Client::factory()->create();
        $admin = \App\Models\User::factory()->create();
        $billing = app(BillingService::class);
        $invoice = $billing->generateInvoice($client, [['description' => 'Work', 'unit_price' => '100.00']]);
        $billing->recordPayment($invoice, PaymentMethod::Cash, '100.00');

        $refunded = $billing->refund($invoice, $admin->id);

        $this->assertSame('refunded', $refunded->status->value);
        $this->assertSame($admin->id, $refunded->refunded_by);
        $this->assertNotNull($refunded->refunded_at);
        $this->assertEquals('100.00', (string) $refunded->amount_paid, 'the historical payment record is preserved, not zeroed');
    }

    public function test_a_partially_paid_invoice_can_also_be_refunded(): void
    {
        $client = Client::factory()->create();
        $billing = app(BillingService::class);
        $invoice = $billing->generateInvoice($client, [['description' => 'Work', 'unit_price' => '100.00']]);
        $billing->recordPayment($invoice, PaymentMethod::Cash, '40.00');

        $refunded = $billing->refund($invoice);

        $this->assertSame('refunded', $refunded->status->value);
    }

    public function test_refunding_an_invoice_with_nothing_paid_is_rejected(): void
    {
        $client = Client::factory()->create();
        $invoice = app(BillingService::class)->generateInvoice($client, [['description' => 'Work', 'unit_price' => '100.00']]);

        $this->expectException(\RuntimeException::class);
        app(BillingService::class)->refund($invoice);
    }

    public function test_an_already_refunded_invoice_cannot_be_refunded_again(): void
    {
        $client = Client::factory()->create();
        $billing = app(BillingService::class);
        $invoice = $billing->generateInvoice($client, [['description' => 'Work', 'unit_price' => '100.00']]);
        $billing->recordPayment($invoice, PaymentMethod::Cash, '100.00');
        $billing->refund($invoice);

        $this->expectException(\RuntimeException::class);
        $billing->refund($invoice->fresh());
    }
}
