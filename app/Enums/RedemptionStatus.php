<?php

namespace App\Enums;

/**
 * The life of one coupon use.
 *
 * Reserved is the state that matters. A use was previously spent the moment a
 * coupon was applied to an invoice, so a 50-use launch campaign could be
 * exhausted by fifty people who never paid — and on this site's own numbers,
 * 4 invoices in 19 are paid. A reservation holds the slot while the buyer is at
 * the payment screen, becomes a confirmed use when the money lands, and returns
 * to the pool if it never does.
 */
enum RedemptionStatus: string
{
    case Reserved = 'reserved';
    case Confirmed = 'confirmed';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'Awaiting payment',
            self::Confirmed => 'Used',
            self::Released => 'Returned to the pool',
        };
    }
}
