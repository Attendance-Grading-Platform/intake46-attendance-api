<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CohortController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Cohort::class);

        $cohorts = Cohort::all();

        return $this->successResponse($cohorts, 'Cohorts retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Cohort::class);

        $validated = $request->validate([
            'track_id' => 'required|exists:tracks,id',
            'name' => 'required|string|max:100',
            'status' => 'nullable|in:active,closed',
            'started_at' => 'required|date',
            'ended_at' => 'required|date|after:started_at',
        ]);

        $cohort = Cohort::create($validated);

        return $this->successResponse($cohort, 'Cohort created successfully.', 201);
    }

    public function show(Cohort $cohort): JsonResponse
    {
        $this->authorize('view', $cohort);

        return $this->successResponse($cohort, 'Cohort retrieved successfully.');
    }

    public function update(Request $request, Cohort $cohort): JsonResponse
    {
        $this->authorize('update', $cohort);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:active,closed',
            'started_at' => 'sometimes|date',
            'ended_at' => 'sometimes|date|after:started_at',
        ]);

        $cohort->update($validated);

        return $this->successResponse($cohort, 'Cohort updated successfully.');
    }

    public function destroy(Cohort $cohort): JsonResponse
    {
        $this->authorize('delete', $cohort);

        $cohort->delete();

        return $this->successResponse(null, 'Cohort deleted successfully.');
    }

    public function assignAdmin(Request $request, Cohort $cohort): JsonResponse
    {
        $this->authorize('assignAdmin', $cohort);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if ($user && $user->role !== 'track_admin') {
                        $fail('The selected user must have the track_admin role.');
                    }
                },
            ],
        ]);

        $cohort->trackAdmins()->syncWithoutDetaching([$validated['user_id']]);

        return $this->successResponse(null, 'Track Admin assigned successfully.');
    }
}
