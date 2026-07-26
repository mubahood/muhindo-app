<?php

namespace App\Events\Learning;

use App\Models\AssignmentSubmission;
use Illuminate\Foundation\Events\Dispatchable;

/** §4.5 — fired when a student turns an assignment in (not on a draft save). */
class AssignmentSubmitted
{
    use Dispatchable;

    public function __construct(public readonly AssignmentSubmission $submission) {}
}
