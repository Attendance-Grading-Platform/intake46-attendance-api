<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceLedger;
use App\Models\AttendanceRecord;
use App\Models\StudentRiskFlag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * AttendanceLedgerService
 *
 * Handles ledger point deductions with idempotency guards and a floor of 0.
 */
class AttendanceLedgerService
{
    /**
     * Deduct points for an unexcused absence (-25).
     *
     * Idempotent: If the attendance record is already marked as 'absent',
     * the deduction will not be applied twice.
     * Floors the balance at 0.
     */
    public function deductUnexcused(User $student, int $sessionId, int $trackId): AttendanceLedger
    {
        return DB::transaction(function () use ($student, $sessionId, $trackId) {
            // 1. Fetch or initialize the ledger
            $ledger = AttendanceLedger::firstOrCreate(
                ['student_id' => $student->id],
                ['cohort_id' => $student->enrolledCohorts()->first()?->id ?? 1, 'balance' => 250]
            );

            // 2. Fetch or create the attendance record
            $record = AttendanceRecord::firstOrCreate(
                ['student_id' => $student->id, 'session_id' => $sessionId],
                ['track_id' => $trackId, 'status' => 'absent']
            );

            // 3. Idempotency Guard: Only deduct if we are actually transitioning to 'absent'
            // If the record was just created, it's 'absent'. If it existed but wasn't 'absent', we deduct.
            // Wait, if it was just created, we *should* deduct.
            // If it already existed and was ALREADY 'absent', it means we deducted before.
            // A reliable way is to check if it was recently created or if status was not absent.
            if ($record->wasRecentlyCreated || $record->status !== 'absent') {
                $record->update(['status' => 'absent']);

                // 4. Deduct 25, floored at 0
                $newBalance = max(0, $ledger->balance - 25);
                $ledger->update(['balance' => $newBalance]);

                // 5. Evaluate risk
                $this->evaluateRisk($student, $ledger);
            }

            return $ledger;
        });
    }

    /**
     * Deduct points for an excused absence (-5).
     *
     * Idempotent: If the attendance record is already marked as 'excused',
     * it will not be applied twice.
     * Floors the balance at 0.
     */
    public function deductExcused(User $student, int $sessionId, int $trackId): AttendanceLedger
    {
        return DB::transaction(function () use ($student, $sessionId, $trackId) {
            $ledger = AttendanceLedger::firstOrCreate(
                ['student_id' => $student->id],
                ['cohort_id' => $student->enrolledCohorts()->first()?->id ?? 1, 'balance' => 250]
            );

            $record = AttendanceRecord::firstOrCreate(
                ['student_id' => $student->id, 'session_id' => $sessionId],
                ['track_id' => $trackId, 'status' => 'excused']
            );

            if ($record->wasRecentlyCreated || $record->status !== 'excused') {
                $record->update(['status' => 'excused']);

                // Deduct 5, floored at 0
                $newBalance = max(0, $ledger->balance - 5);
                $ledger->update(['balance' => $newBalance]);

                $this->evaluateRisk($student, $ledger);
            }

            return $ledger;
        });
    }

    /**
     * Helper to evaluate risk flag threshold.
     */
    private function evaluateRisk(User $student, AttendanceLedger $ledger): void
    {
        $cohortId = $ledger->cohort_id;

        if ($ledger->isAtRisk()) {
            // Ensure flag exists
            $exists = DB::table('student_risk_flags')
                ->where('student_id', $student->id)
                ->where('cohort_id', $cohortId)
                ->whereNull('resolved_at')
                ->exists();

            if (!$exists) {
                DB::table('student_risk_flags')->insert([
                    'student_id' => $student->id,
                    'cohort_id'  => $cohortId,
                    'at_risk'    => true,
                    'reasons'    => json_encode(['Low attendance balance']),
                    'flagged_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            // Resolve any open flags if balance is somehow restored above 150
            DB::table('student_risk_flags')
                ->where('student_id', $student->id)
                ->where('cohort_id', $cohortId)
                ->whereNull('resolved_at')
                ->update([
                    'at_risk' => false,
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }
}
