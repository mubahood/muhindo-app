<?php

namespace App\Enums;

/** How Lesson::content is rendered to students. */
enum ContentFormat: string
{
    case Plain = 'plain';
    case Markdown = 'markdown';
    case Html = 'html';

    public function label(): string
    {
        return match ($this) {
            self::Plain => 'Plain text',
            self::Markdown => 'Markdown',
            self::Html => 'HTML lesson (live example)',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
