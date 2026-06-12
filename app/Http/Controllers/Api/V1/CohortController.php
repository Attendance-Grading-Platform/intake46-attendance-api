<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Track;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CohortController extends Controller
{
    use ApiResponse;

    /**
     * Retrieve a filtered list of cohorts based on the user's role.
     * Plugs the "List Loophole" (Cohort::all() data leak).
     *
     * GET /api/v1/cohorts
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Cohort::class);

        $user = $request->user();
        $cohorts = collect();

        $with = ['track', 'trackAdmins'];

        // Role-based visibility filtering
        if ($user->role === 'branch_manager') {
            $cohorts = Cohort::with($with)->withCount('students')->get();
        } elseif ($user->role === 'track_admin') {
            $cohorts = $user->administeredCohorts()->with($with)->withCount('students')->get();
        } elseif ($user->role === 'instructor') {
            $cohorts = Cohort::with($with)->withCount('students')->whereHas('engagements', function ($query) use ($user) {
                $query->where('engagements.instructor_id', $user->id);
            })->get();
        } elseif ($user->role === 'student') {
            $cohorts = $user->enrolledCohorts()->with($with)->withCount('students')->get();
        }

        return $this->successResponse($cohorts, 'Cohorts retrieved successfully.');
    }

    /**
     * Create a new cohort.
     *
     * POST /api/v1/cohorts
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Cohort::class);

        $validated = $request->validate([
            'track_id' => 'required|exists:tracks,id',
            'name' => 'required|string|max:100',
            'status' => 'nullable|in:active,closed',
            'started_at' => 'required|date',
            'ended_at' => 'required|date|after:started_at',
        ]);

        // LC-1: A track shall have at most one active cohort at any time.
        $requestedStatus = $validated['status'] ?? 'active';
        if ($requestedStatus === 'active') {
            $alreadyActive = Cohort::where('track_id', $validated['track_id'])
                ->where('status', 'active')
                ->exists();
            if ($alreadyActive) {
                return $this->errorResponse('This track already has an active cohort.', 422);
            }
        }

        $cohort = Cohort::create($validated);

        return $this->successResponse($cohort, 'Cohort created successfully.', 201);
    }

    /**
     * View a specific cohort.
     *
     * GET /api/v1/cohorts/{cohort}
     */
    public function show(Cohort $cohort): JsonResponse
    {
        $this->authorize('view', $cohort);

        $cohort->load(['track', 'courses.components']);
        $cohort->loadCount('students');

        return $this->successResponse($cohort, 'Cohort retrieved successfully.');
    }

    /**
     * Update a cohort.
     *
     * PUT /api/v1/cohorts/{cohort}
     */
    public function update(Request $request, Cohort $cohort): JsonResponse
    {
        $this->authorize('update', $cohort);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:active,closed',
            'started_at' => 'sometimes|date',
            'ended_at' => 'sometimes|date|after:started_at',
        ]);

        $cohort->update($validated);

        return $this->successResponse($cohort, 'Cohort updated successfully.');
    }

    /**
     * Delete a cohort.
     *
     * DELETE /api/v1/cohorts/{cohort}
     */
    public function destroy(Cohort $cohort): JsonResponse
    {
        $this->authorize('delete', $cohort);

        $cohort->delete();

        return $this->successResponse(null, 'Cohort deleted successfully.');
    }

    /**
     * Close an active cohort.
     *
     * PUT /api/v1/cohorts/{cohort}/close
     */
    public function close(Cohort $cohort): JsonResponse
    {
        $this->authorize('update', $cohort);

        if ($cohort->status === 'closed') {
            return $this->errorResponse('This cohort is already closed.', 422);
        }

        $cohort->update([
            'status' => 'closed',
            'ended_at' => now(),
        ]);

        return $this->successResponse($cohort, 'Cohort closed successfully.');
    }

    /**
     * Assign a track admin to a cohort.
     *
     * POST /api/v1/cohorts/{cohort}/assign-admin
     */
    public function assignAdmin(Request $request, Cohort $cohort): JsonResponse
    {
        $this->authorize('assignAdmin', $cohort);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if ($user && $user->role !== 'track_admin') {
                        $fail('The selected user must have the track_admin role.');
                    }
                },
            ],
        ]);

        $cohort->trackAdmins()->syncWithoutDetaching([$validated['user_id']]);

        return $this->successResponse(null, 'Track Admin assigned successfully.');
    }

    /**
     * Retrieve a filtered list of cohorts for a specific track.
     *
     * GET /api/v1/tracks/{track}/cohorts
     */
    public function trackCohorts(Request $request, Track $track): JsonResponse
    {
        $this->authorize('viewAny', Cohort::class);

        $user = $request->user();
        $query = Cohort::where('track_id', $track->id);

        // Role-based visibility filtering bounded by track_id
        if ($user->role === 'track_admin') {
            $query->whereHas('trackAdmins', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } elseif ($user->role === 'instructor') {
            $query->whereHas('engagements', function ($q) use ($user) {
                $q->where('engagements.instructor_id', $user->id);
            });
        } elseif ($user->role === 'student') {
            $query->whereHas('students', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        $cohorts = $query->get();

        return $this->successResponse($cohorts, 'Track cohorts retrieved successfully.');
    }

    /**
     * Retrieve the list of students enrolled in a specific cohort.
     *
     * GET /api/v1/cohorts/{cohort}/students
     */
    public function students(Cohort $cohort): JsonResponse
    {
        // If they are authorized to view the cohort, they can see its roster
        $this->authorize('view', $cohort);

        $students = $cohort->students()->with(['enrolledLabGroups', 'tags'])->get();

        return $this->successResponse($students, 'Cohort roster retrieved successfully.');
    }


    /**
     * Enroll a student into a cohort.
     *
     * POST /api/v1/cohorts/{cohort}/enroll
     */
    public function enroll(Request $request, Cohort $cohort): JsonResponse
    {
        // Requires update authority on the cohort (Branch Manager typically)
        $this->authorize('update', $cohort);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if ($user && $user->role !== 'student') {
                        $fail('The selected user must have the student role.');
                    }
                },
            ],
        ]);

        $cohort->students()->syncWithoutDetaching([$validated['user_id'] => ['enrolled_at' => now()]]);

        return $this->successResponse(null, 'Student enrolled successfully.');
    }

    // get all grades for students in a cohort
    // GET /api/v1/cohorts/{cohort}/grades
    public function grades(Cohort $cohort): JsonResponse
    {
        $this->authorize('view', $cohort);

        $studentIds = $cohort->students()->pluck('users.id')->toArray();

        $grades = \App\Models\Grade::whereIn('student_id', $studentIds)
            ->with(['courseComponent.course', 'student:id,name'])
            ->latest()
            ->get();

        return $this->successResponse($grades, 'Cohort grades retrieved successfully.');
    }
}

