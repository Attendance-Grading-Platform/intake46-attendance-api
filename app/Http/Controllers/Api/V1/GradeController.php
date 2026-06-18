<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GradeResource;
use App\Models\Cohort;
use App\Models\Grade;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    use ApiResponse;

    // list grades based on who is asking
    public function index(Request $request)
    {
        $this->authorize('viewAny', Grade::class);

        $user = $request->user();

        $query = Grade::with(['courseComponent.course']);

        // student sees only his grades
        if ($user->role == 'student') {
            $query->where('student_id', $user->id);

        } elseif ($user->role == 'instructor') {
            // instructor sees grades of students in his lab groups
            $studentIds = [];
            $labGroups = $user->instructedLabGroups()->with('students')->get();

            foreach ($labGroups as $labGroup) {
                foreach ($labGroup->students as $student) {
                    $studentIds[] = $student->id;
                }
            }

            $studentIds = array_unique($studentIds);
            $query->whereIn('student_id', $studentIds);

        } elseif ($user->role == 'track_admin') {
            // track admin sees grades of all students in his cohorts
            $cohortIds = $user->administeredCohorts()->pluck('cohorts.id')->toArray();
            $studentIds = User::whereHas('enrolledCohorts', function ($q) use ($cohortIds) {
                $q->whereIn('cohorts.id', $cohortIds);
            })->pluck('id')->toArray();

            $query->whereIn('student_id', $studentIds);
        }

        $grades = $query->latest()->get();

        foreach ($grades as $grade) {
            $grade->final_score = $grade->raw_score;
        }

        return $this->successResponse(GradeResource::collection($grades), 'Grades retrieved successfully.');
    }

    // create new grade
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', [Grade::class, $request->user()]);

        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'course_component_id' => 'required|exists:course_components,id',
            'raw_score' => 'required|numeric|min:0',
            'raw_max' => 'required|numeric|gt:0',
        ]);

        if ($validated['raw_score'] > $validated['raw_max']) {
            return $this->errorResponse('raw_score cannot be more than raw_max.', 422);
        }

        $component = \App\Models\CourseComponent::find($validated['course_component_id']);
        $normalizedScore = $validated['raw_score'];

        if ($component->type === 'lab_deliverable') {
            $submission = \App\Models\Submission::where('student_id', $validated['student_id'])
                ->where('course_component_id', $validated['course_component_id'])
                ->first();

            if ($submission && $component->due_date) {
                // Cutoff is 23:59:59 server time on the due date.
                $cutoff = \Carbon\Carbon::parse($component->due_date)->endOfDay();
                
                if ($submission->created_at->greaterThan($cutoff)) {
                    // Start of day of created_at - start of day of cutoff
                    // Example: cutoff is Jun 10 23:59. Submitted Jun 11 00:01.
                    // difference is 1 day.
                    $daysLate = $submission->created_at->startOfDay()->diffInDays($cutoff->startOfDay(), true);
                    
                    if ($daysLate == 1) {
                        $normalizedScore = $validated['raw_score'] * 0.75;
                    } elseif ($daysLate == 2) {
                        $normalizedScore = $validated['raw_score'] * 0.50;
                    } elseif ($daysLate == 3) {
                        $normalizedScore = $validated['raw_score'] * 0.25;
                    } elseif ($daysLate >= 4) {
                        $normalizedScore = 0;
                    }
                }
            }
        }

        $grade = Grade::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'course_component_id' => $validated['course_component_id'],
            ],
            [
                'graded_by' => $request->user()->id,
                'raw_score' => $validated['raw_score'],
                'raw_max' => $validated['raw_max'],
                'normalized_score' => $normalizedScore,
            ]
        );

        $grade->final_score = $grade->normalized_score;

        return $this->successResponse(new GradeResource($grade), 'Grade saved successfully.', 201);
    }

    // show one grade
    public function show(Grade $grade): JsonResponse
    {
        $this->authorize('view', $grade);

        $grade->load('courseComponent.course');
        $grade->final_score = $grade->raw_score;

        return $this->successResponse(new GradeResource($grade), 'Grade retrieved successfully.');
    }

    // update a grade
    public function update(Request $request, Grade $grade): JsonResponse
    {
        $this->authorize('update', $grade);

        $validated = $request->validate([
            'raw_score' => 'required|numeric|min:0',
            'raw_max' => 'required|numeric|gt:0',
        ]);

        if ($validated['raw_score'] > $validated['raw_max']) {
            return $this->errorResponse('raw_score cannot be more than raw_max.', 422);
        }

        $component = \App\Models\CourseComponent::find($grade->course_component_id);
        $normalizedScore = $validated['raw_score'];

        if ($component->type === 'lab_deliverable') {
            $submission = \App\Models\Submission::where('student_id', $grade->student_id)
                ->where('course_component_id', $grade->course_component_id)
                ->first();

            if ($submission && $component->due_date) {
                $cutoff = \Carbon\Carbon::parse($component->due_date)->endOfDay();
                
                if ($submission->created_at->greaterThan($cutoff)) {
                    $daysLate = $submission->created_at->startOfDay()->diffInDays($cutoff->startOfDay(), true);
                    
                    if ($daysLate == 1) {
                        $normalizedScore = $validated['raw_score'] * 0.75;
                    } elseif ($daysLate == 2) {
                        $normalizedScore = $validated['raw_score'] * 0.50;
                    } elseif ($daysLate == 3) {
                        $normalizedScore = $validated['raw_score'] * 0.25;
                    } elseif ($daysLate >= 4) {
                        $normalizedScore = 0;
                    }
                }
            }
        }

        $grade->update([
            'raw_score' => $validated['raw_score'],
            'raw_max' => $validated['raw_max'],
            'normalized_score' => $normalizedScore,
        ]);
        $grade->final_score = $grade->normalized_score;

        return $this->successResponse(new GradeResource($grade->fresh()), 'Grade updated successfully.');
    }

    // GRD-6: track admin override a grade with a note
    public function override(Request $request, Grade $grade): JsonResponse
    {
        $this->authorize('update', $grade);

        $validated = $request->validate([
            'new_score' => 'required|numeric|min:0',
            'note' => 'required|string|min:5|max:1000',
        ]);

        // dont allow override with same value
        if ((float) $grade->raw_score == (float) $validated['new_score']) {
            return $this->errorResponse('New score is the same as current score.', 422);
        }

        // save original value before override (for audit)
        $originalValue = $grade->original_value;
        if ($originalValue == null) {
            $originalValue = $grade->raw_score;
        }

        $grade->update([
            'original_value' => $originalValue,
            'raw_score' => $validated['new_score'],
            'overridden_by' => $request->user()->id,
            'override_note' => $validated['note'],
        ]);

        $freshGrade = $grade->fresh();
        $freshGrade->final_score = $freshGrade->raw_score;

        return $this->successResponse(new GradeResource($freshGrade), 'Grade overridden successfully.');
    }

    // get all grades for a student
    // GET /api/v1/students/{id}/grades
    public function studentGrades(Request $request, $id): JsonResponse
    {
        $student = User::where('role', 'student')->findOrFail($id);
        $this->authorize('view', $student);

        $grades = Grade::where('student_id', $student->id)
            ->with('courseComponent.course')
            ->latest()
            ->get();

        foreach ($grades as $grade) {
            $grade->final_score = $grade->raw_score;
        }

        // calculate grand total
        $ledger = $student->attendanceLedger;
        $ledgerBalance = $ledger ? $ledger->balance : 250;

        $coursesTotal = 0;
        foreach ($grades as $grade) {
            if ($grade->raw_max > 0 && $grade->courseComponent) {
                $coursesTotal += ($grade->raw_score / $grade->raw_max) * $grade->courseComponent->weight;
            }
        }

        $data = [
            'student' => ['id' => $student->id, 'name' => $student->name],
            'grades' => GradeResource::collection($grades),
            'grand_total' => [
                'ledger_balance' => $ledgerBalance,
                'courses_total' => round($coursesTotal, 2),
                'grand_total' => round($ledgerBalance + $coursesTotal, 2),
            ],
        ];

        return $this->successResponse($data, 'Student grades retrieved successfully.');
    }
}
