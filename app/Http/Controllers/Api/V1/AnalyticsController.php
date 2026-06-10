<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Services\CohortRollupService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CohortRollupService $rollupService
    ) {}

    /**
     * Get analytics summary for a cohort.
     * 
     * GET /v1/cohorts/{cohort}/analytics
     */
    public function summary(Cohort $cohort): JsonResponse
    {
        $this->authorize('view', $cohort);

        $analytics = $this->rollupService->calculateAnalytics($cohort);

        return $this->successResponse($analytics, 'Cohort analytics retrieved successfully.');
    }

    /**
     * Sync at-risk flags for a cohort.
     * 
     * POST /v1/cohorts/{cohort}/analytics/sync
     */
    public function sync(Cohort $cohort): JsonResponse
    {
        $this->authorize('update', $cohort);

        $this->rollupService->syncRiskFlags($cohort);

        return $this->successResponse(null, 'At-risk flags synchronized successfully.');
    }
}
