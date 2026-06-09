<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TrackController — read-only track listing.
 *
 * Tracks are created by the Branch Manager (future admin panel).
 * Any authenticated user may list tracks (with their branch context).
 */
class TrackController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/tracks
     *
     * Return all tracks, eager-loading their parent branch.
     * Future: could be filtered by branch_id query param.
     */
    public function index(): JsonResponse
    {
        $tracks = Track::with('branch')
                       ->orderBy('name')
                       ->get();

        return $this->successResponse($tracks, 'Tracks retrieved successfully.');
    }

    /**
     * DELETE /api/v1/tracks/{track}
     */
    public function destroy(Request $request, Track $track): JsonResponse
    {
        abort_if($request->user()->role !== 'branch_manager', 403, 'Only Branch Managers can delete tracks.');
        // Protection: Cannot delete if track has cohorts
        if ($track->cohorts()->exists()) {
            return $this->errorResponse('Cannot delete track with existing cohorts.', 422);
        }

        $track->delete();

        return $this->successResponse(null, 'Track deleted successfully.');
    }
}
