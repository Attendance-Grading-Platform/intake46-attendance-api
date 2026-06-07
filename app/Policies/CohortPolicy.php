<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cohort;
use App\Models\User;

/**
 * CohortPolicy — Enforces LC-2:
 *   "Only the Branch Manager shall create cohorts and assign Track Admins."
 *
 * NOTE: This is a documented stub following the can() pattern.
 * Wire-up of Hashim's base Role middleware will not change these method
 * signatures — only the interior logic when his PR is merged.
 */
class CohortPolicy
{
    /**
     * Any authenticated user can view the list of cohorts.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users
    }

    /**
     * LC-2: Only branch_manager can create a cohort.
     */
    public function create(User $user): bool
    {
        return $user->role === 'branch_manager';
    }

    /**
     * Any authenticated user can view a cohort's details.
     */
    public function view(User $user, Cohort $cohort): bool
    {
        return true; // All authenticated users (Sanctum guard enforces auth)
    }

    /**
     * LC-2: Only branch_manager can assign a Track Admin to a cohort.
     */
    public function assignAdmin(User $user, Cohort $cohort): bool
    {
        return $user->role === 'branch_manager';
    }

    /**
     * LC-2: Only branch_manager can update a cohort.
     */
    public function update(User $user, Cohort $cohort): bool
    {
        return $user->role === 'branch_manager';
    }
}
