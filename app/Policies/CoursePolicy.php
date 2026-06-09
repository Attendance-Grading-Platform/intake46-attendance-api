<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * CoursePolicy — Manages authorization for course resources.
 * * * Business Rule: "Track Admin configures courses, grade weights, lab groups".
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 * * Scope: Single-branch system (Branch Manager possesses global authority).
 */
class CoursePolicy
{
    /**
     * Determine whether the user can view the list of courses.
     * Note: Filtering the collection based on the user's role must occur within the Controller's query builder.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view specific course details.
     * * * Contextual Authorization Pattern:
     * - Branch Managers: Global access.
     * - Track Admins: Access if assigned to the cohort via `cohort_track_admins`.
     * - Instructors: Access if assigned to engagements within the cohort.
     * - Students: Access if enrolled in the cohort via `cohort_students`.
     */
    public function view(User $user, Course $course): bool
    {
        // Branch Manager Context: Global visibility in single-branch system
        if ($user->role === 'branch_manager') {
            return true;
        }

        // Track Admin Context: Must be assigned as an admin for the owning cohort
        if ($user->role === 'track_admin') {
            return $course->cohort->trackAdmins()->where('user_id', $user->id)->exists();
        }

        // Instructor Context: Must have scheduled engagements for the owning cohort
        if ($user->role === 'instructor') {
            return $course->cohort->engagements()->where('instructor_id', $user->id)->exists();
        }

        // Student Context: Must be enrolled in the cohort that owns the course
        if ($user->role === 'student') {
            return $course->cohort->students()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create a course.
     * * * Authorization: Restricted to management or the Track Admin responsible for the cohort.
     */
    public function create(User $user): Response
    {
        return in_array($user->role, ['branch_manager', 'track_admin'])
            ? Response::allow()
            : Response::deny('Only Track Admins and Branch Managers can create courses.');
    }

    /**
     * Determine whether the user can update course configurations.
     * * * Contextual Pattern:
     * - Branch Managers: Global update authority.
     * - Track Admins: Authorized if assigned as an admin for the specific cohort containing the course.
     */
    public function update(User $user, Course $course): Response
    {
        // Branch Manager Context: Global update authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Track Admin Context: Must be assigned to the specific cohort containing the course
        if ($user->role === 'track_admin') {
            return $course->cohort->trackAdmins()->where('user_id', $user->id)->exists()
                ? Response::allow()
                : Response::deny('You are not assigned as an admin for this cohort.');
        }

        return Response::deny('Only Track Admins can configure courses.');
    }

    /**
     * Determine whether the user can delete a course.
     * * * Authorization: Restricted to management administrative roles.
     */
    public function delete(User $user, Course $course): Response
    {
        // Branch Manager Context: Global delete authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Track Admin Context: Must be assigned to the specific cohort containing the course
        if ($user->role === 'track_admin') {
            return $course->cohort->trackAdmins()->where('user_id', $user->id)->exists()
                ? Response::allow()
                : Response::deny('You are not assigned as an admin for this cohort.');
        }

        return Response::deny('Only management can delete courses.');
    }
}
