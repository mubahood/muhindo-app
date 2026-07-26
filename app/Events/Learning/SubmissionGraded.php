<?php

namespace App\Events\Learning;

use App\Models\AssignmentSubmission;
use Illuminate\Foundation\Events\Dispatchable;

/** §4.5 — fired once an instructor returns (grades) an assignment submission. */
class SubmissionGraded
{
    use Dispatchable;

    public function __construct(public readonly AssignmentSubmission $submission) {}
}
