<?php

namespace App\Support;

use App\Models\ProjectInquiry;
use App\Models\User;

/**
 * Where somebody goes the moment they are signed in.
 *
 * There is one rule here worth stating out loud: a client who has not told me
 * about their project yet is sent to do that, every time, until they have.
 * They made the account in order to hire somebody — landing them on an empty
 * portal and hoping they find "Start a project" is how a lead becomes nothing.
 *
 * Everything else keeps its existing answer, and an explicit intended URL
 * still wins over all of it.
 */
class AfterAuth
{
    public static function destination(User $user): string
    {
        if (self::mustPropose($user)) {
            return route('propose');
        }

        // A client who is not also a student has no dashboard worth showing.
        if ($user->is_client && ! $user->is_student) {
            return route('portal.index');
        }

        return route('dashboard');
    }

    /** A client, hired nobody yet, and has not said what they want built. */
    public static function mustPropose(?User $user): bool
    {
        if (! $user || ! $user->is_client) {
            return false;
        }

        return ! ProjectInquiry::where('user_id', $user->id)->exists();
    }
}
