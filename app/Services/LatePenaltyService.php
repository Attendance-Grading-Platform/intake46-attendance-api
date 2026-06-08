<?php

namespace App\Services;

class LatePenaltyService
{
    /**
     * Calculate the final raw score after applying incremental late submission penalties.
     * Rule: 25% deduction per full day late, capped at a minimum score of 0.
     */
    public function calculate(float $rawScore, int $daysLate): float
    {
        // law el-talib salem fe ma3ado aw badry, el-daraga bterga3 zay ma hya
        if ($daysLate <= 0) {
            return $rawScore;
        }

        // nesbet el-khasm = 3adad el-ayyam * 0.25 (ya3ny 25% kol yom)
        $penaltyPercentage = $daysLate * 0.25;

        // law el-khasm b2a 100% aw aktar (3and 4 ayyam aw aktar), el-natiga btb2a zero
        if ($penaltyPercentage >= 1.0) {
            return 0.0;
        }

        // law el-takhreer mn 1 l 3 ayyam, bnkhsam el-nesba mn el-daraga el-asliya
        return $rawScore * (1.0 - $penaltyPercentage);
    }
}
