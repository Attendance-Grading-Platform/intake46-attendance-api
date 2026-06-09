<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cohort;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * CohortPolicy — Enforces LC-2 and System Access Control Rules.
 * * * Business Rule LC-2: "Only the Branch Manager shall create cohorts and assign Track Admins."
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 * * Scope: Single-branch system (Branch Manager possesses global authority).
 */
class CohortPolicy
{
    /**
     * Determine whether the user can view the list of cohorts.
     * * Note: Any authenticated user can access the index route.
     * Filtering the returned collection based on the user's role must occur within the Controller's query builder.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific cohort's details.
     * * * Contextual Authorization Pattern:
     * - Branch Managers: Allowed globally (System serves a single branch).
     * - Track Admins: Allowed if explicitly assigned to the cohort via the `cohort_track_admins` pivot.
     * - Instructors: Allowed if they are assigned to any teaching engagements scheduled for this cohort.
     * - Students: Allowed if they are actively enrolled in the cohort via the `cohort_students` pivot.
     */
    public function view(User $user, Cohort $cohort): bool
    {
        // Branch Managers see everything in the single-branch system
        if ($user->role === 'branch_manager') {
            return true;
        }

        if ($user->role === 'track_admin') {
            return $cohort->trackAdmins()->where('user_id', $user->id)->exists();
        }

        if ($user->role === 'instructor') {
            // Instructor sees the cohort if they have any engagements scheduled for it
            return $cohort->engagements()->where('engagements.instructor_id', $user->id)->exists();
        }

        if ($user->role === 'student') {
            return $cohort->students()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create a cohort.
     * * * Enforces LC-2: Only the branch_manager is permitted to initialize a new cohort.
     */
    public function create(User $user): Response
    {
        return $user->role === 'branch_manager'
            ? Response::allow()
            : Response::deny('Only the Branch Manager can create cohorts.');
    }

    /**
     * Determine whether the user can assign a Track Admin to a cohort.
     * * * Enforces LC-2: Only the branch_manager is permitted to staff Track Admins.
     */
    public function assignAdmin(User $user, Cohort $cohort): Response
    {
        return $user->role === 'branch_manager'
            ? Response::allow()
            : Response::deny('Only the Branch Manager can assign Track Admins.');
    }

    /**
     * Determine whether the user can update a cohort.
     * * * Contextual Pattern: Because the system serves one single branch,
     * a Branch Manager has global administrative rights over cohort settings without needing a branch_id check.
     */
    public function update(User $user, Cohort $cohort): Response
    {
        // Notice we don't need a branch_id check anymore. The system serves one branch.
        return $user->role === 'branch_manager'
            ? Response::allow()
            : Response::deny('Only the Branch Manager can update cohort settings.');
    }

    /**
     * Determine whether the user can delete a cohort.
     */
    public function delete(User $user, Cohort $cohort): Response
    {
        return $user->role === 'branch_manager'
            ? Response::allow()
            : Response::deny('Only the Branch Manager can delete cohorts.');
    }
}
