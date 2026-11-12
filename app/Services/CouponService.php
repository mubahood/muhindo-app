<?php

namespace App\Services;

use App\Enums\RedemptionStatus;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Coupon validation and redemption, for courses and for products alike.
 *
 * A use is taken in two steps. `reserve()` checks the code and holds a slot
 * while the buyer is at the payment screen; `confirm()` turns that hold into a
 * real use when the money lands. An unpaid hold goes stale on its own after
 * `catalog.coupon_reservation_hours` and the slot returns to the pool.
 *
 * The old single-step version spent a use at invoice creation, which meant a
 * limited campaign could be emptied by people who never paid. That is the
 * difference between "50 students get this price" and "50 people clicked".
 */
class CouponService
{
    /**
     * Validate a code against a purchasable and a subtotal, and hold one use.
     *
     * The coupon row is locked for the duration, so two buyers racing for the
     * last remaining use cannot both win it.
     *
     * @param  Model  $purchasable  a Course or a Product
     * @return array{discount: numeric-string, coupon: Coupon, redemption: CouponRedemption}
     */
    public function reserve(string $code, Model $purchasable, string $subtotal, User $user, string $currency = 'UGX'): array
    {
        return DB::transaction(function () use ($code, $purchasable, $subtotal, $user, $currency) {
            $coupon = Coupon::where('code', self::normalise($code))->lockForUpdate()->first();

            if ($coupon === null) {
                throw InvalidCouponException::make('This coupon code does not exist.');
            }
            if (! $coupon->appliesTo($purchasable)) {
                throw InvalidCouponException::make('This coupon does not apply to this item.');
            }

            $this->assertUsable($coupon, $user, $subtotal, $currency);

            $discount = $this->discountFor($coupon, $subtotal);

            $redemption = $coupon->redemptions()->create([
                'user_id' => $user->id,
                'invoice_id' => null,        // attached once the invoice exists
                'discount' => $discount,
                'currency' => $currency,
                'status' => RedemptionStatus::Reserved,
                'reserved_at' => Carbon::now(),
            ]);

            return ['discount' => $discount, 'coupon' => $coupon, 'redemption' => $redemption];
        });
    }

    /**
     * Same, for a basket that may hold several things.
     *
     * The discount is computed on the part of the basket the coupon actually
     * covers, not on the whole order. "50% off this one product" must not take
     * half off the course sitting next to it in the basket, and a percentage
     * coupon on a mixed basket would otherwise do exactly that.
     *
     * @param  Collection<int,array{model:Model,line_total:numeric-string,name:string}>  $lines
     * @return array{discount: numeric-string, coupon: Coupon, redemption: CouponRedemption, eligible_subtotal: numeric-string}
     */
    public function reserveForBasket(string $code, Collection $lines, User $user, string $currency = 'UGX'): array
    {
        return DB::transaction(function () use ($code, $lines, $user, $currency) {
            $coupon = Coupon::where('code', self::normalise($code))->lockForUpdate()->first();

            if ($coupon === null) {
                throw InvalidCouponException::make('This coupon code does not exist.');
            }

            $eligible = $lines->filter(fn (array $line) => $coupon->appliesTo($line['model']));

            if ($eligible->isEmpty()) {
                throw InvalidCouponException::make(
                    'This coupon does not apply to anything in your basket.'
                );
            }

            $eligibleSubtotal = $eligible->reduce(
                fn (string $carry, array $line) => bcadd($carry, (string) $line['line_total'], 2),
                '0.00'
            );

            $this->assertUsable($coupon, $user, $eligibleSubtotal, $currency);

            $discount = $this->discountFor($coupon, $eligibleSubtotal);

            $redemption = $coupon->redemptions()->create([
                'user_id' => $user->id,
                'invoice_id' => null,
                'discount' => $discount,
                'currency' => $currency,
                'status' => RedemptionStatus::Reserved,
                'reserved_at' => Carbon::now(),
            ]);

            return [
                'discount' => $discount,
                'coupon' => $coupon,
                'redemption' => $redemption,
                'eligible_subtotal' => $eligibleSubtotal,
            ];
        });
    }

    /**
     * Everything true of a usable coupon except what it applies to, which the
     * single-item and basket paths each decide for themselves.
     *
     * Ordered so the message a buyer sees names the most useful reason: being
     * told "not available yet" beats "usage limit reached" on a coupon that is
     * both.
     */
    private function assertUsable(Coupon $coupon, User $user, string $subtotal, string $currency): void
    {
        if (! $coupon->is_active) {
            throw InvalidCouponException::make('This coupon is no longer active.');
        }
        if (! $coupon->hasStarted()) {
            throw InvalidCouponException::make('This coupon is not available yet.');
        }
        if ($coupon->isExpired()) {
            throw InvalidCouponException::make('This coupon has expired.');
        }
        if (! $coupon->meetsMinimum($subtotal)) {
            throw InvalidCouponException::make(
                'This coupon needs a subtotal of at least '
                .$currency.' '.number_format((float) $coupon->min_subtotal).'.'
            );
        }
        if (! $coupon->hasUsesRemaining()) {
            throw InvalidCouponException::make('This coupon has reached its usage limit.');
        }
        if (! $coupon->hasUsesRemainingFor($user)) {
            throw InvalidCouponException::make('You have already used this coupon.');
        }
    }

    /** Point a held reservation at the invoice it discounted. */
    public function attachToInvoice(CouponRedemption $redemption, Invoice $invoice): void
    {
        $redemption->update(['invoice_id' => $invoice->id]);
    }

    /**
     * The money landed: turn every hold against this invoice into a real use.
     *
     * Idempotent, because it is called from a payment listener and a payment
     * webhook can arrive twice. Confirming a stale hold is still correct — the
     * customer paid, so the use is theirs whether or not the window lapsed.
     */
    public function confirmFor(Invoice $invoice): int
    {
        return DB::transaction(function () use ($invoice) {
            $held = CouponRedemption::where('invoice_id', $invoice->id)
                ->where('status', RedemptionStatus::Reserved)
                ->lockForUpdate()
                ->get();

            foreach ($held as $redemption) {
                $redemption->update([
                    'status' => RedemptionStatus::Confirmed,
                    'confirmed_at' => Carbon::now(),
                ]);

                // Cached for list screens; the rows above remain the record.
                Coupon::whereKey($redemption->coupon_id)->increment('used_count');
            }

            return $held->count();
        });
    }

    /** The sale fell through: hand the slot back now rather than waiting for it to go stale. */
    public function releaseFor(Invoice $invoice): int
    {
        return CouponRedemption::where('invoice_id', $invoice->id)
            ->where('status', RedemptionStatus::Reserved)
            ->update([
                'status' => RedemptionStatus::Released,
                'released_at' => Carbon::now(),
            ]);
    }

    /**
     * The money went back: undo a use that was already confirmed.
     *
     * Separate from releaseFor() because the starting state differs. A refund
     * acts on confirmed rows and so must also wind back the cached counter,
     * which a plain release never touches.
     */
    public function revokeFor(Invoice $invoice): int
    {
        return DB::transaction(function () use ($invoice) {
            $rows = CouponRedemption::where('invoice_id', $invoice->id)
                ->whereIn('status', [RedemptionStatus::Reserved, RedemptionStatus::Confirmed])
                ->lockForUpdate()
                ->get();

            foreach ($rows as $redemption) {
                $wasConfirmed = $redemption->status === RedemptionStatus::Confirmed;

                $redemption->update([
                    'status' => RedemptionStatus::Released,
                    'released_at' => Carbon::now(),
                ]);

                // Guarded so the cache cannot go negative if a refund is
                // somehow applied twice.
                if ($wasConfirmed) {
                    Coupon::whereKey($redemption->coupon_id)
                        ->where('used_count', '>', 0)
                        ->decrement('used_count');
                }
            }

            return $rows->count();
        });
    }

    /**
     * What a code would take off, without holding a use.
     *
     * For showing a price before the buyer commits. It deliberately shares the
     * validation above so the quoted figure cannot differ from the charged one.
     *
     * @return array{discount: numeric-string, coupon: Coupon}
     */
    public function quote(string $code, Model $purchasable, string $subtotal, User $user, string $currency = 'UGX'): array
    {
        return DB::transaction(function () use ($code, $purchasable, $subtotal, $user, $currency) {
            $result = $this->reserve($code, $purchasable, $subtotal, $user, $currency);

            // A quote must not consume anything, so unwind the hold it just took.
            $result['redemption']->delete();

            return ['discount' => $result['discount'], 'coupon' => $result['coupon']];
        });
    }

    public static function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    private function discountFor(Coupon $coupon, string $subtotal): string
    {
        $discount = $coupon->type->value === 'percent'
            ? bcmul($subtotal, bcdiv((string) $coupon->value, '100', 4), 2)
            : (string) $coupon->value;

        // Never discount beyond the subtotal itself (a flat-amount coupon on a
        // cheap item), which would otherwise produce a negative total.
        return bccomp($discount, $subtotal, 2) > 0 ? $subtotal : $discount;
    }
}
