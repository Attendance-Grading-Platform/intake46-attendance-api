<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentTag;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GRD-7, GRD-8: Manage Student Tags and instructor notes.
 *
 * Tags are descriptive (e.g., "fast-learner", "needs-support") and notes
 * are qualitative feedback visible to other instructors/admins.
 */
class StudentTagController extends Controller
{
    use ApiResponse;

    /**
     * List all tags for a specific student.
     * ACC-5: All instructors/admins can see tags for students they can view.
     */
    public function index(int $studentId): JsonResponse
    {
        $student = User::where('role', 'student')->findOrFail($studentId);
        $this->authorize('view', $student);

        $tags = StudentTag::where('student_id', $studentId)
            ->with('creator:id,name')
            ->latest()
            ->get();

        return $this->successResponse($tags, 'Student tags retrieved successfully.');
    }

    /**
     * Create a new tag/note for a student.
     */
    public function store(Request $request, int $studentId): JsonResponse
    {
        $student = User::where('role', 'student')->findOrFail($studentId);
        
        // Security: Instructor can only tag students in their assigned lab groups
        if ($request->user()->role === 'instructor') {
            $isMyStudent = $request->user()->instructedLabGroups()
                ->whereHas('students', function ($q) use ($student) {
                    $q->where('users.id', $student->id);
                })->exists();

            if (!$isMyStudent) {
                return $this->errorResponse('Security: You can only tag students in your assigned lab groups.', 403);
            }
        }

        // Policy handles Track Admin / Branch Manager logic
        $this->authorize('update', $student);

        $validated = $request->validate([
            'tag'  => 'required|string|max:50',
            'note' => 'nullable|string|max:1000',
        ]);

        $tag = StudentTag::create([
            'student_id' => $student->id,
            'creator_id' => $request->user()->id,
            'tag'        => $validated['tag'],
            'note'       => $validated['note'],
        ]);

        return $this->successResponse($tag, 'Tag information added successfully.', 201);
    }

    /**
     * Remove a tag (only by creator or admin).
     */
    public function destroy(StudentTag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return $this->successResponse(null, 'Tag removed successfully.');
    }
}
