<?php

namespace App\Events\Learning;

use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;

/** §4.5 — fired the moment an enrollment crosses 100% and its status flips to completed. */
class CourseCompleted
{
    use Dispatchable;

    public function __construct(public readonly Enrollment $enrollment) {}
}
