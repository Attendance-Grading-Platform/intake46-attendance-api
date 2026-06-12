<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEngagementRequest;
use App\Models\Engagement;
use App\Services\EngagementService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngagementController extends Controller
{
    use ApiResponse;

    public function __construct(protected EngagementService $engagementService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Engagement::class);

        $engagements = Engagement::with(['instructor:id,name,email', 'cohorts', 'sessions'])
            ->when($request->cohort_id, fn ($q) => $q->whereHas('cohorts', fn ($sq) => $sq->where('cohorts.id', $request->cohort_id)))
            ->when($request->instructor_id, fn ($q) => $q->where('instructor_id', $request->instructor_id))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('start_date')
            ->get();

        return $this->successResponse($engagements, 'Engagements retrieved successfully.');
    }

    public function store(StoreEngagementRequest $request): JsonResponse
    {
        $this->authorize('create', Engagement::class);

        $validated  = $request->validated();
        $daysOfWeek = $validated['days_of_week'];

        $result = $this->engagementService->createWithSessions(
            data: $validated,
            daysOfWeek: $daysOfWeek,
        );

        return $this->successResponse(
            data: [
                'engagement' => $result['engagement'],
                'sessions'   => $result['sessions'],
                'sessions_count' => $result['sessions']->count(),
            ],
            message: sprintf(
                'Engagement created successfully with %d session(s) generated.',
                $result['sessions']->count(),
            ),
            code: 201,
        );
    }

    public function show(Engagement $engagement): JsonResponse
    {
        $this->authorize('view', $engagement);

        $engagement->load([
            'instructor:id,name,email',
            'cohorts',
            'sessions' => fn ($q) => $q->orderBy('session_date'),
        ]);

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
