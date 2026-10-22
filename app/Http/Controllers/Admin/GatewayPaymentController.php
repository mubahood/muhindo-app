<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\GatewayLog;
use App\Models\Invoice;
use App\Services\Gateway\GatewayPaymentService;
use App\Services\Gateway\PaymentGateway;
use App\Support\Catalog\InstalmentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Flutterwave payment flow. start() opens a hosted session and redirects the
 * payer to Flutterwave; callback() (browser return) and webhook()
 * (server-to-server) both funnel into the idempotent, verify-first
 * GatewayPaymentService::settle, money is only ever recorded after verification.
 */
class GatewayPaymentController extends Controller
{
    public function __construct(private readonly GatewayPaymentService $service) {}

    public function start(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        if (! $invoice->status->isPayable() || bccomp((string) $invoice->balance, '0', 2) <= 0) {
            return back()->with('error', 'This invoice has nothing outstanding to pay.');
        }

        $amount = $this->amountToCharge($request, $invoice);

        if ($amount === null) {
            return back()->with('error', 'That is not an amount this order can be paid in.');
        }

        $invoice->loadMissing('billable');
        $billable = $invoice->billable;

        [, $charge] = $this->service->initialize(
            $invoice,
            $amount,
            route('gateway.callback'),
            [
                'email' => $billable?->email ?: 'billing@'.parse_url(config('app.url'), PHP_URL_HOST),
                'name' => $billable?->name,
                'phone' => $billable?->phone,
            ],
        );

        if (! $charge->ok || $charge->link === null) {
            return back()->with('error', $charge->error ?? 'Could not start the online payment.');
        }

        return redirect()->away($charge->link);
    }

    /**
     * How much to charge: the whole balance, or one instalment.
     *
     * The caller may ask for a part, but it is never trusted as a number. A
     * posted "pay one quarter" is re-derived here from the invoice, so the
     * form cannot name its own price, and anything that is neither the next
     * instalment nor the full balance is refused rather than quietly rounded
     * into one of them.
     *
     * Returns null when the request asked for something this invoice cannot be
     * paid in.
     */
    private function amountToCharge(Request $request, Invoice $invoice): ?string
    {
        $balance = (string) $invoice->balance;

        if (! $request->boolean('instalment')) {
            return $balance;
        }

        $invoice->loadMissing('items');
        $context = InstalmentPlan::contextFor($invoice);

        if ($context === null) {
            return null;
        }

        $next = $context['next_amount'];

        if (bccomp($next, '0', 2) <= 0) {
            return null;
        }

        // The last instalment is the balance; never ask for more than is owed.
        return bccomp($next, $balance, 2) > 0 ? $balance : $next;
    }

    /** Browser return from Flutterwave. Public. The payer may not be signed in. */
    public function callback(Request $request)
    {
        $transactionId = (string) $request->query('transaction_id', '');
        $flwStatus = (string) $request->query('status', '');
        $txRef = (string) $request->query('tx_ref', '');

        $result = 'cancelled';
        if ($transactionId !== '' && in_array($flwStatus, ['successful', 'completed'], true)) {
            $result = $this->service->settle($transactionId);
        }

        $ok = in_array($result, ['settled', 'already_settled'], true);

        return view('gateway.result', [
            'ok' => $ok,
            'result' => $result,
            'retryUrl' => $ok ? null : $this->retryUrl($txRef),
        ]);
    }

    /**
     * On a failed/cancelled Flutterwave attempt, send the payer back to the
     * specific course's checkout page (invoice is untouched, reusable) rather than a
     * dead-end. Falls back to browsing courses when the invoice isn't a course
     * purchase or can't be resolved from the tx_ref.
     */
    private function retryUrl(string $txRef): string
    {
        $invoice = $txRef !== '' ? GatewayLog::where('tx_ref', $txRef)->first()?->invoice : null;
        $courseItem = $invoice?->items->first(fn ($item) => $item->source_type === Course::class);

        return $courseItem ? route('courses.checkout', $courseItem->source) : route('courses.index');
    }

    /** Server-to-server webhook. Public, CSRF-exempt; authenticity via verif-hash. */
    public function webhook(Request $request, PaymentGateway $gateway)
    {
        if (! $gateway->verifyWebhookSignature($request->header('verif-hash'))) {
            return response()->json(['status' => 'invalid signature'], 401);
        }

        $transactionId = (string) data_get($request->all(), 'data.id', '');
        if ($transactionId !== '') {
            $result = $this->service->settle($transactionId);
            Log::info('Flutterwave webhook settled', ['tx' => $transactionId, 'result' => $result]);
        }

        // Always 200 so Flutterwave doesn't retry a handled event.
        return response()->json(['status' => 'ok']);
    }
}
