<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Token auth for the API (Sanctum). One mechanism only (C16). RBAC is enforced
 * on every resource endpoint by the same Policies as the admin panel (C13), so
 * a token never grants more than the user's role.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            return ApiResponse::error(ApiErrorCode::Unauthenticated, 'Invalid credentials.', 401);
        }
        if (! $user->is_active) {
            return ApiResponse::error(ApiErrorCode::Forbidden, 'This account is disabled.', 403);
        }

        $token = $user->createToken($request->validated('device_name') ?: 'api')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
            'password_change_required' => (bool) $user->password_change_required,
        ], 'Signed in.');
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success(new UserResource(request()->user()));
    }

    public function logout(): JsonResponse
    {
        $token = request()->user()?->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return ApiResponse::success(null, 'Signed out.');
    }
}
