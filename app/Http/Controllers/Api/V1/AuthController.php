<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        // 1. Verify Identity
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Invalid email or password.', 401);
        }

        // 2. Enforce Account Lifecycle Constraints (SEC-2)
        //    Returns 403 — user IS identified but forbidden from proceeding
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

    /**
     * Get the authenticated user profile.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse([
            'id'    => $request->user()->id,
            'name'  => $request->user()->name,
            'email' => $request->user()->email,
            'role'  => $request->user()->role,
        ], 'Profile retrieved successfully.');
    }

    /**
     * Display a listing of users.
     *
     * GET /api/v1/auth/users
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::latest()->paginate(20);
        return $this->successResponse($users, 'Users retrieved successfully.');
    }

    /**
     * Store a newly created user in storage.
     *
     * POST /api/v1/auth/users
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users',
            'password'  => 'required|string|min:8',
            'role'      => 'required|string|in:branch_manager,track_admin,instructor,student',
            'is_active' => 'sometimes|boolean',
            'expiry_date' => 'nullable|date',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        return $this->successResponse($user, 'User created successfully.', 201);
    }

    /**
     * Display the specified user.
     *
     * GET /api/v1/auth/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->successResponse($user, 'User retrieved successfully.');
    }

    /**
     * Update the specified user in storage.
     *
     * PUT /api/v1/auth/users/{user}
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'email'     => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password'  => 'sometimes|string|min:8',
            'role'      => 'sometimes|required|string|in:branch_manager,track_admin,instructor,student',
            'is_active' => 'sometimes|boolean',
            'expiry_date' => 'nullable|date',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return $this->successResponse($user, 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     *
     * DELETE /api/v1/auth/users/{user}
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return $this->successResponse(null, 'User deleted successfully.');
    }
}
