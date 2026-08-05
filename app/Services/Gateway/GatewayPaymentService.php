<?php

namespace App\Services\Gateway;

use App\Enums\PaymentMethod;
use App\Models\GatewayLog;
use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates gateway payments on top of a PaymentGateway adapter. initialize()
 * opens a hosted session and logs it; settle() is the ONE place money moves, it
 * verifies server-side, then records a Payment through BillingService. It is
 * idempotent (a gateway_log row is locked and marked successful once), so the
 * redirect callback and the webhook can both call it without double-crediting.
 */
class GatewayPaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly BillingService $billing,
    ) {}

    /** @param array<string,string|null> $customer @return array{0:GatewayLog,1:GatewayCharge} */
    public function initialize(Invoice $invoice, string $amount, string $redirectUrl, array $customer): array
    {
        $txRef = 'MM-'.$invoice->uuid.'-'.bin2hex(random_bytes(5));

        $log = GatewayLog::create([
            'invoice_id' => $invoice->id,
            'provider' => 'flutterwave',
            'tx_ref' => $txRef,
            'status' => 'pending',
            'amount' => $amount,
            'currency' => $invoice->currency,
        ]);

        $charge = $this->gateway->initialize([
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => $invoice->currency,
            'redirect_url' => $redirectUrl,
            'customer' => $customer,
            'meta' => ['invoice' => $invoice->uuid],
        ]);

        if (! $charge->ok) {
            $log->update(['status' => 'failed', 'meta' => ['error' => $charge->error]]);
        }

        return [$log, $charge];
    }

    /**
     * Recover a payment whose callback never arrived.
     *
     * The browser coming back from Flutterwave is the usual trigger for
     * settlement, and it is the least reliable part of the whole flow: the
     * payer closes the tab, the network drops, the phone dies mid-redirect.
     * The money is taken and the site never hears about it. The webhook covers
     * most of that, but only if it is configured and reachable.
     *
     * This asks the gateway about every session we opened for the invoice,
     * keyed on the tx_ref we generated, so a stuck payment can be recovered on
     * demand, by the payer pressing "I have paid" or by staff. It settles
     * through exactly the same verified, idempotent path as everything else.
     *
     * @return string One of: settled, already_settled, nothing_pending, not_successful
     */
    public function reconcile(Invoice $invoice): string
    {
        $pending = GatewayLog::where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->latest('id')
            ->get();

        if ($pending->isEmpty()) {
            return 'nothing_pending';
        }

        $outcome = 'not_successful';

        foreach ($pending as $log) {
            $v = $this->gateway->verifyByReference((string) $log->tx_ref);

            if (! $v->successful || $v->reference === '') {
                continue;
            }

            // settle() is the only place money moves. Going through it keeps
            // the currency, amount and idempotency checks in one place.
            $result = $v->providerRef !== null && $v->providerRef !== ''
                ? $this->settle($v->providerRef)
                : 'not_successful';

            if (in_array($result, ['settled', 'already_settled'], true)) {
                $outcome = $result;
                break;
            }
        }

        return $outcome;
    }

    /**
     * Verify a gateway transaction and, if genuinely successful, record the
     * payment exactly once. Safe to call from both the browser callback and the
     * webhook. Returns a short status string for logging.
     */
    public function settle(string $providerTransactionId): string
    {
        $v = $this->gateway->verify($providerTransactionId);
        if (! $v->successful || $v->reference === '') {
            return 'not_successful';
        }

        return DB::transaction(function () use ($v) {
            /** @var GatewayLog|null $log */
            $log = GatewayLog::where('tx_ref', $v->reference)->lockForUpdate()->first();
            if ($log === null) {
                return 'unknown_reference';
            }
            if ($log->isSettled()) {
                return 'already_settled';   // idempotent, webhook + callback race
            }

            if (strtoupper($v->currency) !== strtoupper($log->currency)) {
                $log->update(['status' => 'failed', 'verified' => true, 'meta' => ['reason' => 'currency_mismatch']]);

                return 'currency_mismatch';
            }
            if (bccomp($v->amount, (string) $log->amount, 2) < 0) {
                $log->update(['status' => 'failed', 'verified' => true, 'meta' => ['reason' => 'amount_short', 'paid' => $v->amount]]);

                return 'amount_short';
            }

            $paymentId = null;

            if ($log->invoice_id !== null) {
                $invoice = Invoice::find($log->invoice_id);
                if ($invoice === null) {
                    $log->update(['status' => 'failed', 'verified' => true, 'meta' => ['reason' => 'no_invoice']]);

                    return 'no_invoice';
                }
                if ($invoice->status->isPayable()) {
                    // Cap at the outstanding balance (a concurrent payment may have reduced it).
                    $toRecord = bccomp((string) $log->amount, (string) $invoice->balance, 2) > 0
                        ? (string) $invoice->balance
                        : (string) $log->amount;

                    if (bccomp($toRecord, '0', 2) > 0) {
                        $payment = $this->billing->recordPayment($invoice, PaymentMethod::Flutterwave, $toRecord, ['reference' => $log->tx_ref]);
                        $paymentId = $payment->id;
                    }
                }
            }

            $log->update([
                'status' => 'successful',
                'verified' => true,
                'provider_ref' => $v->providerRef,
                'payment_type' => $v->paymentType,
                'payment_id' => $paymentId,
            ]);

            return 'settled';
        });
    }
}
