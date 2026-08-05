<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;

/** Sequential progression: a locked lesson is never viewable/completable server-side, never mind the UI. */
class LessonPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    public function view(User $user, Lesson $lesson, Enrollment $enrollment): bool
    {
        return ! $lesson->module->course->isLessonLocked($enrollment, $lesson);
    }
}
