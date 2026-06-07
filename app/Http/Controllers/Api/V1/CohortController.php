<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\User;
use App\Services\CohortService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CohortController — manages cohort lifecycle.
 *
 * LC-2: Only the Branch Manager shall create cohorts and assign Track Admins.
 *       Enforced via CohortPolicy injected through $this->authorize().
 */
class CohortController extends Controller
{
    use ApiResponse;

    public function __construct(protected CohortService $cohortService) {}

    /**
     * GET /api/v1/cohorts
     *
     * List all cohorts (eager loading tracks).
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Cohort::class);

        $cohorts = Cohort::with('track.branch')
                         ->orderBy('created_at', 'desc')
                         ->get();

        return $this->successResponse($cohorts, 'Cohorts retrieved successfully.');
    }

    /**
     * POST /api/v1/cohorts
     *
     * Create a new cohort for a given track.
     * LC-1 (one active per track) is enforced by CohortService.
     * LC-2 (branch_manager only) is enforced by CohortPolicy@create.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Cohort::class);

        $validated = $request->validate([
            'track_id'   => 'required|integer|exists:tracks,id',
            'name'       => 'required|string|max:100',
            'started_at' => 'required|date',
        ]);

        // CohortService enforces LC-1 and throws ValidationException on violation
        $cohort = $this->cohortService->create(array_merge($validated, ['status' => 'active']));

        return $this->successResponse($cohort, 'Cohort created successfully.', 201);
    }

    /**
     * PUT /api/v1/cohorts/{cohort}
     *
     * Update cohort details.
     * LC-2: Only branch_manager may call this.
     */
    public function update(Request $request, Cohort $cohort): JsonResponse
    {
        $this->authorize('update', $cohort);

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'status'     => 'sometimes|in:active,closed',
            'started_at' => 'sometimes|date',
            'ended_at'   => 'sometimes|date|nullable',
        ]);

        $cohort->update($validated);

        return $this->successResponse($cohort, 'Cohort updated successfully.');
    }

    /**
     * GET /api/v1/cohorts/{cohort}
     *
     * Return cohort details (with its track and branch context).
     * Any authenticated user may view.
     */
    public function show(Cohort $cohort): JsonResponse
    {
        $this->authorize('view', $cohort);

        $cohort->load('track.branch');

        return $this->successResponse($cohort, 'Cohort retrieved successfully.');
    }

    /**
     * POST /api/v1/cohorts/{cohort}/assign-admin
     *
     * Assign a Track Admin (role = track_admin) to an active cohort.
     * LC-2: Only branch_manager may call this.
     */
    public function assignAdmin(Request $request, Cohort $cohort): JsonResponse
    {
        $this->authorize('assignAdmin', $cohort);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                // Only users with role track_admin may be assigned
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = User::find($value);
                    if (!$user || $user->role !== 'track_admin') {
                        $fail('The selected user must have the track_admin role.');
                    }
                },
            ],
        ]);

        // Attach track admin to cohort (pivot: cohort_track_admins)
        // syncWithoutDetaching keeps existing assignments when adding more
        $cohort->trackAdmins()->syncWithoutDetaching([$validated['user_id']]);

        $cohort->load('trackAdmins:id,name,email,role');

        return $this->successResponse(
            $cohort->trackAdmins,
            'Track Admin assigned successfully.'
        );
    }
}
