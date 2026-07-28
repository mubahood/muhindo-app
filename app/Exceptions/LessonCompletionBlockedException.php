<?php

namespace App\Exceptions;

use Exception;

/**
 * A student tried to complete a lesson whose requirements aren't met yet —
 * minimum focused time not reached, or a required quiz/assignment unsubmitted.
 * Carries the structured blocker list so callers (web JSON, API, redirects)
 * can each present it their own way.
 */
class LessonCompletionBlockedException extends Exception
{
    /** @param array<int,array<string,mixed>> $blockers */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct(collect($blockers)->pluck('message')->implode(' '));
    }
}
