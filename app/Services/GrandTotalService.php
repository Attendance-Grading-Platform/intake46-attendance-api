<?php

namespace App\Services;

class GrandTotalService
{
    /**
     * Calculate the final cumulative grand total weight based strictly on
     * the ERD's COURSE_COMPONENTS weight system.
     * * @param \Illuminate\Database\Eloquent\Collection $grades
     * @return float
     */
    public function calculateGrandTotal($grades): float
    {
        $totalEarnedPoints = 0.0;
        $totalWeightPossible = 0.0;

        foreach ($grades as $grade) {
            // Guard clause: Skip if the grade hasn't been evaluated yet
            if (is_null($grade->raw_score)) {
                continue;
            }

            // Extract values strictly defined in the ERD
            $rawScore = (float) $grade->raw_score;
            $rawMax = (float) ($grade->raw_max ?? 100.0);

            // Get the weight from the associated course component
            $componentWeight = (float) ($grade->courseComponent->weight ?? 0.0);

            if ($rawMax > 0) {
                // Calculate how many weighted points the student earned
                $percentageEarned = $rawScore / $rawMax;
                $totalEarnedPoints += ($percentageEarned * $componentWeight);
                $totalWeightPossible += $componentWeight;
            }
        }

        // If no components have weight yet, return 0 safely
        if ($totalWeightPossible === 0.0) {
            return 0.0;
        }

        // Returns the actual points earned out of the total weight
        return round($totalEarnedPoints, 2);
    }

    /**
     * Calculate the true Grand Total including the Attendance Ledger.
     *
     * Grand Total = Attendance Ledger balance + Sum of normalized course scores
     *
     * @see Section 6.1 of Vue_Laravel.md
     */
    public function calculateWithLedger(\App\Models\User $student, $grades): array
    {
        $courseTotal = $this->calculateGrandTotal($grades);

        $ledger = $student->attendanceLedger;
        $ledgerBalance = $ledger ? (int) $ledger->balance : 250;

        return [
            'ledger_balance'   => $ledgerBalance,
            'courses_total'    => $courseTotal,
            'grand_total'      => round($ledgerBalance + $courseTotal, 2),
            'is_at_risk'       => $ledger ? $ledger->isAtRisk() : false,
        ];
    }
}
