<?php

namespace App\Enums;

/** When a student sees the correct answer/explanation. */
enum QuizFeedbackMode: string
{
    case Immediate = 'immediate';
    case AfterSubmit = 'after_submit';
    case AfterClose = 'after_close';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Immediately after each question (practice mode)',
            self::AfterSubmit => 'After submitting the attempt',
            self::AfterClose => 'After the quiz closes (available_until)',
            self::None => 'Score only (exam mode)',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
