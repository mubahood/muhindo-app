<?php

namespace App\Http\Controllers;

use App\Models\ProjectInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where "Hire Me" goes.
 *
 * Every hire button on the site points here rather than at a page, because the
 * right destination depends on who is pressing it and there is no good answer
 * that is the same for everybody:
 *
 *   a stranger      → the short public enquiry form. It used to be the
 *                     registration form, on the reasoning that a proposal
 *                     needs an owner. That is true, and it is a fine thing to
 *                     ask for after the first message rather than before it.
 *                     Asking somebody to choose a password before they can say
 *                     what they want built loses most of them, and the ones it
 *                     loses are the half decided, who are most people who ever
 *                     hire anybody.
 *   a client with
 *   nothing proposed → the proposal form, which is the actual first step
 *   a client who has
 *   already proposed → their portal, where the proposal is now sitting
 *
 * One route, so adding a hire button anywhere cannot get this wrong.
 */
class HireController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('enquire');
        }

        $proposed = ProjectInquiry::where('user_id', $user->id)->exists();

        return $proposed
            ? redirect()->route('portal.index')
            : redirect()->route('propose');
    }
}
