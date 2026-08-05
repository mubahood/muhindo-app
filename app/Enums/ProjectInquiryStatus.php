<?php

namespace App\Enums;

/** A "start a project" lead's lifecycle in the admin inbox. */
enum ProjectInquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Converted => 'Converted',
            self::Closed => 'Closed',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::New => 'badge-warn',
            self::Contacted => 'badge-info',
            self::Converted => 'badge-success',
            self::Closed => 'badge-danger',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
