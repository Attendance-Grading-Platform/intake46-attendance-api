<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionReviewResource;
use App\Models\Submission; // Adjust if your model name is LabSubmission or Assignment
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
}
