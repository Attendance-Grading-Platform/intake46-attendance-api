<?php

namespace App\Services;

class GradeAggregationService
{
    /**
     * Normalize an individual raw score based on component weight.
     * Formula: (raw_score / raw_max) * weight
     */
    public function normalizeScore(float $rawScore, float $rawMax, float $weight): float
    {
        // law el-daraga el-3ozma b-zero aw aqal, bnrga3 zero 3ashan n-prevent el-Division by Zero error
        if ($rawMax <= 0) {
            return 0.0;
        }

        return ($rawScore / $rawMax) * $weight;
    }

    /**
     * Aggregate all components to get the total course score.
     */
    public function calculateCourseTotal(array $components): float
    {
        $total = 0.0;

        // bnelrah 3la kol component w n-normalize el-daraga bta3to w n-gam3ha 3la el-total
        foreach ($components as $component) {
            $total += $this->normalizeScore(
                $component['raw_score'],
                $component['raw_max'],
                $component['weight']
            );
        }

        return $total;
    }
}
