<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\User;
use App\Models\Grade;
use App\Models\StudentRiskFlag;
use App\Models\AttendanceRecord;
use Illuminate\Support\Collection;

class CohortRollupService
{
    /**
     * Calculate comprehensive analytics for a cohort.
     */
    public function calculateAnalytics(Cohort $cohort): array
    {
        $students = $cohort->students()->get();
        
        // Count total sessions available for this cohort across all its engagements
        $sessionsCount = $cohort->engagements()
            ->withCount('sessions')
            ->get()
            ->sum('sessions_count');

        $studentMetrics = $students->map(function ($student) use ($cohort, $sessionsCount) {
            $attendanceRate = $this->calculateStudentAttendanceRate($student, $cohort, $sessionsCount);
            $gpa = $this->calculateStudentGPA($student, $cohort);
            
            return [
                'student_id' => $student->id,
                'name' => $student->name,
                'attendance_rate' => round($attendanceRate * 100, 2),
                'gpa' => round($gpa, 2),
                'is_at_risk' => ($attendanceRate < 0.85 || $gpa < 60),
            ];
        });

        $avgAttendance = $studentMetrics->avg('attendance_rate');
        $studentCount = $students->count();
        $passCount = $studentMetrics->where('gpa', '>=', 60)->count();
        $passRate = $studentCount > 0 ? ($passCount / $studentCount) * 100 : 0;

        return [
            'meta' => [
                'cohort_id' => $cohort->id,
                'cohort_name' => $cohort->name,
                'student_count' => $studentCount,
                'total_sessions' => $sessionsCount,
            ],
            'averages' => [
                'attendance_rate' => round($avgAttendance ?? 0, 2),
                'pass_rate' => round($passRate, 2),
            ],
            'students' => $studentMetrics,
        ];
    }

    /**
     * Identify and sync at-risk flags for a cohort.
     */
    public function syncRiskFlags(Cohort $cohort): void
    {
        $analytics = $this->calculateAnalytics($cohort);

        foreach ($analytics['students'] as $metric) {
            if ($metric['is_at_risk']) {
                $reasons = [];
                if ($metric['attendance_rate'] < 85) $reasons[] = 'Attendance below 85%';
                if ($metric['gpa'] < 60) $reasons[] = 'GPA below 60%';

                StudentRiskFlag::updateOrCreate(
                    ['student_id' => $metric['student_id'], 'cohort_id' => $cohort->id],
                    [
                        'at_risk' => true,
                        'reasons' => $reasons,
                        'flagged_at' => now(),
                    ]
                );
            } else {
                // If they were at risk but now aren't, resolve the flag
                StudentRiskFlag::where('student_id', $metric['student_id'])
                    ->where('cohort_id', $cohort->id)
                    ->update(['at_risk' => false, 'resolved_at' => now()]);
            }
        }
    }

    private function calculateStudentAttendanceRate(User $student, Cohort $cohort, int $totalSessions): float
    {
        if ($totalSessions === 0) return 0;

        $presentCount = AttendanceRecord::where('student_id', $student->id)
            ->whereHas('session.engagement.cohorts', function ($q) use ($cohort) {
                $q->where('cohorts.id', $cohort->id);
            })
            ->whereNotNull('arrived_at')
            ->count();

        return $presentCount / $totalSessions;
    }

    private function calculateStudentGPA(User $student, Cohort $cohort): float
    {
        $avg = Grade::where('user_id', $student->id)
            ->whereHas('component.course', function ($q) use ($cohort) {
                $q->where('cohort_id', $cohort->id);
            })
            ->avg('normalized_score');

        return (float) ($avg ?? 0);
    }
}
