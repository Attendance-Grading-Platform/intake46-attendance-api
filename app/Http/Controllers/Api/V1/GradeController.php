<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of grades securely scoped by role.
     */
    public function index(Request $request)
    {
        // 1. Policy Gate
        $this->authorize('viewAny', Grade::class);

        $user = $request->user();

        // 2. ERD Alignment: Eager load through CourseComponent
        $query = Grade::with(['courseComponent.course']);

        // 3. Strict Query Isolation
        if ($user->role === 'student') {
            $query->where('student_id', $user->id);

        } elseif ($user->role === 'instructor') {
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
        }

        $grades = $query->latest()->get();

        // 4. Safely apply a final_score for the Teammate's GradeResource
        // Since late penalties are applied during the Submission grading process,
        // the Grade's raw_score is effectively the final evaluated score.
        foreach ($grades as $grade) {
            $grade->final_score = $grade->raw_score;
        }

        return $this->successResponse(
            GradeResource::collection($grades),
            'Grades retrieved successfully.'
        );
    }

    /**
     * GRD-6: Override a student's grade with a mandatory audit note.
     * Only accessible to Track Admins (enforced via Policy/Role).
     */
    public function override(Request $request, Grade $grade): JsonResponse
    {
        // 1. Policy Authorization (requires track_admin role)
        $this->authorize('update', $grade);

        $validated = $request->validate([
            'new_score' => 'required|numeric|min:0',
            'note'      => 'required|string|min:5|max:1000',
        ]);

        // Guard: Prevent redundant overrides with same value
        if ((float) $grade->raw_score === (float) $validated['new_score']) {
            return $this->errorResponse('New score is identical to current score.', 422);
        }

        // 2. Audit Trail: Always preserve the FIRST original value
        // if this is the first override.
        $originalValue = $grade->original_value ?? $grade->raw_score;

        $grade->update([
            'original_value' => $originalValue,
            'raw_score'      => $validated['new_score'],
            'overridden_by'  => $request->user()->id,
            'override_note'  => $validated['note'],
        ]);

        return $this->successResponse(
            new GradeResource($grade->fresh()),
            'Grade successfully overridden. Record updated in audit trail.'
        );
    }
}
