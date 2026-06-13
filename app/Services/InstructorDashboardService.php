<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EngagementSession;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * InstructorDashboardService
 *
 * Aggregates all data needed by the Instructor Dashboard endpoint.
 * Keeps the controller thin; all heavy lifting lives here.
 */
class InstructorDashboardService
{
    /**
     * Return the complete dashboard payload for the given instructor.
     *
     * @return array{delivered_hours: int, lab_groups: array, grade_distribution: array}
     */
    public function getDashboardData(User $instructor): array
    {
        return [
            'delivered_hours'    => $this->calculateDeliveredHours($instructor),
            'lab_groups'         => $this->getLabGroups($instructor),
            'grade_distribution' => $this->getGradeDistribution($instructor),
        ];
    }

    /* ──────────────────────────────────────────────
     |  Delivered Hours
     |──────────────────────────────────────────────*/

    /**
     * Sum of hours_per_session for every session marked as delivered
     * across all engagements owned by this instructor.
     */
    private function calculateDeliveredHours(User $instructor): int
    {
        $total = EngagementSession::query()
            ->where('delivered', true)
            ->whereHas('engagement', fn ($q) => $q->where('instructor_id', $instructor->id))
            ->join('engagements', 'engagement_sessions.engagement_id', '=', 'engagements.id')
            ->sum('engagements.hours_per_session');

        return (int) $total;
    }

    /* ──────────────────────────────────────────────
     |  Lab Groups
     |──────────────────────────────────────────────*/

    /**
     * Lab groups assigned to this instructor via the lab_group_instructors pivot.
     * Eager-loads cohort → track so we can attach track_name.
     *
     * @return array<int, array{id: int, name: string, track_name: string}>
     */
    private function getLabGroups(User $instructor): array
    {
        return $instructor
            ->instructedLabGroups()
            ->with('cohort.track')
            ->get()
            ->map(fn ($group) => [
                'id'         => $group->id,
                'name'       => $group->name,
                'track_name' => $group->cohort?->track?->name ?? 'N/A',
            ])
            ->values()
            ->toArray();
    }

    /* ──────────────────────────────────────────────
     |  Grade Distribution
     |──────────────────────────────────────────────*/

    /**
     * Aggregate grade percentages for all students in the instructor's
     * lab groups into ITI standard buckets:
     *
     *   Excellent  >= 85
     *   Very Good  >= 75
     *   Good       >= 65
     *   Pass       >= 50
     *   Fail       <  50
     *
     * Uses (raw_score / raw_max) * 100 per grade row, then buckets.
     * Only grades with raw_max > 0 are considered.
     */
    private function getGradeDistribution(User $instructor): array
    {
        // 1. Collect all student IDs from the instructor's lab groups.
        $studentIds = DB::table('lab_group_students')
            ->whereIn('lab_group_id', function ($q) use ($instructor) {
                $q->select('lab_group_id')
                  ->from('lab_group_instructors')
                  ->where('user_id', $instructor->id);
            })
            ->distinct()
            ->pluck('user_id');

        if ($studentIds->isEmpty()) {
            return [
                'Excellent'  => 0,
                'Very Good'  => 0,
                'Good'       => 0,
                'Pass'       => 0,
                'Fail'       => 0,
            ];
        }

        // 2. Fetch all grades for these students and compute percentage per row.
        $grades = Grade::whereIn('student_id', $studentIds)
            ->where('raw_max', '>', 0)
            ->select('raw_score', 'raw_max')
            ->get();

        // 3. Bucket each grade percentage.
        $buckets = [
            'Excellent'  => 0,
            'Very Good'  => 0,
            'Good'       => 0,
            'Pass'       => 0,
            'Fail'       => 0,
        ];

        foreach ($grades as $grade) {
            $pct = ((float) $grade->raw_score / (float) $grade->raw_max) * 100;

            match (true) {
                $pct >= 85 => $buckets['Excellent']++,
                $pct >= 75 => $buckets['Very Good']++,
                $pct >= 65 => $buckets['Good']++,
                $pct >= 50 => $buckets['Pass']++,
                default    => $buckets['Fail']++,
            };
        }

        return $buckets;
    }
}
