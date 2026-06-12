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
use Illuminate\Support\Facades\DB;

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

        // Eager load the relationships so Vue knows which cohort they belong to
        $query = User::with(['enrolledCohorts', 'administeredCohorts'])->latest();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->get();
        return $this->successResponse($users, 'Users retrieved successfully.');
    }
    /**
     * Store a newly provisioned user and handle role-specific relationships.
     *
     * POST /api/v1/auth/users
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        // 1. Validate the base user data AND the dynamic role-specific data
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|email|max:255|unique:users',
            'password'    => 'required|string|min:8',
            'role'        => 'required|string|in:branch_manager,track_admin,instructor,student',
            'is_active'   => 'sometimes|boolean',
            'expiry_date' => 'required|date|after_or_equal:today',

            // Instructor specific fields
            'compensation_type' => 'required_if:role,instructor|in:internal,external',
            'hourly_rate'       => 'required_if:compensation_type,external|numeric|min:0',
            'fixed_salary'      => 'required_if:compensation_type,internal|numeric|min:0',

            // Student & Admin specific fields (Required for BOTH)
            'cohort_id'   => 'required_if:role,student,track_admin|exists:cohorts,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        // 2. Use a Database Transaction to ensure data integrity
        $user = DB::transaction(function () use ($validated) {
            
            // Extract base user data
            $userData = collect($validated)->only([
                'name', 'email', 'password', 'role', 'is_active', 'expiry_date', 
                'compensation_type', 'hourly_rate', 'fixed_salary'
            ])->toArray();

            $user = User::create($userData);

            // 3. Handle Role-Specific Provisioning
            
            // A. If they are a STUDENT
            if ($user->role === 'student' && isset($validated['cohort_id'])) {
                // Enroll student in cohort
                $user->enrolledCohorts()->attach($validated['cohort_id'], [
                    'enrolled_at' => now()
                ]);
                
                // Initialize their Attendance Ledger (Start with 250 points as per ATT-4)
                \App\Models\AttendanceLedger::create([
                    'student_id' => $user->id,
                    'cohort_id'  => $validated['cohort_id'],
                    'balance'    => 250
                ]);
            }

            // B. If they are a TRACK ADMIN
            if ($user->role === 'track_admin' && isset($validated['cohort_id'])) {
                // Assign Track Admin to the cohort
                $user->administeredCohorts()->attach($validated['cohort_id']);
            }

            return $user;
        });

        return $this->successResponse($user, 'Account successfully provisioned.', 201);
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

        // 1. Validate including dynamic fields (Password is optional on update)
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'email'       => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password'    => 'nullable|string|min:8',
            'role'        => 'sometimes|required|string|in:branch_manager,track_admin,instructor,student',
            'is_active'   => 'sometimes|boolean',
            'expiry_date' => 'sometimes|required|date|after_or_equal:today',

            // Instructor specific fields
            'compensation_type' => 'required_if:role,instructor|in:internal,external',
            'hourly_rate'       => 'required_if:compensation_type,external|numeric|min:0',
            'fixed_salary'      => 'required_if:compensation_type,internal|numeric|min:0',

            // Student & Admin specific fields
            'cohort_id'   => 'required_if:role,student,track_admin|exists:cohorts,id',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']); // Don't overwrite with a blank password
        }

        try {
            DB::transaction(function () use ($validated, $user) {
                // 2. Update base fields
                $userData = collect($validated)->only([
                    'name', 'email', 'password', 'role', 'is_active', 'expiry_date',
                    'compensation_type', 'hourly_rate', 'fixed_salary'
                ])->toArray();
                
                $user->update($userData);

                // 3. Sync Relationships
                if ($user->role === 'student' && isset($validated['cohort_id'])) {
                    $user->enrolledCohorts()->sync([
                        $validated['cohort_id'] => ['enrolled_at' => now()]
                    ]);
                }

                if ($user->role === 'track_admin' && isset($validated['cohort_id'])) {
                    $user->administeredCohorts()->sync([$validated['cohort_id']]);
                }
            });

            return $this->successResponse($user, 'Account updated successfully.');

        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Database Error during User Update: ' . $e->getMessage());
            return $this->errorResponse('A database error occurred.', 500);
        }
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
