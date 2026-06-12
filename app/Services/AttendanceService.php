<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;

/**
 * AttendanceService — encapsulates all business logic for the QR-scan
 * attendance workflow.
 *
 * This service is injected into AttendanceController and is the single
 * point of responsibility for:
 *   1. Creating AttendanceRecord rows (check-in / check-out).
 *   2. Determining attendance status (present / late / absent).
 *   3. Updating the student's AttendanceLedger balance.
 *   4. Evaluating and persisting StudentRiskFlags when balance < 150.
 *
 * @see ATT-1, ATT-2, ATT-3, ATT-4, ATT-5
 */
class AttendanceService
{
    /**
     * Process a QR scan event and return the resulting attendance record.
     *
     * @param  int   $sessionId  The engagement session being attended.
     * @param  int   $studentId  The student whose attendance is recorded.
     * @param  int   $trackId    The track context for this record.
     * @param  User  $scannedBy  The authenticated user who triggered the scan.
     * @return AttendanceRecord  The created or updated attendance record.
     *
     * @todo Implement full scan processing:
     *       - Create/update AttendanceRecord with arrived_at timestamp.
     *       - Determine if student is late (compare arrived_at to session start).
     *       - Deduct ledger points: -25 for absence, -5 for excused via ledger methods.
     *       - Trigger StudentRiskFlag if ledger balance drops below 150.
     */
    public function processScan(int $sessionId, int $studentId, int $trackId, User $scannedBy): AttendanceRecord
    {
        // TODO: Implement the full scan processing logic.
        //
        // Suggested implementation outline:
        //
        // return DB::transaction(function () use ($sessionId, $studentId, $trackId) {
        //     $record = AttendanceRecord::create([
        //         'session_id'  => $sessionId,
        //         'student_id'  => $studentId,
        //         'track_id'    => $trackId,
        //         'status'      => 'present',
        //         'arrived_at'  => now(),
        //     ]);
        //
        //     // Update ledger, evaluate risk flags, etc.
        //
        //     return $record;
        // });

        return AttendanceRecord::create([
            'session_id' => $sessionId,
            'student_id' => $studentId,
            'track_id'   => $trackId,
            'status'     => 'present',
            'arrived_at' => now(),
        ]);
    }
}
