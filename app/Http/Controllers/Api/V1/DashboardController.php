<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\InstructorDashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DashboardController — returns pre-aggregated dashboard payloads.
 *
 * GET /api/v1/me/instructor-dashboard
 */
class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly InstructorDashboardService $dashboardService,
    ) {}

    /**
     * GET /api/v1/me/instructor-dashboard
     *
     * Returns the Instructor Dashboard payload:
     *  - delivered_hours  (int)
     *  - lab_groups       (array)
     *  - grade_distribution (object)
     */
    public function instructorDashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'instructor') {
            return $this->errorResponse('This endpoint is for instructors only.', 403);
        }

        $data = $this->dashboardService->getDashboardData($user);

        return $this->successResponse($data, 'Instructor dashboard data retrieved.');
    }
}
