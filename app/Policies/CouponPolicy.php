<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('billing.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('billing.manage');
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->can('billing.manage');
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $user->can('billing.manage');
    }
}
