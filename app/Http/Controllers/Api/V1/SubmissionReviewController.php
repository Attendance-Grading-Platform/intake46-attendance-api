<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionReviewResource;
use App\Models\Submission;
use App\Models\User;
use App\Services\SubmissionReviewService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SubmissionReviewController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SubmissionReviewService $submissionReviewService
    ) {}

    /**
     * Display a listing of student submissions securely isolated to the instructor.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Submission::class);

        $user = $request->user();
        $query = Submission::with(['student', 'course']);

        // Strict Query Isolation
        if ($user->role === 'instructor') {
            // SC-15/D3: Only students in lab groups assigned to this instructor
            $studentIds = $user->instructedLabGroups()
                ->with('students')
                ->get()
                ->pluck('students')
                ->flatten()
                ->pluck('id')
                ->unique();
            $query->whereIn('student_id', $studentIds);

        } elseif ($user->role === 'track_admin') {
            // SEC-1: Only students in cohorts where this admin is assigned
            $cohortIds = $user->administeredCohorts()->pluck('cohorts.id');
            $studentIds = User::whereHas('enrolledCohorts', function($q) use ($cohortIds) {
                $q->whereIn('cohorts.id', $cohortIds);
            })->pluck('id');
            $query->whereIn('student_id', $studentIds);

        } elseif ($user->role === 'student') {
            // SEC-1: Students only see their own work
            $query->where('student_id', $user->id);
        }

        $submissions = $query->latest()->paginate(15);

        // Extracting pagination data to keep ApiResponse format consistent
        $resourceCollection = SubmissionReviewResource::collection($submissions)->response()->getData(true);

        return $this->successResponse($resourceCollection, 'Submissions retrieved successfully.');
    }

    /**
     * GET /api/v1/submissions/queue
     * Display a filtered queue of submissions specifically for the instructor dashboard.
     */
    public function queue(Request $request)
    {
        $this->authorize('viewAny', Submission::class);
        $user = $request->user();

        if ($user->role !== 'instructor') {
            return $this->errorResponse('Only instructors can access the submission queue.', 403);
        }

        $filters = $request->only(['course_id', 'lab_group_id', 'status']);
        $submissions = $this->submissionReviewService->getQueue($user, $filters);

        // Keep the API Response format consistent
        $resourceCollection = SubmissionReviewResource::collection($submissions)->response()->getData(true);

        return $this->successResponse($resourceCollection, 'Submission queue retrieved successfully.');
    }

    /**
     * GET /api/v1/submissions/stats
     * Display summary statistics for the instructor dashboard.
     */
    public function stats(Request $request)
    {
        $this->authorize('viewAny', Submission::class);
        $user = $request->user();

        if ($user->role !== 'instructor') {
            return $this->errorResponse('Only instructors can access submission stats.', 403);
        }

        $stats = $this->submissionReviewService->getStats($user);

        return $this->successResponse($stats, 'Submission stats retrieved successfully.');
    }

    /**
     * Evaluate a submission by creating/updating a Grade record.
     * ENG-2: Late penalty is auto-applied based on CourseComponent due_date.
     */
    public function update(Request $request, string $id)
    {
        $submission = Submission::with('courseComponent')->findOrFail($id);

        $validated = $request->validate([
            'raw_score' => 'required|numeric|min:0',
            'raw_max'   => 'required|numeric|min:1',
        ]);

        // raw_score must not exceed raw_max
        if ($validated['raw_score'] > $validated['raw_max']) {
            return $this->errorResponse('raw_score cannot exceed raw_max.', 422);
        }

        // CRIT-7: Authorize the act of Grading, not updating the submission
        $student = User::findOrFail($submission->student_id);
        $this->authorize('create', [\App\Models\Grade::class, $student]);

        // ENG-2: Apply late penalty using CourseComponent.due_date vs submission.created_at
        $dueDate     = $submission->courseComponent?->due_date;
        $submittedAt = $submission->created_at ?? now();
        $daysLate    = 0;

        if ($dueDate) {
            $daysLate = max(0, (int) now()->parse($submittedAt)
                ->startOfDay()
                ->diffInDays(now()->parse($dueDate)->startOfDay(), false));
        }

        $penaltyService  = new \App\Services\LatePenaltyService();
        $finalScore      = $penaltyService->calculate((float) $validated['raw_score'], $daysLate);

        // CRIT-8: Save the post-penalty score as raw_score for normalization pipeline
        $grade = \App\Models\Grade::updateOrCreate(
            [
                'student_id'          => $submission->student_id,
                'course_component_id' => $submission->course_component_id,
            ],
            [
                'graded_by'  => $request->user()->id,
                'raw_score'  => $finalScore,
                'raw_max'    => $validated['raw_max'],
            ]
        );

        $submission->load('grade');

        return $this->successResponse(
            [
                'submission'       => new SubmissionReviewResource($submission),
                'penalty_applied'  => [
                    'days_late'    => $daysLate,
                    'original_raw' => $validated['raw_score'],
                    'final_score'  => $finalScore,
                ],
            ],
            $daysLate > 0 ? "Graded with late penalty ({$daysLate} days late)." : 'Submission graded successfully.'
        );
    }

    /**
     * Complex GET Endpoint: Get a detailed complex breakdown of a specific student's grades.
     */
    public function studentGradesDetail(string $id)
    {
        $student = User::where('role', 'student')->findOrFail($id);

        // POLICY GATE: Ensure instructor is authorized to view this specific student's record
        $this->authorize('view', $student);

        // Fetch grades and eager-load the course component and its parent course
        $grades = \App\Models\Grade::where('student_id', $student->id)
            ->with('courseComponent.course')
            ->get();

        $report = $grades->map(function ($grade) {
            $rawScore = $grade->raw_score ?? 0;
            $maxScore = $grade->raw_max ?? 100;

            return [
                'course_name' => $grade->courseComponent->course->name ?? 'Unknown',
                'component_id' => $grade->course_component_id,
                'raw_score' => $rawScore,
                'max_score' => $maxScore,
                // If you have a separate penalty service, it can be applied here
                'final_score' => $rawScore,
            ];
        });

        $data = [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'grades_breakdown' => $report
        ];

        return $this->successResponse($data, 'Student detailed grade analytics retrieved successfully.');
    }

    /**
     * GET /api/v1/engagements/{id}/deliverables
     * Filter submissions specifically for a given engagement.
     */
    public function engagementDeliverables(Request $request, string $id)
    {
        $engagement = \App\Models\Engagement::findOrFail($id);
        $this->authorize('view', $engagement);

        // Fetch students from the cohort associated with this engagement
        $cohortIds = $engagement->cohorts()->pluck('cohorts.id');
        $studentIds = User::whereHas('enrolledCohorts', function($q) use ($cohortIds) {
            $q->whereIn('cohorts.id', $cohortIds);
        })->pluck('id');

        $submissions = Submission::whereIn('student_id', $studentIds)
            ->with(['student', 'courseComponent'])
            ->latest()
            ->paginate(15);

        $resourceCollection = SubmissionReviewResource::collection($submissions)->response()->getData(true);
        return $this->successResponse($resourceCollection, 'Engagement submissions retrieved successfully.');
    }
}
