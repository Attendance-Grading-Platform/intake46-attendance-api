<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\User;
use App\Models\Grade;
use App\Models\StudentRiskFlag;
use App\Models\AttendanceRecord;

class CohortRollupService
{
    // calculate analytics for a cohort
    public function calculateAnalytics(Cohort $cohort): array
    {
        $students = $cohort->students()->get();

        // count total sessions for this cohort
        $sessionsCount = 0;
        $engagements = $cohort->engagements()->withCount('sessions')->get();
        foreach ($engagements as $engagement) {
            $sessionsCount += $engagement->sessions_count;
        }

        $studentsData = [];

        foreach ($students as $student) {
            $attendanceRate = $this->getStudentAttendanceRate($student, $cohort, $sessionsCount);
            $gpa = $this->getStudentGPA($student, $cohort);

            $isAtRisk = false;
            if ($attendanceRate < 0.85 || $gpa < 60) {
                $isAtRisk = true;
            }

            $studentsData[] = [
                'student_id' => $student->id,
                'name' => $student->name,
                'attendance_rate' => round($attendanceRate * 100, 2),
                'gpa' => round($gpa, 2),
                'is_at_risk' => $isAtRisk,
            ];
        }

        // calculate average attendance
        $avgAttendance = 0;
        if (count($studentsData) > 0) {
            $totalAttendance = 0;
            foreach ($studentsData as $s) {
                $totalAttendance += $s['attendance_rate'];
            }
            $avgAttendance = $totalAttendance / count($studentsData);
        }

        // calculate pass rate
        $passCount = 0;
        foreach ($studentsData as $s) {
            if ($s['gpa'] >= 60) {
                $passCount++;
            }
        }

        $passRate = 0;
        if (count($studentsData) > 0) {
            $passRate = ($passCount / count($studentsData)) * 100;
        }

        return [
            'meta' => [
                'cohort_id' => $cohort->id,
                'cohort_name' => $cohort->name,
                'student_count' => count($studentsData),
                'total_sessions' => $sessionsCount,
            ],
            'averages' => [
                'attendance_rate' => round($avgAttendance, 2),
                'pass_rate' => round($passRate, 2),
            ],
            'students' => $studentsData,
        ];
    }

    // save at-risk flags to database
    public function syncRiskFlags(Cohort $cohort): void
    {
        $analytics = $this->calculateAnalytics($cohort);

        foreach ($analytics['students'] as $studentData) {
            if ($studentData['is_at_risk'] == true) {
                $reasons = [];

                if ($studentData['attendance_rate'] < 85) {
                    $reasons[] = 'Attendance below 85%';
                }

                if ($studentData['gpa'] < 60) {
                    $reasons[] = 'GPA below 60';
                }

                StudentRiskFlag::updateOrCreate(
                    ['student_id' => $studentData['student_id'], 'cohort_id' => $cohort->id],
                    [
                        'at_risk' => true,
                        'reasons' => $reasons,
                        'flagged_at' => now(),
                    ]
                );
            } else {
                // student is no longer at risk, resolve the flag
                StudentRiskFlag::where('student_id', $studentData['student_id'])
                    ->where('cohort_id', $cohort->id)
                    ->update(['at_risk' => false, 'resolved_at' => now()]);
            }
        }
    }

    // calculate how many sessions the student attended
    private function getStudentAttendanceRate(User $student, Cohort $cohort, int $totalSessions): float
    {
        if ($totalSessions == 0) {
            return 0;
        }

        $presentCount = AttendanceRecord::where('student_id', $student->id)
            ->whereNotNull('arrived_at')
            ->whereHas('session.engagement.cohorts', function ($q) use ($cohort) {
                $q->where('cohorts.id', $cohort->id);
            })
            ->count();

        return $presentCount / $totalSessions;
    }

    // calculate student gpa based on grades
    private function getStudentGPA(User $student, Cohort $cohort): float
    {
        $grades = Grade::where('student_id', $student->id)
            ->whereHas('courseComponent.course', function ($q) use ($cohort) {
                $q->where('cohort_id', $cohort->id);
            })
            ->with('courseComponent')
            ->get();

        if ($grades->count() == 0) {
            return 0;
        }

        $totalEarned = 0;
        $totalWeight = 0;

        foreach ($grades as $grade) {
            $rawMax = $grade->raw_max ? (float) $grade->raw_max : 100;
            $weight = $grade->courseComponent ? (float) $grade->courseComponent->weight : 0;

            if ($rawMax > 0 && $weight > 0) {
                $totalEarned += ($grade->raw_score / $rawMax) * $weight;
                $totalWeight += $weight;
            }
        }

        if ($totalWeight == 0) {
            return 0;
        }

        return round(($totalEarned / $totalWeight) * 100, 2);
    }
}
