<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AttendanceLedger;
use App\Models\AttendanceRecord;

/**
 * ATT-4..6: Automatically manage the attendance ledger when a record is saved.
 * Hardened version using Transactions (SC-3).
 */
class AttendanceRecordObserver
{
    /**
     * Called after an AttendanceRecord is created.
     */
    public function created(AttendanceRecord $record): void
    {
        // Only deduct if the student is marked absent (no scan-in)
        if ($record->arrived_at !== null) {
            return;
        }

        $cohortId = $record->session->engagement->cohorts->first()?->id;

        $ledger = AttendanceLedger::firstOrCreate(
            ['student_id' => $record->student_id],
            [
                'cohort_id' => $cohortId,
                'balance' => AttendanceLedger::INITIAL_BALANCE
            ]
        );

        // Record unexcused absence transaction
        $ledger->deductUnexcused($record->session_id);
    }

    /**
     * Called after an AttendanceRecord is updated.
     */
    public function updated(AttendanceRecord $record): void
    {
        // Case: was absent (null), now scanned-in (timestamp)
        // Transition: unexcused (-25) -> present (0)
        if ($record->getOriginal('arrived_at') === null && $record->arrived_at !== null) {
            $ledger = AttendanceLedger::where('student_id', $record->student_id)->first();

            if ($ledger) {
                // Delete the unexcused transaction if it exists
                $ledger->transactions()->where('session_id', $record->session_id)->delete();
                $ledger->recalculateBalance();
            }
        }
        
        // Case: was present (timestamp), now marked absent (null) - manual override
        if ($record->getOriginal('arrived_at') !== null && $record->arrived_at === null) {
            $ledger = AttendanceLedger::where('student_id', $record->student_id)->first();
            if ($ledger) {
                $ledger->deductUnexcused($record->session_id);
            }
        }
    }
    
    /**
     * Called after an AttendanceRecord is deleted.
     */
    public function deleted(AttendanceRecord $record): void
    {
        // Clean up any transactions linked to this session
        $ledger = AttendanceLedger::where('student_id', $record->student_id)->first();
        if ($ledger) {
            $ledger->transactions()->where('session_id', $record->session_id)->delete();
            $ledger->recalculateBalance();
        }
    }
}
