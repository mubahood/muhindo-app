<?php

namespace App\Enums;

/** §7.4 — how Lesson::content is rendered to students. */
enum ContentFormat: string
{
    case Plain = 'plain';
    case Markdown = 'markdown';

    public function label(): string
    {
        return match ($this) {
            self::Plain => 'Plain text',
            self::Markdown => 'Markdown',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
