<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * AttendanceRecordPolicy — Manages authorization for daily attendance logs.
 * * * Business Rules: POR-2 (Student visibility), ACC-2 & EXC-3 (Admin overrides), ACC-3 (Instructor scope).
 * * Architecture: Static Contextual RBAC using Illuminate\Auth\Access\Response.
 * 
 * * POR-2: Students can only view their own session-by-session records.
 * * ACC-2 & EXC-3: Track Admins manage the roster and are responsible for approving
 * excuse requests (which involves updating the attendance record status from absent to excused).
 * * ENG-3 & ACC-3: Instructors can view and take attendance,
 * but only for the specific teaching engagements they are assigned to.
 */
class AttendanceRecordPolicy
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
     * Determine whether the user can view a specific attendance record.
     * * * Contextual Authorization Pattern:
     */
    public function view(User $user, AttendanceRecord $record): bool
    {
        // ACC-1: Branch Manager has global visibility
        if ($user->role === 'branch_manager') {
            return true;
        }

        // POR-2: Student sees ONLY their own attendance records
        if ($user->role === 'student') {
            return $record->student_id === $user->id;
        }

        // ACC-2: Track Admin sees attendance for any student in their administered cohorts
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($record) {
                    $query->where('users.id', $record->student_id);
                })->exists();
        }

        // ACC-3: Instructor sees attendance ONLY for students in their assigned lab groups
        if ($user->role === 'instructor') {
            return $user->instructedLabGroups()
                ->whereHas('students', function ($query) use ($record) {
                    $query->where('users.id', $record->student_id);
                })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create an attendance record.
     * Note: Typically created by IoT Scanners (via ScannerController) or manually by staff.
     */
    public function create(User $user): Response
    {
        // Students can never manually check themselves in via the CRUD API.
        return in_array($user->role, ['branch_manager', 'track_admin', 'instructor'])
            ? Response::allow()
            : Response::deny('Students are not permitted to manually create attendance records.');
    }

    /**
     * Determine whether the user can update an attendance record.
     */
    public function update(User $user, AttendanceRecord $record): Response
    {
        // Branch Manager Context: Global update authority
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Track Admin Context (EXC-3): Authorized to update (e.g., approving an excuse request)
        if ($user->role === 'track_admin') {
            $isMyStudent = $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($record) {
                    $query->where('users.id', $record->student_id);
                })->exists();

            return $isMyStudent
                ? Response::allow()
                : Response::deny('You can only modify attendance for students within your assigned cohorts.');
        }

        // Instructor Context (ACC-3): Can modify attendance for students in their assigned lab groups
        if ($user->role === 'instructor') {
            return $user->instructedLabGroups()
                ->whereHas('students', function ($query) use ($record) {
                    $query->where('users.id', $record->student_id);
                })->exists()
                ? Response::allow()
                : Response::deny('You can only access attendance records for students in your assigned lab groups.');
        }

        return Response::deny('Access Denied. You do not have permission to modify this record.');
    }

    /**
     * Determine whether the user can delete an attendance record.
     */
    public function delete(User $user, AttendanceRecord $record): Response
    {
        if ($user->role === 'branch_manager') {
            return Response::allow();
        }

        // Track Admins can delete erroneous records (e.g., scanner glitch) for their students
        if ($user->role === 'track_admin') {
            return $user->administeredCohorts()
                ->whereHas('students', function ($query) use ($record) {
                    $query->where('users.id', $record->student_id);
                })->exists()
                ? Response::allow()
                : Response::deny('You can only delete records within your assigned cohorts.');
        }

        return Response::deny('Instructors and Students cannot delete attendance records.');
    }
}
