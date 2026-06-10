<?php

namespace App\Policies;

use App\Models\LabGroup;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LabGroupPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LabGroup $labGroup): bool
    {
        if ($user->role === 'branch_manager') {
            return true;
        }

        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()->where('cohorts.id', $labGroup->cohort_id)->exists();
        }

        if ($user->role === 'instructor') {
            return $labGroup->instructors()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['branch_manager', 'track_admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LabGroup $labGroup): bool
    {
        if ($user->role === 'branch_manager') {
            return true;
        }

        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()->where('cohorts.id', $labGroup->cohort_id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LabGroup $labGroup): bool
    {
        return $this->update($user, $labGroup);
    }
}
