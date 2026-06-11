<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StudentTag;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * StudentTagPolicy — Manages authorization for student tags and notes.
 * * * Business Rules: GRD-7 (Predefined and free-text tags), GRD-8 (Accumulative across courses),
 * * * ACC-5 (Visible to everyone who grades that student).
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 */
class StudentTagPolicy
{
    /**
     * Determine whether the user can view the list of student tags.
     * Note: Collection filtering must be handled in the Controller query.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific student tag.
     * * * ACC-5: Tags are visible to every person who grades that student, internal or external.
     * * * Students are BLOCKED entirely — tags are grader-only context.
     */
    public function view(User $user, StudentTag $studentTag): bool
    {
        // Branch Manager Context: Global visibility
        if ($user->role === 'branch_manager') {
            return true;
        }

        // Student Context: Blocked — tags are grader-only information (ACC-5)
        if ($user->role === 'student') {
            return false;
        }

        // Track Admin Context (ACC-5): Can view tags for students in their administered cohorts
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($studentTag) {
                    $query->where('users.id', $studentTag->student_id);
                })->exists();
        }

        // Instructor Context (ACC-5): Can view tags for students in their assigned lab groups
        if ($user->role === 'instructor') {
            return $user->instructedLabGroups()
                ->whereHas('students', function ($query) use ($studentTag) {
                    $query->where('users.id', $studentTag->student_id);
                })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create a student tag.
     * * * GRD-7: Instructors tag students in their lab groups. Track Admins tag students in their cohorts.
     * * * Students cannot create tags.
     */
    public function create(User $user, User $student): Response
    {
        // Branch Manager Context: Global authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Track Admin Context
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($student) {
                    $query->where('users.id', $student->id);
                })->exists()
                ? Response::allow()
                : Response::deny('You can only tag students in your assigned cohorts.');
        }

        // Instructor Context
        if ($user->role === 'instructor') {
            return $user->instructedLabGroups()
                ->whereHas('students', function ($query) use ($student) {
                    $query->where('users.id', $student->id);
                })->exists()
                ? Response::allow()
                : Response::deny('You can only tag students in your assigned lab groups.');
        }

        return Response::deny('GRD-7: Only instructors and Track Admins can create student tags.');
    }

    /**
     * Determine whether the user can update an existing student tag.
     * * * Only the original tag creator (created_by) or a Branch Manager can edit.
     */
    public function update(User $user, StudentTag $studentTag): Response
    {
        // Branch Manager Context: Global update authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Creator Context: Only the person who created the tag can edit it
        if ($user->id === $studentTag->created_by) {
            return Response::allow();
        }

        return Response::deny('GRD-8: Only the original tag creator or a Branch Manager can edit a student tag.');
    }

    /**
     * Determine whether the user can delete a student tag.
     * * * Only the original creator or a Branch Manager can delete.
     */
    public function delete(User $user, StudentTag $studentTag): Response
    {
        // Branch Manager Context: Global delete authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Creator Context: Only the person who created the tag can delete it
        if ($user->id === $studentTag->created_by) {
            return Response::allow();
        }

        return Response::deny('GRD-8: Only the original tag creator or a Branch Manager can delete a student tag.');
    }
}
