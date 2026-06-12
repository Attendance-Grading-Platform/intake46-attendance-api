<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/cohorts/{cohort}/courses
     */
    public function index(Cohort $cohort): JsonResponse
    {
        $this->authorize('viewAny', Course::class);

        $courses = $cohort->courses()->with('components')->get();
        return $this->successResponse($courses, 'Courses retrieved successfully.');
    }

    /**
     * POST /api/v1/cohorts/{cohort}/courses
     */
    public function store(Request $request, Cohort $cohort): JsonResponse
    {
        // Matches CoursePolicy@create
        $this->authorize('create', Course::class);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('courses')->where('cohort_id', $cohort->id)
            ],
        ]);

        $course = $cohort->courses()->create($validated);

        return $this->successResponse($course, 'Course created successfully.', 201);
    }

    /**
     * PUT /api/v1/courses/{course}
     */
    public function update(Request $request, Course $course): JsonResponse
    {
        // Matches CoursePolicy@update and passes context
        $this->authorize('update', $course);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('courses')->where('cohort_id', $course->cohort_id)->ignore($course->id)
            ],
        ]);

        $course->update($validated);

        return $this->successResponse($course, 'Course updated successfully.');
    }

    /**
     * POST /api/v1/courses/{course}/components
     */
    public function storeComponent(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'type'     => 'required|in:lab_deliverable,final_exam',
            'weight'   => 'required|numeric|min:0|max:100',
            'due_date' => 'nullable|date',
        ]);

        // SC-16: Total weight sum validation (100% cap)
        $currentWeight = $course->components()->sum('weight');
        if (round((float)$currentWeight + (float)$validated['weight'], 2) > 100) {
            return $this->errorResponse(
                "Total component weight exceeds 100%. Current sum: {$currentWeight}% + your new {$validated['weight']}% = " . ($currentWeight + $validated['weight']) . "%.",
                422
            );
        }

        $component = $course->components()->create($validated);

        return $this->successResponse($component, 'Course component added successfully.', 201);
    }

    /**
     * PUT /api/v1/course-components/{component}
     */
    public function updateComponent(Request $request, CourseComponent $component): JsonResponse
    {
        $this->authorize('update', $component->course);

        // SC-16: Lock weights once any grade exists for THIS specific component
        if (\App\Models\Grade::where('course_component_id', $component->id)->exists()) {
            return $this->errorResponse(
                'Cannot modify component weights after grading has started for this component.',
                422
            );
        }

        $validated = $request->validate([
            'type'     => 'sometimes|required|in:lab_deliverable,final_exam',
            'weight'   => 'sometimes|required|numeric|min:0|max:100',
            'due_date' => 'nullable|date',
        ]);

        if (isset($validated['weight'])) {
            $course = $component->course;
            $otherWeights = $course->components()->where('id', '!=', $component->id)->sum('weight');

            if (round((float)$otherWeights + (float)$validated['weight'], 2) > 100) {
                return $this->errorResponse(
                    "Weights must sum to 100%. Current other weights: {$otherWeights}%. Total would be: " . ($otherWeights + $validated['weight']) . "%.",
                    422
                );
            }
        }

        $component->update($validated);

        // Optional: Notifying if total is not yet 100? 
        // The spec implies it MUST sum to 100, but we allow partial setup.
        // We will enforce the check at time of Course Completion/Final calculation.

        return $this->successResponse($component, 'Course component updated successfully.');
    }

    /**
     * DELETE /api/v1/course-components/{component}
     */
    public function destroyComponent(CourseComponent $component): JsonResponse
    {
        $this->authorize('delete', $component->course);

        // SEC-4/SC-16: Block deletion if any grade exists for THIS specific component
        if (\App\Models\Grade::where('course_component_id', $component->id)->exists()) {
            return $this->errorResponse(
                'Cannot delete course component as grading has already started.',
                422
            );
        }

        $component->delete();

        return $this->successResponse(null, 'Course component deleted successfully.');
    }
}
