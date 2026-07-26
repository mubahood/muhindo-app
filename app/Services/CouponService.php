<?php

namespace App\Services;

use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

/**
 * §7.1 — coupon validation + redemption, applied at course-invoice creation (not at payment —
 * a use is spent the moment it's applied to an invoice, matching how the plan frames it as a
 * lightweight growth lever, not a financial ledger needing cross-invoice atomicity).
 */
class CouponService
{
    /**
     * Validates a code (case-insensitive) against a course + subtotal and atomically consumes
     * one use, returning the computed discount. The coupon row is locked for the duration so
     * two students racing for the last remaining use can't both succeed.
     *
     * @return array{discount: string, coupon: Coupon}
     */
    public function redeem(string $code, Course $course, string $subtotal): array
    {
        return DB::transaction(function () use ($code, $course, $subtotal) {
            $coupon = Coupon::where('code', strtoupper(trim($code)))->lockForUpdate()->first();

            if ($coupon === null) {
                throw InvalidCouponException::make('This coupon code does not exist.');
            }
            if (! $coupon->is_active) {
                throw InvalidCouponException::make('This coupon is no longer active.');
            }
            if ($coupon->isExpired()) {
                throw InvalidCouponException::make('This coupon has expired.');
            }
            if (! $coupon->hasUsesRemaining()) {
                throw InvalidCouponException::make('This coupon has reached its usage limit.');
            }
            if (! $coupon->appliesTo($course)) {
                throw InvalidCouponException::make('This coupon does not apply to this course.');
            }

            $discount = $this->discountFor($coupon, $subtotal);
            $coupon->increment('used_count');

            return ['discount' => $discount, 'coupon' => $coupon];
        });
    }

    private function discountFor(Coupon $coupon, string $subtotal): string
    {
        $discount = $coupon->type->value === 'percent'
            ? bcmul($subtotal, bcdiv((string) $coupon->value, '100', 4), 2)
            : (string) $coupon->value;

        // Never discount beyond the subtotal itself (a flat-amount coupon on a cheap course).
        return bccomp($discount, $subtotal, 2) > 0 ? $subtotal : $discount;
    }
}
