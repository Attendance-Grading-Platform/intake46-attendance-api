<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\GradeAuditLog;
use Illuminate\Support\Facades\DB;

class GradeOverrideService
{
    /**
     * Execute a secure administrative grade override with mandatory auditing.
     */
    public function executeOverride(Grade $grade, float $newRawScore, int $adminId, string $overrideNote): Grade
    {
        // law el-note fadya aw fiha spaces bs, bnrfa3 Exception fawran
        if (empty(trim($overrideNote))) {
            throw new \InvalidArgumentException('An override note is strictly required for auditing.');
        }

        // bn-start DB Transaction 3ashan n-guarantee in el-tadeel w el-log ytsglo sawa aw l2
        return DB::transaction(function () use ($grade, $newRawScore, $adminId, $overrideNote) {

            // 1. Create el-audit log record
            GradeAuditLog::create([
                'grade_id' => $grade->id,
                'changed_by' => $adminId,
                'old_raw_score' => $grade->raw_score,
                'new_raw_score' => $newRawScore,
                'override_note' => $overrideNote,
            ]);

            // 2. Update el-grade model raw score
            $grade->raw_score = $newRawScore;

            // 3. Re-calculate el-normalized score automatic b-esta5dam el-aggregation service
            $gradeAggregation = new GradeAggregationService();
            $grade->normalized_score = $gradeAggregation->normalizeScore(
                $newRawScore,
                $grade->raw_max,
                $grade->weight
            );

            $grade->save();

            return $grade;
        });
    }
}
