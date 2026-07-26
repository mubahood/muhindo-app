<?php

namespace App\Enums;

/** §5.1 — the nine question types QuizService's auto-grader (§5.2) knows how to score. */
enum QuestionType: string
{
    case McqSingle = 'mcq_single';
    case McqMulti = 'mcq_multi';
    case TrueFalse = 'true_false';
    case FillBlank = 'fill_blank';
    case Numeric = 'numeric';
    case Matching = 'matching';
    case Ordering = 'ordering';
    case ShortText = 'short_text';
    case Essay = 'essay';

    public function label(): string
    {
        return match ($this) {
            self::McqSingle => 'Multiple choice (single answer)',
            self::McqMulti => 'Multiple choice (multiple answers)',
            self::TrueFalse => 'True / False',
            self::FillBlank => 'Fill in the blank',
            self::Numeric => 'Numeric answer',
            self::Matching => 'Matching pairs',
            self::Ordering => 'Ordering / sequence',
            self::ShortText => 'Short text answer',
            self::Essay => 'Essay',
        };
    }

    /** Essay is never auto-graded; short_text falls back to manual review when it can't be matched. */
    public function isAlwaysManuallyGraded(): bool
    {
        return $this === self::Essay;
    }

    /** Whether this type uses the `question_options` table at all. */
    public function usesOptions(): bool
    {
        return match ($this) {
            self::McqSingle, self::McqMulti, self::TrueFalse, self::Matching, self::Ordering => true,
            self::FillBlank, self::Numeric, self::ShortText, self::Essay => false,
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
