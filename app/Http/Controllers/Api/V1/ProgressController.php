<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StudentProgressService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProgressController — returns pre-aggregated student progress data.
 *
 * GET /api/v1/me/progress
 */
class ProgressController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StudentProgressService $progressService,
    ) {}

    /**
     * GET /api/v1/me/progress
     *
     * Returns the Student Progress payload:
     *  - score_progression  (array)
     *  - attendance_trend   (array)
     *  - ledger_history     (array)
     *  - course_breakdown   (array)
     *  - session_record     (array)
     *  - final_index        (float)
     */
    public function studentProgress(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'student') {
            return $this->errorResponse('This endpoint is for students only.', 403);
        }

        $data = $this->progressService->getProgressData($user);

        return $this->successResponse($data, 'Student progress data retrieved.');
    }
}
