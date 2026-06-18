<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\AttendanceLedger;
use App\Models\AttendanceRecord;
use App\Models\EngagementSession;
use App\Models\ExcuseRequest;
use App\Models\User;
use App\Services\AttendanceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AttendanceController — Manages the QR-scan attendance flow and
 * student attendance retrieval within the V1 API surface.
 *
 * Architecture:
 *  - Skinny controller pattern: all point deductions, ledger mutations,
 *    and risk-flag logic live in the injected AttendanceService.
 *  - Authorization is split between the StoreAttendanceRequest (which
 *    delegates to AttendanceRecordPolicy@create) and inline Policy
 *    checks for read operations.
 *
 * @see ATT-1: QR scan-in / scan-out
 * @see ATT-4: Ledger balance retrieval
 * @see AttendanceRecordPolicy
 */
class AttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /* ──────────────────────────────────────────────────────────
     |  POST /api/v1/attendance/scan
     |──────────────────────────────────────────────────────────
     |  Processes a QR scan event (check-in or check-out).
     |
     |  The StoreAttendanceRequest handles:
     |    1. Authorization via AttendanceRecordPolicy@create
     |       (blocks students from the staff CRUD API).
     |    2. Payload validation (session_id, student_id, track_id).
     |    3. Duplicate-record and self-scan integrity checks.
     |
     |  The heavy lifting (creating the AttendanceRecord, deducting
     |  ledger points, triggering risk flags) is delegated to the
     |  AttendanceService so this controller stays skinny.
     |──────────────────────────────────────────────────────────*/

    /**
     * Record attendance from a QR scan event.
     *
     * POST /api/v1/attendance/scan
     *
     * @param  StoreAttendanceRequest  $request  Validated & authorized payload.
     * @return JsonResponse
     */
    public function scan(StoreAttendanceRequest $request): JsonResponse
    {
        // All validation and authorization already passed via the
        // FormRequest. Extract the validated payload.
        $validated = $request->validated();

        // Delegate the entire scan workflow to the service layer.
        // AttendanceService::processScan() is responsible for:
        //   1. Creating the AttendanceRecord row.
        //   2. Determining status (present vs. late threshold).
        //   3. Updating the AttendanceLedger balance.
        //   4. Evaluating and persisting StudentRiskFlags if balance < 150.

        $student = User::find($validated['student_id']);
        $trackId = 1; // Default fallback
        if ($student) {
            $cohort = $student->enrolledCohorts()->first();
            if ($cohort) {
                $trackId = $cohort->track_id;
            }
        }

        $record = $this->attendanceService->processScan(
            sessionId: (int) $validated['session_id'],
            studentId: (int) $validated['student_id'],
            trackId:   $trackId,
            scannedBy: $request->user(),
        );

        return $this->successResponse(
            $record,
            'Attendance recorded successfully.',
            201
        );
    }

    /* ──────────────────────────────────────────────────────────
     |  GET /api/v1/students/{id}/attendance
     |──────────────────────────────────────────────────────────
     |  Retrieves a student's session-by-session attendance log
     |  along with their current ledger balance and risk status.
     |
     |  Authorization is delegated to UserPolicy@view which
     |  ensures the requester is the student themselves, their
     |  track admin, or a branch manager.
     |──────────────────────────────────────────────────────────*/

    /**
     * Retrieve a student's attendance records and ledger balance.
     *
     * GET /api/v1/students/{id}/attendance
     *
     * @param  Request  $request
     * @param  int|null $id  The student's user ID.
     * @return JsonResponse
     */
    public function studentAttendance(Request $request, $id = null): JsonResponse
    {
        // if id is null, use the logged in user id
        if ($id == null) {
            $id = $request->user()->id;
        }

        $student = User::where('role', 'student')->findOrFail($id);

        $this->authorize('view', $student);

        $records = $student->attendanceRecords()->with('session.engagement')->orderBy('created_at', 'desc')->get();

        $ledger = $student->attendanceLedger;
        $balance = 250;
        $isAtRisk = false;

        if ($ledger) {
            $balance = $ledger->balance;
            $isAtRisk = $ledger->isAtRisk();
        }

        $data = [
            'student_id'     => $student->id,
            'student_name'   => $student->name,
            'ledger_balance' => $balance,
            'is_at_risk'     => $isAtRisk,
            'records'        => $records,
        ];

        return $this->successResponse($data, 'Student attendance retrieved successfully.');
    }

    // get student ledger balance
    // GET /api/v1/me/ledger  or  GET /api/v1/students/{id}/ledger
    public function studentLedger(Request $request, $id = null): JsonResponse
    {
        if ($id == null) {
            $id = $request->user()->id;
        }

        $student = User::where('role', 'student')->findOrFail($id);

        $this->authorize('view', $student);

        $ledger = $student->attendanceLedger;
        $balance = 250;
        $isAtRisk = false;

        if ($ledger) {
            $balance = $ledger->balance;
            $isAtRisk = $ledger->isAtRisk();
        }

        $data = [
            'student_id' => $student->id,
            'student_name' => $student->name,
            'balance' => $balance,
            'is_at_risk' => $isAtRisk,
        ];

        return $this->successResponse($data, 'Attendance ledger retrieved successfully.');
    }

    // get all attendance for a session
    // GET /api/v1/sessions/{session}/attendance
    public function sessionAttendance(Request $request, EngagementSession $session): JsonResponse
    {
        $user = $request->user();

        // check if user can see this session
        $canSee = false;

        if ($user->role == 'branch_manager') {
            $canSee = true;
        } elseif ($user->role == 'track_admin') {
            $canSee = true;
        } elseif ($user->role == 'instructor') {
            if ($session->engagement->instructor_id == $user->id) {
                $canSee = true;
            }
        }

        if (!$canSee) {
            return $this->errorResponse('You cannot view this session attendance.', 403);
        }

        // Get all cohorts attached to this engagement
        $cohortIds = $session->engagement->cohorts()->pluck('cohorts.id')->toArray();

        // Get all students enrolled in those cohorts
        $students = User::where('role', 'student')
            ->whereHas('enrolledCohorts', function ($q) use ($cohortIds) {
                $q->whereIn('cohorts.id', $cohortIds);
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        // Get attendance records for this session
        $records = AttendanceRecord::where('session_id', $session->getKey())
            ->get()
            ->keyBy('student_id');

        // Get excuse requests for this session
        $excuses = ExcuseRequest::where('session_id', $session->getKey())
            ->get()
            ->keyBy('student_id');

        // Combine into a roster
        $roster = $students->map(function ($student) use ($records, $excuses) {
            $record = $records->get($student->id);
            $excuse = $excuses->get($student->id);

            return [
                'student' => $student,
                'record'  => $record ? [
                    'id'         => $record->id,
                    'status'     => $record->status,
                    'arrived_at' => $record->arrived_at,
                    'left_at'    => $record->left_at,
                ] : null,
                'excuse'  => $excuse ? [
                    'id'     => $excuse->id,
                    'status' => $excuse->status,
                ] : null,
            ];
        });

        $data = [
            'session_id'   => $session->getKey(),
            'session_date' => $session->session_date,
            'records'      => $roster, // returning 'records' as the full roster to maintain frontend structure (but frontend will need updates)
        ];

        return $this->successResponse($data, 'Session attendance retrieved successfully.');
    }

    // track admin manually marks students as absent
    // POST /api/v1/sessions/{session}/mark-absent
    public function markAbsent(Request $request, EngagementSession $session): JsonResponse
    {
        $user = $request->user();

        // Allow Instructors, Track Admins, and Branch Managers to mark absent
        if (!in_array($user->role, ['track_admin', 'branch_manager', 'instructor'])) {
            return $this->errorResponse('You do not have permission to mark students absent.', 403);
        }

        if ($user->role == 'instructor' && $session->engagement->instructor_id != $user->id) {
            return $this->errorResponse('You can only mark students absent for your own sessions.', 403);
        }

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:users,id',
        ]);

        $count = 0;

        foreach ($validated['student_ids'] as $studentId) {
            $record = $this->attendanceService->markAbsent(
                $session->getKey(),
                $studentId,
                $user
            );

            if ($record) {
                $count++;
            }
        }

        return $this->successResponse(['marked_absent_count' => $count], $count . ' student(s) marked as absent.');
    }

    /**
     * GET /api/v1/me/absent-sessions
     *
     * Retrieve sessions where the student is marked absent and has not
     * yet submitted an excuse request.
     */
    public function absentSessions(Request $request): JsonResponse
    {
        $studentId = $request->user()->id;

        $absentSessionIds = AttendanceRecord::where('student_id', $studentId)
            ->where('status', 'absent')
            ->pluck('session_id');

        $excusedSessionIds = ExcuseRequest::where('student_id', $studentId)
            ->pluck('session_id');

        $eligibleSessionIds = $absentSessionIds->diff($excusedSessionIds);

        $sessions = EngagementSession::whereIn('id', $eligibleSessionIds)
            ->with('engagement:id,type')
            ->orderBy('session_date', 'desc')
            ->get(['id', 'session_date', 'engagement_id']);

        return $this->successResponse($sessions, 'Absent sessions retrieved successfully.');
    }
}
    