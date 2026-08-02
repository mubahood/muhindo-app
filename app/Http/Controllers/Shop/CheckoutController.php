<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Shop\Cart;
use App\Services\Shop\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly Cart $cart,
        private readonly CheckoutService $checkout,
    ) {}

    /** The one review screen before money moves. */
    public function review(Request $request): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.index')->with('error', 'Your basket is empty.');
        }

        return view('shop.checkout', [
            'lines' => $this->cart->contents(),
            'subtotal' => $this->cart->subtotal(),
            'currency' => $this->cart->currency() ?? 'UGX',
            'free' => bccomp($this->cart->subtotal(), '0', 2) <= 0,
        ]);
    }

    /**
     * Historic URL for the shop's payment screen. There is now one payment
     * screen for every kind of purchase; this forwards to it.
     */
    public function pay(Request $request, \App\Models\Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        // Kept as a redirect rather than deleted: this URL is handed out by
        // place() and may already be in someone's history.
        return redirect()->route('payments.show', $invoice);
    }

    /**
     * Raise the invoice and hand straight over to the existing gateway route.
     *
     * Nothing here talks to Flutterwave: the shop's job ends at "there is an
     * invoice", and payment, verification and settlement are the same code path
     * that already handles course checkouts and client invoices.
     */
    public function place(Request $request): RedirectResponse
    {
        try {
            ['invoice' => $invoice, 'owned' => $owned] = $this->checkout->checkout($request->user());
        } catch (RuntimeException $e) {
            return redirect()->route('cart.show')->with('error', $e->getMessage());
        }

        if ($invoice === null) {
            return redirect()->route('shop.downloads')
                ->with('success', 'You already own everything that was in your basket.');
        }

        $notice = $owned === []
            ? null
            : 'Already owned, so left off this order: '.implode(', ', $owned).'.';

        // A free order has nothing to pay for. It is still settled through
        // billing rather than fulfilled here, so free and paid orders reach
        // access by the same event and the same listeners.
        if (bccomp((string) $invoice->total, '0', 2) <= 0) {
            app(\App\Services\BillingService::class)->settleFreeInvoice($invoice);

            return redirect()->route('shop.downloads')
                ->with('success', trim(($notice ? $notice.' ' : '').'Done — your items are ready below.'));
        }

        return redirect()->route('checkout.pay', $invoice)->with('success', $notice);
    }
}
