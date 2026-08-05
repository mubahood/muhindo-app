<?php

namespace App\Events\Learning;

use App\Models\QuizAttempt;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired once an attempt is fully auto-graded (or, later, once manual grading completes it). */
class QuizAttemptSubmitted
{
    use Dispatchable;

    public function __construct(public readonly QuizAttempt $attempt) {}
}
