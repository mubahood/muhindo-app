<?php

namespace App\Enums;

/** How a coupon's value is applied: a percentage off, or a flat currency amount off. */
enum CouponType: string
{
    case Percent = 'percent';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percent off',
            self::Amount => 'Amount off',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
