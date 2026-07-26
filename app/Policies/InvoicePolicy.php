<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('billing.manage');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.manage') || $this->isOwner($user, $invoice);
    }

    public function create(User $user): bool
    {
        return $user->can('billing.manage');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.manage');
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        // Staff can record any payment method; the billable owner can only
        // self-serve pay their own invoice (online, via Flutterwave).
        return $user->can('billing.manage') || $this->isOwner($user, $invoice);
    }

    /** The billable owner (a client or the student who purchased a course). */
    private function isOwner(User $user, Invoice $invoice): bool
    {
        if ($invoice->billable_type === User::class) {
            return $invoice->billable_id === $user->id;
        }
        if ($invoice->billable_type === Client::class) {
            return $invoice->billable?->user_id === $user->id;
        }

        return false;
    }
}
