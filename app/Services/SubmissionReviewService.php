<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CourseComponent;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class SubmissionReviewService
{
    /**
     * Get paginated queue of submissions for the instructor with filtering.
     */
    public function getQueue(User $instructor, array $filters): LengthAwarePaginator
    {
        $studentIds = $this->getInstructorStudentIds($instructor);

        $query = Submission::with(['student', 'courseComponent.course', 'grade'])
            ->whereIn('student_id', $studentIds);

        // Course filter
        if (!empty($filters['course_id'])) {
            $query->whereHas('courseComponent', function ($q) use ($filters) {
                $q->where('course_id', $filters['course_id']);
            });
        }

        // Lab Group filter: student must be in this lab group
        if (!empty($filters['lab_group_id'])) {
            $query->whereHas('student.enrolledLabGroups', function ($q) use ($filters) {
                $q->where('lab_groups.id', $filters['lab_group_id']);
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'graded') {
                $query->has('grade');
            } elseif ($filters['status'] === 'pending') {
                $query->doesntHave('grade');
            }
        }

        $paginator = $query->latest()->paginate(15);

        // Append 'days_since' manually
        $paginator->getCollection()->transform(function ($submission) {
            $submission->days_since = $submission->created_at 
                ? max(0, $submission->created_at->startOfDay()->diffInDays(now()->startOfDay(), false)) 
                : 0;
            return $submission;
        });

        return $paginator;
    }

    /**
     * Get high-level summary statistics for the submission dashboard.
     */
    public function getStats(User $instructor): array
    {
        $studentIds = $this->getInstructorStudentIds($instructor);

        $ungradedCount = Submission::whereIn('student_id', $studentIds)
            ->doesntHave('grade')
            ->count();

        $criticalCount = Submission::whereIn('student_id', $studentIds)
            ->doesntHave('grade')
            ->where('created_at', '<', now()->subDays(5))
            ->count();

        // Throughput and Avg Response
        $totalSubmissions = Submission::whereIn('student_id', $studentIds)->count();
        $gradedSubmissions = Submission::whereIn('student_id', $studentIds)
            ->has('grade')
            ->with('grade')
            ->get();

        $throughputPercentage = $totalSubmissions > 0 
            ? (int) round(($gradedSubmissions->count() / $totalSubmissions) * 100) 
            : 0;

        $avgResponseDays = 0;
        if ($gradedSubmissions->isNotEmpty()) {
            $totalDays = $gradedSubmissions->sum(function ($sub) {
                return max(0, $sub->created_at->startOfDay()->diffInDays($sub->grade->created_at->startOfDay(), false));
            });
            $avgResponseDays = round($totalDays / $gradedSubmissions->count(), 1);
        }

        // Missing Count (Heuristic Approximation)
        $cohortIds = $instructor->instructedLabGroups()
            ->with('students.enrolledCohorts')
            ->get()
            ->pluck('students')
            ->flatten()
            ->pluck('enrolledCohorts')
            ->flatten()
            ->pluck('id')
            ->unique();

        $deliverableComponentsCount = CourseComponent::where('type', 'lab_deliverable')
            ->whereHas('course.cohorts', function ($q) use ($cohortIds) {
                $q->whereIn('cohorts.id', $cohortIds);
            })
            ->where('due_date', '<', now())
            ->count();

        $totalStudents = count($studentIds);
        $expectedSubmissions = $deliverableComponentsCount * $totalStudents;
        
        $missingCount = max(0, $expectedSubmissions - $totalSubmissions);

        // Append filters mapping
        $availableFilters = $this->getAvailableFilters($instructor);

        return [
            'ungraded_count'        => $ungradedCount,
            'missing_count'         => $missingCount,
            'avg_response_days'     => $avgResponseDays,
            'throughput_percentage' => $throughputPercentage,
            'critical_count'        => $criticalCount,
            'available_filters'     => $availableFilters,
        ];
    }

    /**
     * Helper to get student IDs under the instructor's assigned lab groups.
     */
    private function getInstructorStudentIds(User $instructor): array
    {
        return $instructor->instructedLabGroups()
            ->with('students')
            ->get()
            ->pluck('students')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->toArray();
    }

    /**
     * Gather filters available for the given instructor.
     */
    private function getAvailableFilters(User $instructor): array
    {
        // Assigned Lab Groups
        $labGroups = $instructor->instructedLabGroups()->select('lab_groups.id', 'lab_groups.name')->get();

        // To get courses, we find any course that belongs to cohorts linked to the lab groups.
        $cohortIds = $labGroups->pluck('cohort_id')->unique();
        $courses = \App\Models\Course::whereHas('cohorts', function ($q) use ($cohortIds) {
            $q->whereIn('cohorts.id', $cohortIds);
        })->select('courses.id', 'courses.name')->get();

        return [
            'courses'    => $courses,
            'lab_groups' => $labGroups,
        ];
    }
}
