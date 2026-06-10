<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AttendanceLedger;
use App\Models\AttendanceRecord;

/**
 * ATT-4..6: Automatically manage the attendance ledger when a record is saved.
 *
 * Trigger: when an AttendanceRecord is *created* with arrived_at = null
 * (meaning the student was marked absent for that session).
 *
 * The deduction lifecycle:
 *   – Absent (no scan-in)   → -25 from ledger  (ATT-5)
 *   – Excuse approved        → +20 refund        (handled in ExcuseRequestController)
 *   – Ledger floor           → balance never goes below 0
 */
class AttendanceRecordObserver
{
    /**
     * Called after an AttendanceRecord is created.
     * If student was absent (arrived_at is null), deduct 25 from their ledger.
     */
    public function created(AttendanceRecord $record): void
    {
        // Only deduct if the student is marked absent (no scan-in)
        if ($record->arrived_at !== null) {
            return;
        }

        $ledger = AttendanceLedger::firstOrCreate(
            ['student_id' => $record->student_id],
            ['balance' => 250]
        );

        // ATT-5: Unexcused absence = -25 pts, floor at 0
        $ledger->deductUnexcused();

        // Enforce floor: balance must never go below 0
        if ($ledger->balance < 0) {
            $ledger->update(['balance' => 0]);
        }
    }

    /**
     * Called after an AttendanceRecord is updated.
     * If arrived_at transitions from null → a timestamp, the student
     * went from absent to present — refund the -25 deduction.
     */
    public function updated(AttendanceRecord $record): void
    {
        // Check: was absent before, present now
        if ($record->getOriginal('arrived_at') === null && $record->arrived_at !== null) {
            $ledger = AttendanceLedger::where('student_id', $record->student_id)->first();

            if ($ledger) {
                // Refund the -25 that was auto-deducted on creation
                $ledger->increment('balance', 25);
            }
        }
    }
}
