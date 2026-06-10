<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Grade;
use App\Models\LabGroup;
use App\Models\User;
use App\Services\CohortRollupService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ApiResponse;

    protected $rollupService;

    public function __construct(CohortRollupService $rollupService)
    {
        $this->rollupService = $rollupService;
    }

    // branch manager sees all tracks analytics
    // GET /api/v1/analytics/branch
    public function branchAnalytics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Cohort::class);

        $user = $request->user();

        if ($user->role != 'branch_manager') {
            return $this->errorResponse('Only Branch Manager can see this.', 403);
        }

        $cohorts = Cohort::with('track')->get();
        $result = [];

        foreach ($cohorts as $cohort) {
            $analytics = $this->rollupService->calculateAnalytics($cohort);
            $result[] = $analytics;
        }

        return $this->successResponse($result, 'Branch analytics retrieved successfully.');
    }

    // GET /api/v1/analytics/cohorts/{cohort}
    public function summary(Cohort $cohort): JsonResponse
    {
        $this->authorize('view', $cohort);

        $analytics = $this->rollupService->calculateAnalytics($cohort);

        return $this->successResponse($analytics, 'Cohort analytics retrieved successfully.');
    }

    // get at risk students for a cohort
    // GET /api/v1/analytics/at-risk/{cohort}
    public function atRisk(Cohort $cohort): JsonResponse
    {
        $this->authorize('view', $cohort);

        $analytics = $this->rollupService->calculateAnalytics($cohort);

        $atRiskStudents = [];
        foreach ($analytics['students'] as $student) {
            if ($student['is_at_risk'] == true) {
                $atRiskStudents[] = $student;
            }
        }

        $data = [
            'cohort_name' => $cohort->name,
            'at_risk_count' => count($atRiskStudents),
            'at_risk_students' => $atRiskStudents,
        ];

        return $this->successResponse($data, 'At-risk students retrieved successfully.');
    }

    // instructor sees his lab group analytics
    // GET /api/v1/analytics/lab-groups/{labGroup}
    public function labGroupAnalytics(LabGroup $labGroup): JsonResponse
    {
        $this->authorize('view', $labGroup);

        $students = $labGroup->students()->get();
        $studentsData = [];

        foreach ($students as $student) {
            $grades = Grade::where('student_id', $student->id)->with('courseComponent')->get();

            $totalEarned = 0;
            $totalWeight = 0;

            foreach ($grades as $grade) {
                if ($grade->raw_max > 0 && $grade->courseComponent) {
                    $totalEarned += ($grade->raw_score / $grade->raw_max) * $grade->courseComponent->weight;
                    $totalWeight += $grade->courseComponent->weight;
                }
            }

            $gpa = $totalWeight > 0 ? round($totalEarned, 2) : 0;

            $ledger = $student->attendanceLedger;
            $balance = $ledger ? $ledger->balance : 250;

            $studentsData[] = [
                'student_id' => $student->id,
                'name' => $student->name,
                'ledger_balance' => $balance,
                'gpa' => $gpa,
                'is_at_risk' => ($balance < 150 || $gpa < 60),
            ];
        }

        $data = [
            'lab_group_id' => $labGroup->id,
            'lab_group_name' => $labGroup->name,
            'students' => $studentsData,
        ];

        return $this->successResponse($data, 'Lab group analytics retrieved successfully.');
    }

    // student sees his own progress
    // GET /api/v1/me/progress
    public function myProgress(Request $request): JsonResponse
    {
        $student = $request->user();

        if ($student->role != 'student') {
            return $this->errorResponse('Only students can access this.', 403);
        }

        $grades = Grade::where('student_id', $student->id)->with('courseComponent.course')->get();

        $gradesData = [];
        foreach ($grades as $grade) {
            $component = $grade->courseComponent;
            $course = $component ? $component->course : null;

            $normalized = 0;
            if ($component && $grade->raw_max > 0) {
                $normalized = round(($grade->raw_score / $grade->raw_max) * $component->weight, 2);
            }

            $gradesData[] = [
                'course_name' => $course ? $course->name : 'N/A',
                'component_type' => $component ? $component->type : 'N/A',
                'raw_score' => $grade->raw_score,
                'raw_max' => $grade->raw_max,
                'component_weight' => $component ? $component->weight : 0,
                'normalized_score' => $normalized,
            ];
        }

        $ledger = $student->attendanceLedger;
        $balance = $ledger ? $ledger->balance : 250;

        $data = [
            'ledger_balance' => $balance,
            'grades_breakdown' => $gradesData,
        ];

        return $this->successResponse($data, 'Your progress retrieved successfully.');
    }

    // admin or manager sees a student analytics
    // GET /api/v1/students/{id}/analytics
    public function studentAnalytics(string $id): JsonResponse
    {
        $student = User::where('role', 'student')->findOrFail($id);
        $this->authorize('view', $student);

        $grades = Grade::where('student_id', $student->id)->with('courseComponent.course')->get();

        $gradesData = [];
        foreach ($grades as $grade) {
            $component = $grade->courseComponent;
            $course = $component ? $component->course : null;

            $normalized = 0;
            if ($component && $grade->raw_max > 0) {
                $normalized = round(($grade->raw_score / $grade->raw_max) * $component->weight, 2);
            }

            $gradesData[] = [
                'course_name' => $course ? $course->name : 'N/A',
                'component_type' => $component ? $component->type : 'N/A',
                'raw_score' => $grade->raw_score,
                'raw_max' => $grade->raw_max,
                'component_weight' => $component ? $component->weight : 0,
                'normalized_score' => $normalized,
            ];
        }

        $ledger = $student->attendanceLedger;
        $balance = $ledger ? $ledger->balance : 250;
        $isAtRisk = $student->isAtRisk();

        $data = [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'ledger_balance' => $balance,
            'is_at_risk' => $isAtRisk,
            'grades_breakdown' => $gradesData,
        ];

        return $this->successResponse($data, 'Student analytics retrieved successfully.');
    }
}
