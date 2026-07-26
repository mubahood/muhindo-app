<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('projects.manage');
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->can('projects.manage')) {
            return true;
        }

        return $project->isOwnedByUser($user);
    }

    public function create(User $user): bool
    {
        return $user->can('projects.manage');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('projects.manage');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects.manage');
    }
}
