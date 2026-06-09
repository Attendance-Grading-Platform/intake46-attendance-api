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

/**
 * EngagementController — manages the Engagement lifecycle.
 *
 * ENG-3: Create an engagement (with automatic session generation).
 * ENG-5: Instructors only see data for their active engagements.
 *
 * Pattern: Skinny controller → delegates business logic to EngagementService.
 */
class EngagementController extends Controller
{
    use ApiResponse;

    public function __construct(protected EngagementService $engagementService) {}

    /**
     * GET /api/v1/engagements
     *
     * List engagements. Optionally filter by cohort_id, instructor_id, or type.
     */
    public function index(Request $request): JsonResponse
    {
        $engagements = Engagement::with([
                'instructor:id,name,email',
                'cohort:id,name',
                'sessions',
            ])
            ->when($request->cohort_id, fn ($q) => $q->where('cohort_id', $request->cohort_id))
            ->when($request->instructor_id, fn ($q) => $q->where('instructor_id', $request->instructor_id))
            ->when($request->type, fn ($q) => $q->ofType($request->type))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderByDesc('start_date')
            ->get();

        return $this->successResponse($engagements, 'Engagements retrieved successfully.');
    }

    /**
     * POST /api/v1/engagements
     *
     * Create a new engagement and auto-generate its sessions based on
     * the provided days_of_week within the start_date → end_date range.
     *
     * @see StoreEngagementRequest  for validation rules
     * @see EngagementService       for transactional logic
     */
    public function store(StoreEngagementRequest $request): JsonResponse
    {
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

    /**
     * GET /api/v1/engagements/{engagement}
     *
     * Return a single engagement with its sessions and instructor.
     */
    public function show(Engagement $engagement): JsonResponse
    {
        $engagement->load([
            'instructor:id,name,email',
            'cohort:id,name',
            'sessions' => fn ($q) => $q->orderBy('session_date'),
        ]);

        return $this->successResponse($engagement, 'Engagement retrieved successfully.');
    }
}
