<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\AttendanceLedger;

class AttendanceController extends Controller
{
    public function studentAttendance(Request $request, $id)
    {
        $student = User::where('role', 'student')->findOrFail($id);

        $this->authorize('view', $student);
        $records = $student->attendanceRecords()
            ->with('session.engagement')
            ->orderBy('created_at', 'desc')
            ->get();

        $ledger = $student->attendanceLedger;
        $balance = $ledger ? $ledger->balance : 250;
        $isAtRisk = $ledger ? $ledger->isAtRisk() : false;

        // return attendance data
        return response()->json([
            'message' => 'Student attendance retrieved successfully',
            'data' => [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'ledger_balance' => $balance,
                'is_at_risk' => $isAtRisk,
                'records' => $records
            ]
        ], 200);
    }
}