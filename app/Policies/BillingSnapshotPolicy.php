<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BillingSnapshot;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * BillingSnapshotPolicy — Manages authorization for billing and accounting data.
 * * * Business Rules: BIL-3 (Consolidated billing rollup), BIL-4 (Branch Manager dashboard visibility).
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 * * Note: Billing snapshots are system-generated. Manual CUD operations are restricted to Branch Managers.
 */
class BillingSnapshotPolicy
{
    /**
     * Determine whether the user can view the list of billing snapshots.
     * Note: Collection filtering must be handled in the Controller query.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific billing snapshot.
     * * * BIL-4: Branch Manager sees the billing rollup.
     * * * Track Admins can view snapshots linked to their administered cohorts.
     * * * Students and Instructors are BLOCKED entirely.
     */
    public function view(User $user, BillingSnapshot $billingSnapshot): bool
    {
        // Branch Manager Context (BIL-4): Global visibility for billing rollup
        if ($user->role === 'branch_manager') {
            return true;
        }

        // Track Admin Context: Can view billing snapshots for their administered cohorts
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->where('cohorts.id', $billingSnapshot->cohort_id)
                ->exists();
        }

        // Student & Instructor Context: Blocked — billing data is management-only
        return false;
    }

    /**
     * Determine whether the user can create a billing snapshot.
     * * * BIL-3: Billing snapshots are system-generated. Manual creation restricted to Branch Managers.
     */
    public function create(User $user): Response
    {
        return $user->role === 'branch_manager'
            ? Response::allow()
            : Response::deny('BIL-3: Billing snapshots are system-generated. Only Branch Managers can manually create them.');
    }

    /**
     * Determine whether the user can update a billing snapshot.
     * * * Restricted to Branch Managers only.
     */
    public function update(User $user, BillingSnapshot $billingSnapshot): Response
    {
        return $user->role === 'branch_manager'
            ? Response::allow()
            : Response::deny('BIL-3: Only Branch Managers can modify billing snapshots.');
    }

    /**
     * Determine whether the user can delete a billing snapshot.
     * * * Restricted to Branch Managers only.
     */
    public function delete(User $user, BillingSnapshot $billingSnapshot): Response
    {
        return $user->role === 'branch_manager'
            ? Response::allow()
            : Response::deny('BIL-3: Only Branch Managers can delete billing snapshots.');
    }
}
