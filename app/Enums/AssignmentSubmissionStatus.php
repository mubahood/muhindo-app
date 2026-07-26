<?php

namespace App\Enums;

/** §5.3 — Classroom's turn-in flow: draft (private, editable) -> submitted -> returned (graded). */
enum AssignmentSubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Returned => 'Returned',
        };
    }
}
