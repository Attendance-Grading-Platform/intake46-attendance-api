<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * UserPolicy — Manages authorization for user profiles and account lifecycle.
 * * * Business Rules: SEC-1 (Top-down provisioning), ACC-1 to ACC-4 (Visibility).
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 */
class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     * Note: Filtering the collection based on the user's role must occur within the Controller.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * * * Contextual Authorization Pattern (ACC-1 to ACC-4):
     */
    public function view(User $user, User $model): bool
    {
        // ACC-1: Branch Managers have global visibility
        if ($user->role === 'branch_manager') {
            return true;
        }

        // ACC-4: Users can always view their own data
        if ($user->id === $model->id) {
            return true;
        }

        // ACC-2: Track Admins can view users within their assigned cohorts
        if ($user->role === 'track_admin') {
            if ($model->role === 'student') {
                return $user->administeredCohorts()
                    ->whereHas('students', function ($query) use ($model) {
                        $query->where('users.id', $model->id);
                    })->exists();
            }

            // Admins can view instructor profiles to schedule them
            return true;
        }

        // ACC-3: Instructors can only view students in their assigned lab groups
        if ($user->role === 'instructor' && $model->role === 'student') {
            return $user->instructedLabGroups()
                ->whereHas('students', function ($query) use ($model) {
                        $query->where('users.id', $model->id);
                })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     * * * SEC-1: Top-down provisioning. No public self-registration.
     * * * Hierarchy: Branch Manager → Track Admins → instructors and students.
     */
    public function create(User $user, string $targetRole = 'student'): Response
    {
        // Branch Manager Context: Can provision management but NOT another Branch Manager
        if ($user->role === 'branch_manager') {
            if ($targetRole === 'branch_manager') {
                return Response::deny('SEC-1: Branch Managers cannot create other Branch Managers via API.');
            }
            return Response::allow();
        }

        // Track Admin Context: Can only provision instructors and students for THEIR tracks
        if ($user->role === 'track_admin') {
            if (in_array($targetRole, ['instructor', 'student'])) {
                return Response::allow();
            }
            return Response::deny('SEC-1: Track Admins can only create instructor or student accounts.');
        }

        return Response::deny('SEC-1: Only management can provision new accounts.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): Response
    {
        // Branch Manager Context: Global update authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Self Context: Users can update their own profile (e.g., changing password)
        if ($user->id === $model->id) {
            return Response::allow();
        }

        // Track Admin Context: Can update students in their cohorts
        if ($user->role === 'track_admin' && $model->role === 'student') {
            $isStudentInCohort = $user->administeredCohorts()
                    ->whereHas('students', function ($query) use ($model) {
                        $query->where('users.id', $model->id);
                    })->exists();

            return $isStudentInCohort
                ? Response::allow()
                : Response::deny('You can only update students within your assigned cohorts.');
        }

        return Response::deny('You do not have permission to modify this account.');
    }

    /**
     * Determine whether the user can delete the model.
     * Note: This performs a Soft Delete.
     */
    public function delete(User $user, User $model): Response
    {
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        return Response::deny('Only the Branch Manager can deactivate or delete accounts.');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): Response
    {
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        return Response::deny('Only the Branch Manager can restore deactivated accounts.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): Response
    {
        // System Protection: Enforces the database rule "Never hard-delete a user"
        return Response::deny('System Protection: Users cannot be permanently deleted to preserve the grading ledger.');
    }
}
