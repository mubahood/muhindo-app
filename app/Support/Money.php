<?php

namespace App\Support;

/**
 * Formatting helpers for Live Account money. Amounts are always whole-unit
 * integers (e.g. UGX shillings), so formatting is exact.
 */
class Money
{
    public static function format(int $amount, string $currency = 'USD'): string
    {
        // USD gets the conventional symbol; other currencies are prefixed by code.
        if (strtoupper($currency) === 'USD') {
            return '$'.number_format($amount);
        }

        return $currency.' '.number_format($amount);
    }
}
