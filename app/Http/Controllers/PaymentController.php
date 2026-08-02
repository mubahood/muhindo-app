<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\Billing\BuyerPaymentService;
use App\Services\Gateway\GatewayPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The one payment screen, for everything a person can buy here.
 *
 * There used to be two: courses/checkout in the signed-in layout and shop/pay
 * in the marketing layout, doing the same job with different wording, different
 * buttons and different chrome, so the experience of buying a course and the
 * experience of buying source code had nothing in common. Both now redirect
 * here. Whether the invoice is for a course, a product or a project only
 * changes the lines listed and where "done" points afterwards.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly BuyerPaymentService $buyer,
        private readonly GatewayPaymentService $gateway,
    ) {}

    /** Everything I have bought or still owe for — the "pay at any time" list. */
    public function index(Request $request): View
    {
        $user = $request->user();

        $invoices = Invoice::query()
            ->ownedBy($user)
            ->with('items')
            ->latest('id')
            ->get();

        return view('payments.index', [
            'outstanding' => $invoices->filter->isOutstanding()->values(),
            'settled' => $invoices->reject->isOutstanding()->values(),
        ]);
    }

    /** The payment screen for one invoice. */
    public function show(Request $request, Invoice $invoice): View|RedirectResponse
    {
        $this->authorize('pay', $invoice);

        if (! $invoice->isOutstanding()) {
            return redirect()->route('payments.index')
                ->with('success', 'That order is settled — everything on it is ready.');
        }

        return view('payments.show', [
            'invoice' => $invoice->load('items'),
            'destination' => $this->destinationFor($invoice),
        ]);
    }

    /**
     * "I will pay Mr. Muhindo Mubaraka directly."
     *
     * Lets them out of the payment screen without paying. Nothing is unlocked:
     * the invoice stays open and the course or download stays shut until a
     * payment actually clears it.
     */
    public function direct(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        try {
            $this->buyer->payDirectly($invoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard')->with('success', trim(sprintf(
            'Noted — you will pay Muhindo directly for %s (%s %s). It stays locked until he confirms the payment, and you can pay online any time from My orders.',
            $this->summaryFor($invoice),
            $invoice->currency,
            number_format((float) $invoice->balance, 2)
        )));
    }

    /** Changed their mind — drop the arrangement and hand over to the gateway. */
    public function online(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        try {
            $this->buyer->cancelDirectArrangement($invoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payments.show', $invoice);
    }

    /** Cancel an unpaid order. */
    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        try {
            $this->buyer->cancel($invoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payments.index')
            ->with('success', 'That order has been cancelled. Nothing was charged, and you can buy it again whenever you like.');
    }

    /**
     * "I have paid but nothing happened."
     *
     * Asks the gateway directly rather than waiting for a callback that may
     * never arrive. This is the difference between a payer who lost their
     * connection mid-redirect getting their course, and them having to email
     * about it.
     */
    public function recheck(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        $result = $this->gateway->reconcile($invoice);

        if (in_array($result, ['settled', 'already_settled'], true)) {
            return redirect()->route('payments.index')
                ->with('success', 'Found it — your payment is confirmed and everything is unlocked.');
        }

        return back()->with('error', $result === 'nothing_pending'
            ? 'No payment attempt has been started for this order yet, so there is nothing to check.'
            : 'We could not find a completed payment for this order yet. If money has left your account, give it a minute and check again — or message Muhindo and he will confirm it manually.');
    }

    /** Where "done" leads, and what the order is called, per kind of purchase. */
    private function destinationFor(Invoice $invoice): array
    {
        $items = $invoice->items;

        if ($course = $this->firstSource($invoice, Course::class)) {
            return ['label' => 'Go to the course', 'url' => route('learn.course', $course)];
        }

        if ($items->contains(fn ($i) => $i->source_type === Product::class)) {
            return ['label' => 'Go to my downloads', 'url' => route('shop.downloads')];
        }

        return ['label' => 'Go to my projects', 'url' => route('portal.index')];
    }

    private function summaryFor(Invoice $invoice): string
    {
        $first = $invoice->items->first();

        return $first?->description ?: 'invoice '.$invoice->invoice_no;
    }

    private function firstSource(Invoice $invoice, string $type): ?object
    {
        return $invoice->items->first(fn ($i) => $i->source_type === $type)?->source;
    }
}
