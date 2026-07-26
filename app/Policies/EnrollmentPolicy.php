<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    /** Owns the enrollment and its status actually grants lesson/material/completion access. */
    public function access(User $user, Enrollment $enrollment): bool
    {
        if ($enrollment->user_id !== $user->id) {
            return false;
        }

        return in_array($enrollment->status, ['active', 'completed'], true);
    }

    /** The owner may always view their own enrollment record (any status), e.g. "payment pending" screens. */
    public function view(User $user, Enrollment $enrollment): bool
    {
        if ($user->can('courses.manage')) {
            return true;
        }

        return $enrollment->user_id === $user->id;
    }
}
