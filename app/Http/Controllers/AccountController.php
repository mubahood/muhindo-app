<?php

namespace App\Http\Controllers;

use App\Services\AccountService;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * "Your account" — the profile and settings screen every signed-in person shares,
 * on the same shell as the rest of the logged-in side.
 *
 * Each panel posts its own form and validates into its own error bag, so a
 * mistyped password never wipes the details a person was editing above it and
 * every message lands next to the field it belongs to.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function edit(Request $request): View
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return view('account.edit', [
            'user' => $user,
            'currentType' => $this->accounts->currentType($user),
            // What a person would lose by narrowing their account type — shown up
            // front rather than silently overridden after they save.
            'enrollmentCount' => $user->enrollments()->count(),
            'projectCount' => $user->client?->projects()->count() ?? 0,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:32'],
            'bio' => ['nullable', 'string', 'max:500'],
        ]);

        // Changing the address invalidates the verification that pointed at the old one.
        if ($data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }

        $user->update($data);

        return back()->with('success', 'Your details have been saved.');
    }

    public function updateAccountType(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validateWithBag('accountType', [
            'account_type' => ['required', 'in:'.implode(',', AccountService::TYPES)],
        ]);

        if ($user->isAdmin()) {
            // Admins reach every surface through their role; a capability flag would
            // be meaningless here and could only mislead.
            return back()->with('warning', 'Admin accounts already have access to both the learning and client areas.');
        }

        $kept = $this->accounts->applyAccountType($user, $data['account_type']);

        if ($kept !== []) {
            $reason = in_array('client', $kept, true)
                ? 'you still have projects on your account'
                : 'you are still enrolled in a course';

            return back()->with('warning', 'Saved — but '.implode(' and ', array_map(
                fn (string $c) => $c === 'client' ? 'client access' : 'learning access', $kept
            )).' was kept because '.$reason.'.');
        }

        return back()->with('success', 'Your account type has been updated.');
    }

    public function updateAvatar(Request $request, AvatarService $avatars): RedirectResponse
    {
        $request->validateWithBag('avatar', [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->deleteStoredAvatar($user->avatar);
        $user->update(['avatar' => $avatars->storeResized($request->file('avatar'))]);

        return back()->with('success', 'Your photo has been updated.');
    }

    public function removeAvatar(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->deleteStoredAvatar($user->avatar);
        $user->update(['avatar' => null]);

        return back()->with('success', 'Your photo has been removed.');
    }

    private function deleteStoredAvatar(?string $path): void
    {
        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
