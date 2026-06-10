<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
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
     * @param  int      $id  The student's user ID.
     * @return JsonResponse
     */
    public function studentAttendance(Request $request, int $id): JsonResponse
    {
        $student = User::where('role', 'student')->findOrFail($id);

        // Secure: Delegates to UserPolicy@view to ensure the requester
        // is either the student themselves, their track admin, or branch manager.
        $this->authorize('view', $student);

        $records = $student->attendanceRecords()
            ->with('session.engagement')
            ->orderBy('created_at', 'desc')
            ->get();

        $ledger = $student->attendanceLedger;
        $balance = $ledger ? $ledger->balance : 250;
        $isAtRisk = $ledger ? $ledger->isAtRisk() : false;

        $data = [
            'student_id'     => $student->id,
            'student_name'   => $student->name,
            'ledger_balance' => $balance,
            'is_at_risk'     => $isAtRisk,
            'records'        => $records,
        ];

        return $this->successResponse($data, 'Student attendance retrieved successfully.');
    }
}
