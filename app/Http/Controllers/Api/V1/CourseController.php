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
        $this->authorize('manage', Course::class);

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
        $this->authorize('manage', Course::class);

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
        $this->authorize('manage', Course::class);

        $validated = $request->validate([
            'type'     => 'required|in:lab_deliverable,final_exam',
            'weight'   => 'required|numeric|min:0|max:100',
            'due_date' => 'nullable|date',
        ]);

        // Business Rule: Total weight must not exceed 100
        $currentWeight = $course->components()->sum('weight');
        if (round((float)$currentWeight + (float)$validated['weight'], 2) > 100) {
            throw ValidationException::withMessages([
                'weight' => "Total component weight exceeds 100 (Current: {$currentWeight}).",
            ]);
        }

        $component = $course->components()->create($validated);

        return $this->successResponse($component, 'Course component added successfully.', 201);
    }

    /**
     * PUT /api/v1/course-components/{component}
     */
    public function updateComponent(Request $request, CourseComponent $component): JsonResponse
    {
        $this->authorize('manage', Course::class);

        $validated = $request->validate([
            'type'     => 'sometimes|required|in:lab_deliverable,final_exam',
            'weight'   => 'sometimes|required|numeric|min:0|max:100',
            'due_date' => 'nullable|date',
        ]);

        if (isset($validated['weight'])) {
            $course = $component->course;
            $otherWeights = $course->components()->where('id', '!=', $component->id)->sum('weight');
            
            if (round((float)$otherWeights + (float)$validated['weight'], 2) > 100) {
                throw ValidationException::withMessages([
                    'weight' => "Total component weight exceeds 100 (Other components: {$otherWeights}).",
                ]);
            }
        }

        $component->update($validated);

        return $this->successResponse($component, 'Course component updated successfully.');
    }
}
