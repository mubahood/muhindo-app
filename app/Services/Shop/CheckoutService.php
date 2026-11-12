<?php

namespace App\Services\Shop;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\BillingService;
use App\Services\CouponService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns a basket into an invoice.
 *
 * This is the single seam between "what someone chose" and the billing machinery
 * that already exists. Everything after it, the hosted payment session, the
 * server-side verify, recording the payment, the InvoicePaid event and the
 * listeners that grant access, is the same code that already settles course
 * checkouts and client project invoices. There is one payment path on this
 * site, and the shop joins it rather than adding a second.
 */
class CheckoutService
{
    public function __construct(
        private readonly Cart $cart,
        private readonly BillingService $billing,
        private readonly CouponService $coupons,
    ) {}

    /**
     * Everything already owned is dropped before invoicing, so a full basket of
     * things someone bought last week cannot bill them again.
     *
     * A coupon is applied to the part of the basket it covers, after the
     * already-owned lines have been dropped — so a code scoped to a product
     * someone already bought is refused rather than silently discounting
     * whatever is left.
     *
     * @return array{invoice:?Invoice, owned:list<string>, discount:numeric-string, coupon:?string}
     */
    public function checkout(User $user, ?string $couponCode = null): array
    {
        $contents = $this->cart->contents();

        if ($contents->isEmpty()) {
            throw new RuntimeException('Your basket is empty.');
        }

        if ($this->cart->currency() === null) {
            throw new RuntimeException('Everything in one order has to be priced in the same currency.');
        }

        $owned = [];
        $items = [];
        $eligible = collect();

        /* Second check. A basket can sit in a session for days, and a product
           that was deliverable when it went in may have had its file removed
           since, so the invoice is never raised on a stale answer. */
        foreach ($contents as $line) {
            if ($line['model'] instanceof Product && ! $line['model']->isDeliverable()) {
                throw new RuntimeException(
                    "\"{$line['name']}\" is not ready to download yet. Remove it to continue."
                );
            }
        }

        foreach ($contents as $line) {
            if ($this->alreadyOwns($user, $line)) {
                $owned[] = $line['name'];
                $this->cart->remove($line['key']);

                continue;
            }

            $items[] = [
                'description' => $line['type'] === 'course'
                    ? 'Course enrolment '.$line['name']
                    : $line['name'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'source' => $line['model'],
            ];

            $eligible->push([
                'model' => $line['model'],
                'name' => $line['name'],
                'line_total' => bcmul((string) $line['unit_price'], (string) $line['quantity'], 2),
            ]);
        }

        if ($items === []) {
            return ['invoice' => null, 'owned' => $owned, 'discount' => '0.00', 'coupon' => null];
        }

        $currency = $this->cart->currency() ?? 'UGX';
        $discount = '0.00';
        $held = null;

        if ($couponCode !== null && trim($couponCode) !== '') {
            $held = $this->coupons->reserveForBasket($couponCode, $eligible, $user, $currency);
            $discount = $held['discount'];
        }

        $invoice = DB::transaction(function () use ($user, $items, $currency, $discount, $held) {
            $invoice = $this->billing->generateInvoice(
                billable: $user,
                items: $items,
                discount: $discount,
                currency: $currency,
            );

            if ($held !== null) {
                $this->coupons->attachToInvoice($held['redemption'], $invoice);
            }

            return $invoice;
        });

        $this->cart->clear();

        return [
            'invoice' => $invoice,
            'owned' => $owned,
            'discount' => $discount,
            'coupon' => $held['coupon']->code ?? null,
        ];
    }

    /** @param array<string,mixed> $line */
    private function alreadyOwns(User $user, array $line): bool
    {
        if ($line['model'] instanceof Product) {
            return $user->productLicenses()->where('product_id', $line['model']->id)->exists();
        }

        if ($line['model'] instanceof Course) {
            return $user->enrollments()
                ->where('course_id', $line['model']->id)
                ->whereIn('status', ['active', 'completed'])
                ->exists();
        }

        return false;
    }
}
