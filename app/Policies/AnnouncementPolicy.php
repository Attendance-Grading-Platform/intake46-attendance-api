<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Announcement;
use App\Models\Cohort;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * AnnouncementPolicy — Manages authorization for cohort announcements.
 * * * Business Rules: ANN-1 (Track Admin posts anytime), ANN-2 (Instructor posts during engagement window), ANN-3 (Article-style feed).
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 */
class AnnouncementPolicy
{
    /**
     * Determine whether the user can view the list of announcements.
     * Note: Collection filtering must be handled in the Controller query.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific announcement.
     * * * Contextual Authorization Pattern:
     * - Branch Managers / Track Admins / Instructors: Staff can view all announcements.
     * - Students: Can only view announcements for cohorts they are enrolled in.
     */
    public function view(User $user, Announcement $announcement): Response
    {
        // Staff roles: global visibility
        if (in_array($user->role, ['branch_manager', 'track_admin', 'instructor'])) {
            return Response::allow();
        }

        // Student Context: Can only view announcements for their enrolled cohorts
        if ($user->role === 'student') {
            return $user->enrolledCohorts()
                ->where('cohorts.id', $announcement->cohort_id)
                ->exists()
                ? Response::allow()
                : Response::deny('You can only view announcements for cohorts you are enrolled in.');
        }

        return Response::deny('Access Denied.');
    }

    /**
     * Determine whether the user can create an announcement for a specific cohort.
     * * * ANN-1: Track Admin posts to their cohort at any time.
     * * * ANN-2: Instructor posts only during their active engagement window.
     */
    public function create(User $user, Cohort $cohort): Response
    {
        // Branch Manager Context: Global authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Track Admin Context (ANN-1): Must be assigned to this specific cohort
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->where('cohorts.id', $cohort->id)
                ->exists()
                ? Response::allow()
                : Response::deny('ANN-1: You can only post announcements to cohorts you administer.');
        }

        // Instructor Context (ANN-2): Must have an active engagement linked to this cohort
        if ($user->role === 'instructor') {
            $hasActiveEngagement = $user->engagements()
                ->active()
                ->whereHas('cohorts', function ($query) use ($cohort) {
                    $query->where('cohorts.id', $cohort->id);
                })
                ->exists();

            return $hasActiveEngagement
                ? Response::allow()
                : Response::deny('ANN-2: Instructors can only post announcements during their active engagement window.');
        }

        return Response::deny('Students are not permitted to create announcements.');
    }

    /**
     * Determine whether the user can update an announcement.
     * * * Only the original author, a Track Admin assigned to the cohort, or a Branch Manager can edit.
     */
    public function update(User $user, Announcement $announcement): Response
    {
        // Branch Manager Context: Global update authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Author Context: The original author can edit their own announcement
        if ($user->id === $announcement->author_id) {
            return Response::allow();
        }

        // Track Admin Context: Must be assigned to the announcement's cohort
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->where('cohorts.id', $announcement->cohort_id)
                ->exists()
                ? Response::allow()
                : Response::deny('You can only edit announcements within cohorts you administer.');
        }

        return Response::deny('You do not have permission to edit this announcement.');
    }

    /**
     * Determine whether the user can delete an announcement.
     * * * Only Track Admins assigned to the cohort, or Branch Managers can delete.
     */
    public function delete(User $user, Announcement $announcement): Response
    {
        // Branch Manager Context: Global delete authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Track Admin Context: Must be assigned to the announcement's cohort
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->where('cohorts.id', $announcement->cohort_id)
                ->exists()
                ? Response::allow()
                : Response::deny('You can only delete announcements within cohorts you administer.');
        }

        return Response::deny('Only Track Admins and Branch Managers can delete announcements.');
    }
}