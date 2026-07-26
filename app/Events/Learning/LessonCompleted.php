<?php

namespace App\Events\Learning;

use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Foundation\Events\Dispatchable;

/** §4.5 — fired once per completed lesson, whether or not it finishes the course. */
class LessonCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly Lesson $lesson,
    ) {}
}
