<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceLedger;
use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * StudentProgressService
 *
 * Aggregates all data needed by the Student Progress dashboard (Screen 22).
 * Keeps the controller thin; all heavy lifting lives here.
 */
class StudentProgressService
{
    /**
     * Return the complete progress payload for the given student.
     *
     * @return array{
     *   score_progression: array,
     *   attendance_trend: array,
     *   ledger_history: array,
     *   course_breakdown: array,
     *   session_record: array,
     *   final_index: float
     * }
     */
    public function getProgressData(User $student): array
    {
        return [
            'score_progression' => $this->getScoreProgression($student),
            'attendance_trend'  => $this->getAttendanceTrend($student),
            'ledger_history'    => $this->getLedgerHistory($student),
            'course_breakdown'  => $this->getCourseBreakdown($student),
            'session_record'    => $this->getSessionRecord($student),
            'final_index'       => $this->getFinalIndex($student),
        ];
    }

    /* ──────────────────────────────────────────────
     |  Score Progression (monthly grade averages)
     |──────────────────────────────────────────────*/

    /**
     * Historical grade averages aggregated by month (uses created_at).
     * Returns [{month: "2026-01", average: 72.5}, ...] — spanGaps-friendly.
     */
    private function getScoreProgression(User $student): array
    {
        $grades = Grade::where('student_id', $student->id)
            ->with('courseComponent')
            ->orderBy('created_at')
            ->get();

        if ($grades->isEmpty()) {
            return [];
        }

        // Group grades by YYYY-MM from created_at
        $grouped = $grades->groupBy(function ($grade) {
            return $grade->created_at->format('Y-m');
        });

        $result = [];

        foreach ($grouped as $month => $monthGrades) {
            $totalPercent = 0;
            $count = 0;

            foreach ($monthGrades as $grade) {
                $rawMax = $grade->raw_max ? (float) $grade->raw_max : 100;
                if ($rawMax > 0) {
                    $totalPercent += ((float) $grade->raw_score / $rawMax) * 100;
                    $count++;
                }
            }

            $result[] = [
                'month'   => $month,
                'average' => $count > 0 ? round($totalPercent / $count, 1) : 0,
            ];
        }

        return $result;
    }

    /* ──────────────────────────────────────────────
     |  Attendance Trend (last 4 weeks)
     |──────────────────────────────────────────────*/

    /**
     * The student's AttendanceRecords grouped by the last 4 weeks.
     * Calculates present vs missed per week.
     *
     * Returns [{week: "Jun 02 – Jun 08", present: 4, missed: 1}, ...]
     */
    private function getAttendanceTrend(User $student): array
    {
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::FRIDAY);
        $startDate = (clone $endOfWeek)->subWeeks(4)->startOfWeek(Carbon::SATURDAY);

        $records = AttendanceRecord::where('student_id', $student->id)
            ->whereHas('session', function ($q) use ($startDate, $endOfWeek) {
                $q->whereBetween('session_date', [$startDate->toDateString(), $endOfWeek->toDateString()]);
            })
            ->with('session')
            ->get();

        $result = [];

        for ($i = 0; $i < 4; $i++) {
            $weekStart = (clone $startDate)->addWeeks($i);
            $weekEnd   = (clone $weekStart)->addDays(6);

            $weekRecords = $records->filter(function ($record) use ($weekStart, $weekEnd) {
                $sessionDate = $record->session->session_date;
                return $sessionDate->gte($weekStart) && $sessionDate->lte($weekEnd);
            });

            $present = $weekRecords->filter(fn ($r) => $r->arrived_at !== null)->count();
            $missed  = $weekRecords->filter(fn ($r) => $r->arrived_at === null)->count();

            $result[] = [
                'week'    => $weekStart->format('M d') . ' – ' . $weekEnd->format('M d'),
                'present' => $present,
                'missed'  => $missed,
            ];
        }

        return $result;
    }

    /* ──────────────────────────────────────────────
     |  Ledger History (full semester step-down)
     |──────────────────────────────────────────────*/

    /**
     * Chronological points timeline reconstructed from AttendanceTransactions
     * starting from 250 balance. Shows the full semester step-down effect.
     *
     * Returns [{date: "2026-02-15", balance: 250, description: "Initial"}, ...]
     */
    private function getLedgerHistory(User $student): array
    {
        $ledger = $student->attendanceLedger;

        if (!$ledger) {
            return [['date' => now()->toDateString(), 'balance' => AttendanceLedger::INITIAL_BALANCE, 'description' => 'Initial Balance']];
        }

        $transactions = $ledger->transactions()
            ->orderBy('created_at')
            ->get();

        $timeline = [];
        $runningBalance = AttendanceLedger::INITIAL_BALANCE;

        // First point: initial balance at ledger creation
        $timeline[] = [
            'date'        => $ledger->created_at->toDateString(),
            'balance'     => $runningBalance,
            'description' => 'Initial Balance',
        ];

        foreach ($transactions as $tx) {
            $runningBalance = max(0, $runningBalance + (int) $tx->points);
            $timeline[] = [
                'date'        => $tx->created_at->toDateString(),
                'balance'     => $runningBalance,
                'description' => $tx->description ?? $tx->type,
            ];
        }

        return $timeline;
    }

    /* ──────────────────────────────────────────────
     |  Course Breakdown (per-course percentage)
     |──────────────────────────────────────────────*/

    /**
     * Each course the student has grades for, evaluating
     * (totalEarned / totalWeight) * 100 via CohortRollupService logic.
     *
     * Returns [{course_name: "PHP", percentage: 78.5}, ...]
     */
    private function getCourseBreakdown(User $student): array
    {
        $grades = Grade::where('student_id', $student->id)
            ->with('courseComponent.course')
            ->get();

        if ($grades->isEmpty()) {
            return [];
        }

        // Group by course
        $courseMap = [];

        foreach ($grades as $grade) {
            $component = $grade->courseComponent;
            $course    = $component?->course;

            if (!$component || !$course) {
                continue;
            }

            $courseId = $course->id;

            if (!isset($courseMap[$courseId])) {
                $courseMap[$courseId] = [
                    'course_name'  => $course->name,
                    'totalEarned'  => 0,
                    'totalWeight'  => 0,
                ];
            }

            $rawMax = $grade->raw_max ? (float) $grade->raw_max : 100;
            $weight = (float) $component->weight;

            if ($rawMax > 0 && $weight > 0) {
                $courseMap[$courseId]['totalEarned'] += ((float) $grade->raw_score / $rawMax) * $weight;
                $courseMap[$courseId]['totalWeight'] += $weight;
            }
        }

        $result = [];

        foreach ($courseMap as $data) {
            $percentage = $data['totalWeight'] > 0
                ? round(($data['totalEarned'] / $data['totalWeight']) * 100, 1)
                : 0;

            $result[] = [
                'course_name' => $data['course_name'],
                'percentage'  => $percentage,
            ];
        }

        // Sort by percentage descending for visual hierarchy
        usort($result, fn ($a, $b) => $b['percentage'] <=> $a['percentage']);

        return $result;
    }

    /* ──────────────────────────────────────────────
     |  Session Record (latest 10)
     |──────────────────────────────────────────────*/

    /**
     * Latest 10 AttendanceRecords with date, session type, and status.
     *
     * Returns [{date: "2026-06-10", type: "lecture", status: "present"}, ...]
     */
    private function getSessionRecord(User $student): array
    {
        $records = AttendanceRecord::where('student_id', $student->id)
            ->with('session.engagement')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return $records->map(function ($record) {
            $session = $record->session;
            $date    = $session?->session_date?->toDateString() ?? $record->created_at->toDateString();
            $type    = $session?->engagement?->type ?? 'session';

            // Derive status enum from the record
            if ($record->arrived_at === null) {
                $status = 'absent';
            } elseif ($record->status === 'late') {
                $status = 'late';
            } else {
                $status = 'present';
            }

            return [
                'date'   => $date,
                'type'   => $type,
                'status' => $status,
            ];
        })->values()->toArray();
    }

    /* ──────────────────────────────────────────────
     |  Final Index (unified cumulative grade 0-100)
     |──────────────────────────────────────────────*/

    /**
     * A unified cumulative grade percentage on a 0-100 scale.
     * Uses the same logic as CohortRollupService::getStudentGPA().
     */
    private function getFinalIndex(User $student): float
    {
        $grades = Grade::where('student_id', $student->id)
            ->with('courseComponent')
            ->get();

        if ($grades->isEmpty()) {
            return 0;
        }

        $totalEarned = 0;
        $totalWeight = 0;

        foreach ($grades as $grade) {
            $rawMax = $grade->raw_max ? (float) $grade->raw_max : 100;
            $weight = $grade->courseComponent ? (float) $grade->courseComponent->weight : 0;

            if ($rawMax > 0 && $weight > 0) {
                $totalEarned += ((float) $grade->raw_score / $rawMax) * $weight;
                $totalWeight += $weight;
            }
        }

        if ($totalWeight == 0) {
            return 0;
        }

        return round(($totalEarned / $totalWeight) * 100, 1);
    }
}
