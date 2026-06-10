<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Cohort;
use App\Models\LabGroup;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LabGroupController extends Controller
{
    use ApiResponse;

    /**
     * Create a new lab group for a cohort.
     * 
     * POST /v1/cohorts/{cohort}/lab-groups
     */
    public function store(Cohort $cohort, Request $request): JsonResponse
    {
        $this->authorize('update', $cohort);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $labGroup = $cohort->labGroups()->create($validated);

        return $this->successResponse($labGroup, 'Lab group created successfully.', 201);
    }

    /**
     * Assign instructors to a lab group.
     * 
     * POST /v1/lab-groups/{labGroup}/instructors
     */
    public function assignInstructors(LabGroup $labGroup, Request $request): JsonResponse
    {
        $this->authorize('update', $labGroup);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $instructors = User::whereIn('id', $validated['user_ids'])
            ->where('role', 'instructor')
            ->get();

        if ($instructors->count() !== count($validated['user_ids'])) {
            return $this->errorResponse('One or more users are not valid instructors.', 422);
        }

        $labGroup->instructors()->sync($validated['user_ids']);

        return $this->successResponse(null, 'Instructors assigned successfully.');
    }

    /**
     * Assign students to a lab group.
     * 
     * POST /v1/lab-groups/{labGroup}/students
     */
    public function assignStudents(LabGroup $labGroup, Request $request): JsonResponse
    {
        $this->authorize('update', $labGroup);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Business Rule: Students must be enrolled in the cohort of this lab group
        $enrolledInCohort = $labGroup->cohort->students()
            ->whereIn('users.id', $validated['user_ids'])
            ->pluck('users.id')
            ->toArray();

        if (count($enrolledInCohort) !== count($validated['user_ids'])) {
            return $this->errorResponse('One or more students are not enrolled in this cohort.', 422);
        }

        $labGroup->students()->sync($validated['user_ids']);

        return $this->successResponse(null, 'Students assigned to lab group successfully.');
    }

    /**
     * Get lab group details with members.
     */
    public function show(LabGroup $labGroup): JsonResponse
    {
        $this->authorize('view', $labGroup);

        return $this->successResponse(
            $labGroup->load(['students:id,name,email', 'instructors:id,name,email']),
            'Lab group details retrieved.'
        );
    }
}
