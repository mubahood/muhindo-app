<?php

namespace App\Enums;

/** Per-course navigation rule. `sequential` locks lesson N+1 until N completes. */
enum CourseProgression: string
{
    case Free = 'free';
    case Sequential = 'sequential';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free navigation (any lesson, any order)',
            self::Sequential => 'Sequential (locked until the previous lesson is complete)',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
