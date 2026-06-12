<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\EngagementSession;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ATT-1: QR Code check-in and check-out scanner endpoint.
 *
 * Public fast-path — no auth token required.
 * Validations:
 *  - Cannot scan-in twice for the same session.
 *  - Cannot scan-out before scanning in.
 *  - Ledger -25 deduction fires automatically via AttendanceRecord observer on save.
 */
class ScannerController extends Controller
{
    use ApiResponse;

    /**
     * ATT-1: Record a student's scan-in (arrived_at) for a session.
     *
     * POST /api/scan/checkin
     * Body: { student_id: int, session_id: int }
     */
    public function checkin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'session_id' => 'required|exists:engagement_sessions,id',
        ]);

        $session = EngagementSession::with('engagement.cohorts')->findOrFail($validated['session_id']);

        // Security: Verify student is enrolled in the cohort(s) that own this session/engagement
        $isEnrolled = $session->engagement->cohorts()->whereHas('students', function ($q) use ($validated) {
            $q->where('users.id', $validated['student_id']);
        })->exists();

        if (!$isEnrolled) {
            return $this->errorResponse('Security: Student is not enrolled in this cohort.', 403);
        }

        // Guard: Already scanned in?
        $existing = AttendanceRecord::where('student_id', $validated['student_id'])
            ->where('session_id', $validated['session_id'])
            ->first();

        if ($existing && $existing->arrived_at !== null) {
            return $this->errorResponse('Student has already scanned in for this session.', 422);
        }

        $record = AttendanceRecord::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'session_id' => $validated['session_id'],
            ],
            ['arrived_at' => now()]
        );

        return $this->successResponse(
            ['arrived_at' => $record->arrived_at],
            'Check-in recorded. Arrived at ' . $record->arrived_at->toTimeString()
        );
    }

    /**
     * ATT-1: Record a student's scan-out (left_at) for a session.
     *
     * POST /api/scan/checkout
     * Body: { student_id: int, session_id: int }
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'session_id' => 'required|exists:engagement_sessions,id',
        ]);

        $session = EngagementSession::with('engagement.cohorts')->findOrFail($validated['session_id']);

        // Security: Verify student is enrolled
        $isEnrolled = $session->engagement->cohorts()->whereHas('students', function ($q) use ($validated) {
            $q->where('users.id', $validated['student_id']);
        })->exists();

        if (!$isEnrolled) {
            return $this->errorResponse('Security: Student is not enrolled in this cohort.', 403);
        }

        // Guard: Must have scanned in first
        $record = AttendanceRecord::where('student_id', $validated['student_id'])
            ->where('session_id', $validated['session_id'])
            ->first();

        if (!$record || $record->arrived_at === null) {
            return $this->errorResponse('Cannot check out — student has not scanned in for this session.', 422);
        }

        if ($record->left_at !== null) {
            return $this->errorResponse('Student has already scanned out for this session.', 422);
        }

        $record->update(['left_at' => now()]);

        return $this->successResponse(
            ['left_at' => $record->left_at],
            'Check-out recorded. Left at ' . $record->left_at->toTimeString()
        );
    }
}
