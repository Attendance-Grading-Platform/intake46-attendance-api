<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Authenticate a user and issue a Sanctum token.
     *
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // 1. Verify Identity
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid email or password.', 401);
        }

        // 2. Enforce Account Lifecycle Constraints (SEC-2)
        if (! $user->is_active) {
            return $this->errorResponse('Your account has been deactivated.', 403);
        }

        if ($user->expiry_date && now()->startOfDay()->greaterThan($user->expiry_date)) {
            return $this->errorResponse('Your account has expired. Please contact your Branch Manager.', 403);
        }

        // 3. Issue Sanctum Bearer Token
        // We delete old tokens to prevent an explosion of orphaned tokens across devices
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        // 4. Return unified payload for the Vue 3 Pinia Store
        return $this->successResponse([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role, // Crucial for Frontend Contextual RBAC
            ],
        ], 'Login successful.');
    }

    /**
     * Revoke the current user's access token.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Delete the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully.');
    }
}
