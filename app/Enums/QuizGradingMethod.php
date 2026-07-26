<?php

namespace App\Enums;

/** §5.1 — which attempt counts when a quiz allows more than one. */
enum QuizGradingMethod: string
{
    case Highest = 'highest';
    case Latest = 'latest';
    case Average = 'average';
    case First = 'first';

    public function label(): string
    {
        return match ($this) {
            self::Highest => 'Highest attempt',
            self::Latest => 'Latest attempt',
            self::Average => 'Average of all attempts',
            self::First => 'First attempt',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
