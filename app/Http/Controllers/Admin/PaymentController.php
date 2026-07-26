<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Payments against an invoice. recordPayment (transaction, invoice lock,
 * overpayment guard) lives in BillingService; the controller authorizes and
 * turns errors into flashes.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function store(PaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        try {
            $this->billing->recordPayment(
                $invoice,
                $request->paymentMethod(),
                $request->amountString(),
                ['reference' => $request->validated('reference')],
                $request->user()->id,
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment recorded.');
    }

    public function receipt(Payment $payment)
    {
        $this->authorize('view', $payment->invoice);

        $payment->load(['invoice.billable', 'receivedBy']);

        return Pdf::loadView('pdf.receipt', ['payment' => $payment])
            ->download("receipt-{$payment->invoice->invoice_no}.pdf");
    }
}
