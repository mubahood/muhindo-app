<?php

namespace App\Enums;

/**
 * §4.3 completion rules engine. Only `manual` (existing behaviour) and
 * `min_watch` are enforced today; `quiz_pass`/`submission` are declared now so
 * lesson forms and the schema don't need another migration once P3's quiz/
 * assignment models exist to back them.
 */
enum CompletionRule: string
{
    case Manual = 'manual';
    case MinWatch = 'min_watch';
    case QuizPass = 'quiz_pass';
    case Submission = 'submission';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual (student clicks complete)',
            self::MinWatch => 'Minimum watch time',
            self::QuizPass => 'Pass the lesson quiz',
            self::Submission => 'Submit an assignment',
        };
    }

    /** Whether this rule is enforced yet (the other two wait on P3 models). */
    public function isEnforced(): bool
    {
        return match ($this) {
            self::Manual, self::MinWatch => true,
            self::QuizPass, self::Submission => false,
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn ($c, $s) => $c + [$s->value => $s->label()], []);
    }
}
