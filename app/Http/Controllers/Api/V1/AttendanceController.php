<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\EngagementSession;
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

    /**
     * Record attendance from a QR scan event.
     *
     * POST /api/v1/attendance/scan
     */
    public function scan(StoreAttendanceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $record = $this->attendanceService->processScan(
            sessionId: (int) $validated['session_id'],
            studentId: (int) $validated['student_id'],
            trackId:   (int) $validated['track_id'],
            scannedBy: $request->user(),
        );

        return $this->successResponse($record, 'Attendance recorded successfully.', 201);
    }

    /**
     * Retrieve a student's attendance records and ledger balance.
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
}
    