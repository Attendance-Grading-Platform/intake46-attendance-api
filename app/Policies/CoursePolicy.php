<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Track Admin can manage courses for their assigned track.
     * (Stubbed for now, using role check)
     */
    public function manage(User $user): bool
    {
        return in_array($user->role, ['branch_manager', 'track_admin']);
    }

    /**
     * Anyone authenticated can view courses.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }
}
