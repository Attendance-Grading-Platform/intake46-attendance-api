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

        $record = $this->attendanceService->processScan(
            sessionId: (int) $validated['session_id'],
            studentId: (int) $validated['student_id'],
            trackId:   (int) $validated['track_id'],
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

        $records = AttendanceRecord::where('session_id', $session->getKey())
            ->with('student:id,name,email')
            ->orderBy('arrived_at')
            ->get();

        $data = [
            'session_id' => $session->getKey(),
            'session_date' => $session->session_date,
            'records' => $records,
        ];

        return $this->successResponse($data, 'Session attendance retrieved successfully.');
    }

    // track admin manually marks students as absent
    // POST /api/v1/sessions/{session}/mark-absent
    public function markAbsent(Request $request, EngagementSession $session): JsonResponse
    {
        $user = $request->user();

        if ($user->role != 'track_admin' && $user->role != 'branch_manager') {
            return $this->errorResponse('Only Track Admins can mark students absent.', 403);
        }

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:users,id',
        ]);

        $count = 0;

        foreach ($validated['student_ids'] as $studentId) {
            // check if record already exists
            $existing = AttendanceRecord::where('student_id', $studentId)
                ->where('session_id', $session->getKey())
                ->first();

            if (!$existing) {
                // create absent record - observer will deduct 25 from ledger
                AttendanceRecord::create([
                    'student_id' => $studentId,
                    'session_id' => $session->getKey(),
                    'arrived_at' => null,
                    'left_at' => null,
                ]);
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
    