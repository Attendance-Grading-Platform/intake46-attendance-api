<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExcuseRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * ExcuseRequestPolicy — Manages authorization for the excuse workflow.
 * * * Business Rules: EXC-1 (Student submits), EXC-3 (Track Admin approves/rejects).
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 */
class ExcuseRequestPolicy
{
    /**
     * Determine whether the user can view the list of excuse requests.
     * Note: Collection filtering must be handled in the Controller query.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific excuse request.
     * * * Contextual Authorization Pattern:
     * - Branch Managers: Global visibility.
     * - Track Admins: Can view requests from students in their administered cohorts.
     * - Students: Can only view their own requests.
     */
    public function view(User $user, ExcuseRequest $excuseRequest): bool
    {
        // Branch Manager Context: Global visibility
        if ($user->role === 'branch_manager') {
            return true;
        }

        // Student Context (EXC-1): Can only view their own requests
        if ($user->role === 'student') {
            return $excuseRequest->student_id === $user->id;
        }

        // Track Admin Context (EXC-3): Can view requests for students in their cohorts
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($excuseRequest) {
                    $query->where('users.id', $excuseRequest->student_id);
                })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create an excuse request.
     * * * EXC-1: Only Students can submit excuse requests.
     */
    public function create(User $user): Response
    {
        return $user->role === 'student'
            ? Response::allow()
            : Response::deny('EXC-1: Only students can submit excuse requests.');
    }

    /**
     * Determine whether the user can update an excuse request (approve/reject).
     * * * EXC-3: Only Track Admins (for their cohorts) and Branch Managers can approve/reject.
     * * * Students CANNOT modify a request after submission.
     */
    public function update(User $user, ExcuseRequest $excuseRequest): Response
    {
        // Branch Manager Context: Global authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Student Context: Blocked after submission
        if ($user->role === 'student') {
            return Response::deny('EXC-3: Students cannot modify an excuse request after submission.');
        }

        // Track Admin Context (EXC-3): Can approve/reject for students in their cohorts
        if ($user->role === 'track_admin') {
            $isMyStudent = $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($excuseRequest) {
                    $query->where('users.id', $excuseRequest->student_id);
                })->exists();

            return $isMyStudent
                ? Response::allow()
                : Response::deny('EXC-3: You can only review excuse requests from students in your assigned cohorts.');
        }

        return Response::deny('EXC-3: Only Track Admins and Branch Managers can review excuse requests.');
    }

    /**
     * Determine whether the user can delete an excuse request.
     * * * Restricted to Branch Managers only.
     */
    public function delete(User $user, ExcuseRequest $excuseRequest): Response
    {
        return $user->role === 'branch_manager'
            ? Response::allow()
            : Response::deny('Only Branch Managers can delete excuse requests.');
    }
}
