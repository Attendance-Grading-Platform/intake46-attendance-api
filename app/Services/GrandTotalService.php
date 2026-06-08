<?php

namespace App\Services;

class GrandTotalService
{
    /**
     * Calculate the final cumulative grand total weight across all track courses.
     */
    public function calculateGrandTotal(array $courses): float
    {
        $totalEarnedPoints = 0.0;
        $totalCredits = 0.0;

        foreach ($courses as $course) {
            // Edge Case: law el-grade lsa b null (matsaglesh), bna3mel skip 3ashan mazlamsh el-talib
            if (is_null($course['normalized_score'])) {
                continue;
            }

            // Earned points = score * course credit hours
            $totalEarnedPoints += ($course['normalized_score'] * $course['credits']);
            $totalCredits += $course['credits'];
        }

        // law el-talib lsa malosh wala daraga fi ay mada, bnrga3 0.0 safely
        if ($totalCredits === 0.0) {
            return 0.0;
        }

        // Weighted Average Formula: (Score1*C1 + Score2*C2) / Total Credits
        return round($totalEarnedPoints / $totalCredits, 2);
    }
}
