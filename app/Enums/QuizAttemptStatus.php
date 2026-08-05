<?php

namespace App\Enums;

/** The attempt lifecycle QuizService moves an attempt through, server-authoritative. */
enum QuizAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Submitted => 'Submitted',
            self::Graded => 'Graded',
            self::Abandoned => 'Abandoned',
        };
    }
}
