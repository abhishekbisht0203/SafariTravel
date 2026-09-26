<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token-based authentication for API operators.
 *
 * Operators live in the backend's own table and authenticate with Sanctum
 * personal access tokens. Nothing here touches wp_users, so a WordPress
 * password can never be exchanged for an API token and an API token can never
 * be used against wp-admin.
 */
class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        // Hash unconditionally so a missing account and a wrong password take
        // the same amount of time.
        $password = (string) $request->validated('password');

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            return response()->json([
                'message' => 'These credentials do not match our records.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->canViewLeads()) {
            return response()->json([
                'message' => 'This account is not permitted to use the API.',
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $user->createToken(
            trim((string) ($request->validated('device_name') ?: 'api-client')),
        );

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ]);
    }

    /**
     * DELETE /api/v1/auth/login — revoke the token used for this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
