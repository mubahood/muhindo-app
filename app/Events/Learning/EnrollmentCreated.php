<?php

namespace App\Events\Learning;

use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;

/** §4.5 — fired once, only for a genuinely new enrollment (not a double-click/idempotent hit on an existing one). */
class EnrollmentCreated
{
    use Dispatchable;

    public function __construct(public readonly Enrollment $enrollment) {}
}
