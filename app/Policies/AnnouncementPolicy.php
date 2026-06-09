<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;
use App\Models\Cohort;
use App\Models\Engagement;

class AnnouncementPolicy
{
    /**
     * Any authenticated user can view announcements.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create an announcement for a specific cohort.
     */
    public function create(User $user, Cohort $cohort): bool
    {
        // managers can create announcements any time
        if (in_array($user->role, ['branch_manager', 'track_admin', 'admin'])) {
            return true;
        }

        // instructor must have an active engagement in this cohort
        if ($user->role === 'instructor') {
            return Engagement::where('instructor_id', $user->id)
                ->where('cohort_id', $cohort->id)
                ->active()
                ->exists();
        }

        return false;
    }
}