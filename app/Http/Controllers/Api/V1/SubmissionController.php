<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\CourseComponent;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POR-4: Student Submission Portal
 * Students use this to submit their labs as a URL or a file, and view their pending/completed assignments.
 */
class SubmissionController extends Controller
{
    use ApiResponse;

    /**
     * Fetch all deliverables/assignments for the authenticated student.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Submission::class);

        $student = $request->user();
        \Log::info('--- Fetching Assignments for Student ---', ['student_id' => $student->id]);

        // 1. Get the IDs of the cohorts the student is currently enrolled in
        $cohortIds = $student->enrolledCohorts()->pluck('cohorts.id');
        \Log::info('Student Cohorts:', ['cohort_ids' => $cohortIds->toArray()]);

        // 2. Fetch all lab deliverables for those cohorts
        $components = CourseComponent::with([
            'course',
            // Only eager-load the submission (and its grade) belonging to THIS specific student
            'submissions' => function ($query) use ($student) {
                $query->where('student_id', $student->id)->with('grade');
            }
        ])
        ->whereHas('course', function ($query) use ($cohortIds) {
            $query->whereIn('cohort_id', $cohortIds);
        })
        ->where('type', 'lab_deliverable') // Assumes your DB uses 'type' to distinguish deliverables
        ->get();

        \Log::info('Raw Course Components count:', ['count' => $components->count()]);

        // 3. Map the database structure to the exact JSON contract expected by the Vue frontend
        $mappedAssignments = $components->map(function ($component) {
            $submission = $component->submissions->first();
            $grade = $submission ? $submission->grade : null;

            // Extract the filename from the path if a physical file was uploaded
            $submittedFile = null;
            if ($submission) {
                $path = $submission->submission_file_path ?? $submission->file_path;
                $submittedFile = $path ? basename($path) : $submission->submission_url;
            }

            return [
                'id'                  => $component->id,
                'course_name'         => $component->course->name ?? 'Unknown Course',
                'title'               => $component->title ?? $component->name ?? 'Assignment', 
                'due_date'            => $component->due_date,
                'maxPoints'           => $component->max_points ?? $component->max_score ?? 100,
                // Status dynamically resolves based on whether a grade or submission exists
                'status'              => $grade ? 'graded' : ($submission ? 'submitted' : 'pending'),
                'grade'               => $grade ? $grade->score : null,
                'feedback'            => $grade ? $grade->feedback : null,
                'submitted_file'      => $submittedFile,
            ];
        });

        \Log::info('Mapped Assignments count:', ['count' => $mappedAssignments->count()]);

        return $this->successResponse($mappedAssignments, 'Assignments retrieved successfully.');
    }

    /**
     * Store a new submission.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Submission::class);

        // SC-18: Exactly one of URL or File is required. 10MB limit and PDF/Image mimes.
        $validated = $request->validate([
            'course_component_id' => 'required|exists:course_components,id',
            'submission_url'      => 'nullable|url|required_without:submission_file',
            'submission_file'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240|required_without:submission_url',
        ]);

        if ($request->filled('submission_url') && $request->hasFile('submission_file')) {
            return $this->errorResponse('You must provide EITHER a URL OR a file, not both.', 422);
        }

        $student = $request->user();
        $component = CourseComponent::findOrFail($validated['course_component_id']);

        // Guard: Student must be in the cohort of the course component
        $isEnrolled = $student->enrolledCohorts()
            ->where('cohorts.id', $component->course->cohort_id)
            ->exists();

        if (!$isEnrolled) {
            return $this->errorResponse('You are not enrolled in the cohort for this course component.', 403);
        }

        // Handle file upload
        $filePath = null;
        if ($request->hasFile('submission_file')) {
            $filePath = $request->file('submission_file')->store('submissions', 'local');
        }

        // SC-18: Automatically flag if late
        $isLate = false;
        if ($component->due_date && now()->greaterThan($component->due_date)) {
            $isLate = true;
        }

        // Create or Update (allow student to resubmit if not graded yet)
        $submission = Submission::updateOrCreate(
            [
                'student_id'          => $student->id,
                'course_component_id' => $component->id,
            ],
            [
                'submission_url'       => $validated['submission_url'] ?? null,
                'submission_file_path' => $filePath, // Fixed column name mismatch if any
                'is_late'              => $isLate,
                'submitted_at'         => now(),
            ]
        );

        return $this->successResponse($submission, 'Submission uploaded successfully.', 201);
    }
}