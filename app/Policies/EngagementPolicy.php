<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Engagement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * EngagementPolicy — Manages authorization for scheduling and teaching assignments.
 * * * Business Rules: ENG-1 (Top-down scheduling), ACC-3 (Instructor isolation), POR-1 (Student visibility).
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 */
class EngagementPolicy
{
    /**
     * Determine whether the user can view any models.
     * Note: Collection filtering must be handled in the Controller query builder.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific engagement.
     * * * Contextual Authorization Pattern:
     */
    public function view(User $user, Engagement $engagement): bool
    {
        // Branch Manager Context: Global visibility
        if ($user->role === 'branch_manager') {
            return true;
        }

        // Instructor Context (ACC-3): Can ONLY view their own teaching assignments
        if ($user->role === 'instructor') {
            return $engagement->instructor_id === $user->id;
        }

        // Track Admin Context: Can view engagements scheduled for ANY of their assigned cohorts
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->whereHas('engagements', function ($query) use ($engagement) {
                    $query->where('engagements.id', $engagement->id);
                })->exists();
        }

        // Student Context (POR-1): Can view engagements scheduled for cohorts they are enrolled in
        if ($user->role === 'student') {
            return $user->enrolledCohorts()
                ->whereHas('engagements', function ($query) use ($engagement) {
                    $query->where('engagements.id', $engagement->id);
                })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     * * * ENG-1: Only management can build the schedule.
     */
    public function create(User $user): Response
    {
        return in_array($user->role, ['branch_manager', 'track_admin'])
            ? Response::allow()
            : Response::deny('ENG-1: Only Track Admins and Branch Managers can schedule new engagements.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Engagement $engagement): Response
    {
        // Branch Manager Context: Global update authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Track Admins can only modify engagements that are linked to their assigned cohorts
        if ($user->role === 'track_admin') {
            $isMyCohort = $user->administeredCohorts()
                ->whereHas('engagements', function ($query) use ($engagement) {
                    $query->where('engagements.id', $engagement->id);
                })->exists();

            return $isMyCohort
                ? Response::allow()
                : Response::deny('You can only modify the schedule for your assigned cohorts.');
        }

        return Response::deny('Instructors and Students cannot modify the official schedule.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Engagement $engagement): Response
    {
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        if ($user->role === 'track_admin') {
            $isMyCohort = $user->administeredCohorts()
                ->whereHas('engagements', function ($query) use ($engagement) {
                    $query->where('engagements.id', $engagement->id);
                })->exists();

            return $isMyCohort
                ? Response::allow()
                : Response::deny('You can only cancel engagements for your assigned cohorts.');
        }

        return Response::deny('Only management can cancel scheduled engagements.');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Engagement $engagement): Response
    {
        return in_array($user->role, ['branch_manager', 'track_admin'])
            ? Response::allow()
            : Response::deny('Only management can restore canceled engagements.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Engagement $engagement): Response
    {
        // System Protection: Prevent hard deletions to ensure billing (BIL-1) and attendance ledgers remain intact.
        return Response::deny('System Protection: Engagements cannot be permanently deleted to preserve billing and attendance history.');
    }
}
