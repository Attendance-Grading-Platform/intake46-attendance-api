<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionReviewResource;
use App\Models\Submission;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SubmissionReviewController extends Controller
{
    use ApiResponse;

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
            $studentIds = $user->instructedLabGroups()
                ->with('students')
                ->get()
                ->pluck('students')
                ->flatten()
                ->pluck('id')
                ->unique();
            $query->whereIn('student_id', $studentIds);

        } elseif ($user->role === 'track_admin') {
            $cohortIds = $user->administeredCohorts()->pluck('cohorts.id');
            $studentIds = User::whereHas('enrolledCohorts', function($q) use ($cohortIds) {
                $q->whereIn('cohorts.id', $cohortIds);
            })->pluck('id');
            $query->whereIn('student_id', $studentIds);

        } elseif ($user->role === 'student') {
            $query->where('student_id', $user->id);
        }

        $submissions = $query->latest()->paginate(15);

        // Extracting pagination data to keep ApiResponse format consistent
        $resourceCollection = SubmissionReviewResource::collection($submissions)->response()->getData(true);

        return $this->successResponse($resourceCollection, 'Submissions retrieved successfully.');
    }

    /**
     * Evaluate a submission by creating/updating a Grade record.
     */
    public function update(Request $request, string $id)
    {
        $submission = Submission::with('courseComponent')->findOrFail($id);

        $validated = $request->validate([
            'raw_score' => 'required|numeric|min:0',
            'raw_max'   => 'required|numeric|min:1',
        ]);

        // CRIT-7: Authorize the act of Grading, not updating the submission
        $student = User::findOrFail($submission->student_id);
        $this->authorize('create', [\App\Models\Grade::class, $student]);

        // CRIT-8: Use the validated raw_max, not the component weight
        $grade = \App\Models\Grade::updateOrCreate(
            [
                'student_id' => $submission->student_id,
                'course_component_id' => $submission->course_component_id,
            ],
            [
                'graded_by' => $request->user()->id,
                'raw_score' => $validated['raw_score'],
                'raw_max'   => $validated['raw_max'],
            ]
        );

        // Fetch the submission again with its new grade attached for the frontend resource
        $submission->load('grade');

        return $this->successResponse(
            new SubmissionReviewResource($submission),
            'Submission graded successfully.'
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
}
