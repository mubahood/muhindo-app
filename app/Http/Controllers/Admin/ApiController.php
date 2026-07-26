<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Admin self-profile JSON API (used by the admin layout's profile drawer).
 * Legacy legal/finance endpoints were removed in the trading-trainer cleanup.
 */
class ApiController extends Controller
{
    public function profileUpdate(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => "required|email|unique:users,email,{$user->id}",
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:500',
        ]);

        $user->update($data);

        return response()->json([
            'success' => true,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'initials' => $user->initials,
        ]);
    }

    public function profileAvatarUpdate(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate([
            'avatar' => 'required|image|max:10240|mimes:jpg,jpeg,png,webp,gif',
        ]);

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = app(AvatarService::class)->storeResized($request->file('avatar'));
        $user->update(['avatar' => $path]);

        return response()->json([
            'success' => true,
            'avatar_url' => $user->avatar_url,
            'initials' => $user->initials,
        ]);
    }

    public function profileAvatarRemove(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        $user->update(['avatar' => null]);

        return response()->json(['success' => true, 'initials' => $user->initials]);
    }

    public function profilePasswordChange(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:4|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['success' => true]);
    }
}
