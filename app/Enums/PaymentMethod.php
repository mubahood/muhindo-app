<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case Bank = 'bank';
    case Flutterwave = 'flutterwave'; // online payment via the Flutterwave gateway

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::MobileMoney => 'Mobile money',
            self::Bank => 'Bank transfer',
            self::Flutterwave => 'Online (Flutterwave)',
        };
    }

    /** Settled by an external gateway (recorded server-side after verification). */
    public function isGateway(): bool
    {
        return $this === self::Flutterwave;
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
