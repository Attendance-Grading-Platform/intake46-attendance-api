<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionReviewResource;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;

class SubmissionReviewController extends Controller
{
    /**
     * Display a listing of student submissions for the instructor.
     */
    public function index(Request $request)
    {
        // Fetch submissions with eager-loaded student and course details for the dashboard table
        $submissions = Submission::with(['student', 'course'])
            ->latest()
            ->paginate(15);

        return SubmissionReviewResource::collection($submissions);
    }

    /**
     * Update the grade or status of a specific submission.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);

        $submission = Submission::findOrFail($id);

        $submission->update([
            'score' => $request->score,
            'feedback' => $request->feedback,
            'status' => 'graded',
        ]);

        return response()->json([
            'message' => 'Submission graded successfully.',
            'data' => new SubmissionReviewResource($submission)
        ]);
    }

    /**
     * Complex GET Endpoint: Get a detailed complex breakdown of a specific student's grades.
     */
    public function studentGradesDetail(string $id)
    {
        // Fetch the student with user role or abort if not found
        $student = User::where('role', 'student')->findOrFail($id);

        // Load grades with course and lab submission relationships
        $grades = $student->grades()->with(['course', 'labSubmission'])->get();

        // Map the breakdown components (Raw, Max, Normalized, Penalty)
        $report = $grades->map(function ($grade) {
            $rawScore = $grade->score;
            $maxScore = $grade->labSubmission->max_score ?? 100;
            $penaltyAmount = $grade->late_penalty ?? 0;
            $normalizedScore = $grade->normalized_score ?? $rawScore;

            return [
                'course_name' => $grade->course?->name,
                'component_id' => $grade->id,
                'raw_score' => $rawScore,
                'max_score' => $maxScore,
                'penalty_amount' => $penaltyAmount,
                'normalized_score' => $normalizedScore,
                'final_score' => $grade->final_score ?? ($rawScore - $penaltyAmount),
            ];
        });

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'grades_breakdown' => $report
        ]);
    }
}
