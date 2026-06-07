<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

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
}
