<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Engagement;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngagementController extends Controller
{
    use ApiResponse;

    public function index(Cohort $cohort): JsonResponse
    {
        $this->authorize('viewAny', Engagement::class);

        $engagements = $cohort->engagements()->get();

        return $this->successResponse($engagements, 'Engagements retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Engagement::class);

        $validated = $request->validate([
            'cohort_id' => 'required|exists:cohorts,id',
            'instructor_id' => 'required|exists:users,id',
            'type' => 'required|in:lecture,lab,business_session',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'scheduled_hours' => 'required|integer|min:1', 
            'days_of_week' => 'required|array',
            'days_of_week.*' => 'integer|between:0,6', // 0 = Sunday
        ]);

        $engagement = Engagement::create([
            'instructor_id' => $validated['instructor_id'],
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'scheduled_hours' => $validated['scheduled_hours'], 
            'days_of_week' => $validated['days_of_week'],
        ]);

        $engagement->cohorts()->attach($validated['cohort_id']);

        // ── ENG-3 Automation: Provision Sessions ──────────────────────────────
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $days = $validated['days_of_week'];

        $sessions = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if (in_array($date->dayOfWeek, $days)) {
                $sessions[] = [
                    'engagement_id' => $engagement->id,
                    'session_date' => $date->toDateString(),
                    'delivered' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($sessions)) {
            \App\Models\EngagementSession::insert($sessions);
        }

        return $this->successResponse($engagement->load('sessions'), 'Engagement and sessions created successfully.', 201);
    }

    public function show(Engagement $engagement): JsonResponse
    {
        $this->authorize('view', $engagement);

        return $this->successResponse($engagement, 'Engagement retrieved successfully.');
    }

    public function update(Request $request, Engagement $engagement): JsonResponse
    {
        $this->authorize('update', $engagement);

        $validated = $request->validate([
            'instructor_id' => 'sometimes|exists:users,id',
            'type' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'scheduled_hours' => 'sometimes|integer|min:1', 
        ]);

        $engagement->update($validated);

        return $this->successResponse($engagement, 'Engagement updated successfully.');
    }

    public function destroy(Engagement $engagement): JsonResponse
    {
        $this->authorize('delete', $engagement);

        $engagement->delete();

        return $this->successResponse(null, 'Engagement deleted successfully.');
    }
}
