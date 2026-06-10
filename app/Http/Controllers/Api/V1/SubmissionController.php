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
 * Students use this to submit their labs as a URL or a file.
 */
class SubmissionController extends Controller
{
    use ApiResponse;

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
