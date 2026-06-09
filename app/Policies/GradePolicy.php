<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * GradePolicy — Manages authorization for the grading engine.
 * * * Business Rules: GRD-4 (Instructor grouping), GRD-6 (Admin overrides), ACC-1 to ACC-4 (Visibility).
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 */
class GradePolicy
{
    /**
     * Determine whether the user can view any models.
     * Note: Collection filtering must be handled in the Controller query.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific grade.
     * * * Contextual Authorization Pattern:
     */
    public function view(User $user, Grade $grade): bool
    {
        // ACC-1: Branch Manager sees everything
        if ($user->role === 'branch_manager') {
            return true;
        }

        // ACC-4: Student sees ONLY their own grades
        if ($user->role === 'student') {
            return $grade->student_id === $user->id;
        }

        // ACC-2: Track Admin sees grades for any student in their administered cohorts
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($grade) {
                    $query->where('users.id', $grade->student_id);
                })->exists();
        }

        // ACC-3: Instructor sees grades ONLY for students in their assigned lab groups
        if ($user->role === 'instructor') {
            return $user->instructedLabGroups()
                ->whereHas('students', function ($query) use ($grade) {
                    $query->where('users.id', $grade->student_id);
                })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create a grade (submit an evaluation).
     * * * GRD-4: Instructors can only grade students in their assigned lab groups.
     */
    public function create(User $user, User $student): Response
    {
        // Students can never issue grades.
        if ($user->role === 'student') {
            return Response::deny('Students are not permitted to submit grades.');
        }

        // Branch Manager & Track Admin: Broad create authority
        if (in_array($user->role, ['branch_manager', 'track_admin'])) {
            return Response::allow();
        }

        // Instructor Context (GRD-4): Can only grade students in their assigned lab groups
        if ($user->role === 'instructor') {
            $isMyStudent = $user->instructedLabGroups()
                ->whereHas('students', function ($query) use ($student) {
                    $query->where('users.id', $student->id);
                })->exists();

            return $isMyStudent
                ? Response::allow()
                : Response::deny('GRD-4: You can only grade students in your assigned lab groups.');
        }

        return Response::deny('Access Denied.');
    }

    /**
     * Determine whether the user can update a grade.
     */
    public function update(User $user, Grade $grade): Response
    {
        // Branch Manager Context: Global update authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Track Admin Context (GRD-6): Can update/override any grade in their assigned cohort
        if ($user->role === 'track_admin') {
            $isStudentInCohort = $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($grade) {
                    $query->where('users.id', $grade->student_id);
                })->exists();

            return $isStudentInCohort
                ? Response::allow()
                : Response::deny('You can only modify grades for students in your assigned cohorts.');
        }

        // Instructor Context (GRD-4): Can only grade their assigned lab group
        if ($user->role === 'instructor') {
            $isMyStudent = $user->instructedLabGroups()
                ->whereHas('students', function ($query) use ($grade) {
                    $query->where('users.id', $grade->student_id);
                })->exists();

            if (!$isMyStudent) {
                return Response::deny('You can only modify grades for students in your assigned lab groups.');
            }

            // System Protection: Prevent instructors from overwriting a Track Admin's official override
            if ($grade->overridden_by !== null) {
                return Response::deny('This grade has been officially overridden by a Track Admin and cannot be altered.');
            }

            return Response::allow();
        }

        return Response::deny('Access Denied.');
    }

    /**
     * Determine whether the user can delete a grade.
     */
    public function delete(User $user, Grade $grade): Response
    {
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($grade) {
                    $query->where('users.id', $grade->student_id);
                })->exists()
                ? Response::allow()
                : Response::deny('You can only delete grades within your assigned cohorts.');
        }

        return Response::deny('Instructors and Students cannot delete grades.');
    }

    /**
     * Determine whether the user can restore a grade.
     */
    public function restore(User $user, Grade $grade): Response
    {
        return in_array($user->role, ['branch_manager', 'track_admin'])
            ? Response::allow()
            : Response::deny('Only management can restore deleted grades.');
    }

    /**
     * Determine whether the user can permanently delete a grade.
     */
    public function forceDelete(User $user, Grade $grade): Response
    {
        return Response::deny('System Protection: Grades cannot be permanently deleted to preserve the ledger history.');
    }
}
