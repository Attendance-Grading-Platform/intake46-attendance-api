<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    use ApiResponse;

    /**
     * Retrieve a student's attendance records and ledger balance.
     *
     * GET /api/v1/students/{id}/attendance
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
            'student_id' => $student->id,
            'student_name' => $student->name,
            'ledger_balance' => $balance,
            'is_at_risk' => $isAtRisk,
            'records' => $records
        ];

        return $this->successResponse($data, 'Student attendance retrieved successfully.');
    }
}
