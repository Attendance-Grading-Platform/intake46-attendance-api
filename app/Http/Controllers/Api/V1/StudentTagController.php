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
        
        // Policy handles Instructor / Track Admin / Branch Manager logic
        $this->authorize('create', [StudentTag::class, $student]);

        $validated = $request->validate([
            'tag'  => 'required|string|max:50',
            'note' => 'nullable|string|max:1000',
        ]);

        $tag = StudentTag::create([
            'student_id' => $student->id,
            'created_by' => $request->user()->id,
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

    // list notes for a student
    // GET /api/v1/students/{id}/notes
    public function listNotes(int $id): JsonResponse
    {
        $student = User::where('role', 'student')->findOrFail($id);
        $this->authorize('view', $student);

        $notes = StudentTag::where('student_id', $id)
            ->whereNotNull('note')
            ->with('creator:id,name')
            ->latest()
            ->get();

        return $this->successResponse($notes, 'Student notes retrieved successfully.');
    }

    // add a note to a student
    // POST /api/v1/students/{id}/notes
    public function storeNote(Request $request, int $id): JsonResponse
    {
        $student = User::where('role', 'student')->findOrFail($id);

        // Policy handles Instructor / Track Admin / Branch Manager logic
        $this->authorize('create', [StudentTag::class, $student]);

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
            'tag' => 'nullable|string|max:50',
        ]);

        // use 'note' as tag label if no tag provided
        $tagLabel = 'note';
        if (isset($validated['tag'])) {
            $tagLabel = $validated['tag'];
        }

        $noteRecord = StudentTag::create([
            'student_id' => $student->id,
            'created_by' => $request->user()->id,
            'tag' => $tagLabel,
            'note' => $validated['note'],
        ]);

        return $this->successResponse($noteRecord, 'Note added successfully.', 201);
    }
}
