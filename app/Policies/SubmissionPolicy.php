<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * SubmissionPolicy — Manages authorization for student assignment submissions.
 * * * Business Rules: POR-4 (Student submits URL or file), GRD-4 (Instructor grades own group).
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 */
class SubmissionPolicy
{
    /**
     * Determine whether the user can view the list of submissions.
     * Note: Collection filtering must be handled in the Controller query.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific submission.
     * * * Contextual Authorization Pattern:
     * - Branch Managers: Global visibility.
     * - Track Admins: Can view submissions from students in their administered cohorts.
     * - Instructors: Can view submissions from students in their assigned lab groups (GRD-4).
     * - Students: Can only view their own submissions (ACC-4).
     */
    public function view(User $user, Submission $submission): bool
    {
        // Branch Manager Context: Global visibility
        if ($user->role === 'branch_manager') {
            return true;
        }

        // Student Context (ACC-4): Can only view their own submissions
        if ($user->role === 'student') {
            return $submission->student_id === $user->id;
        }

        // Track Admin Context: Can view submissions for students in their cohorts
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($submission) {
                    $query->where('users.id', $submission->student_id);
                })->exists();
        }

        // Instructor Context (GRD-4): Can view submissions for students in their lab groups
        if ($user->role === 'instructor') {
            return $user->instructedLabGroups()
                ->whereHas('students', function ($query) use ($submission) {
                    $query->where('users.id', $submission->student_id);
                })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create a submission.
     * * * POR-4: Students submit assignments as a URL or file upload.
     */
    public function create(User $user): Response
    {
        return $user->role === 'student'
            ? Response::allow()
            : Response::deny('POR-4: Only students can submit assignments.');
    }

    /**
     * Determine whether the user can update a submission.
     * * * Only the owning student can re-upload before the deadline.
     */
    public function update(User $user, Submission $submission): Response
    {
        if ($user->role === 'student' && $submission->student_id === $user->id) {
            return Response::allow();
        }

        return Response::deny('POR-4: Only the owning student can update their submission.');
    }

    /**
     * Determine whether the user can delete a submission.
     * * * Restricted to Branch Managers only to preserve grading audit trail.
     */
    public function delete(User $user, Submission $submission): Response
    {
        return $user->role === 'branch_manager'
            ? Response::allow()
            : Response::deny('Only Branch Managers can delete submissions.');
    }
}
