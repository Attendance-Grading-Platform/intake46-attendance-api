<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EngagementSession;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SessionController — manages engagement session operations.
 *
 * PATCH /sessions/{id} — toggle or update the delivered flag.
 */
class SessionController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/engagements/{engagement}/sessions
     * Retrieve all sessions for a specific engagement.
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $engagement = \App\Models\Engagement::findOrFail($id);
        $this->authorize('view', $engagement);

        $sessions = EngagementSession::where('engagement_id', $engagement->id)
            ->orderBy('session_date')
            ->get();

        return $this->successResponse($sessions, 'Sessions retrieved successfully.');
    }

    /**
     * PATCH /api/v1/sessions/{session}
     *
     * Update the delivered flag on a session.
     * Used by instructors/admins to mark a session as delivered (BIL-1).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $session = EngagementSession::findOrFail($id);

        $this->authorize('update', $session->engagement);

        $validated = $request->validate([
            'delivered' => ['required', 'boolean'],
        ]);

        $session->update($validated);

        $session->load('engagement:id,type,start_date,end_date');

        return $this->successResponse($session, 'Session updated successfully.');
    }

    /**
     * GET /api/v1/sessions/active
     *
     * Retrieve active sessions for the authenticated instructor based on
     * the current date and time window.
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'instructor') {
            return $this->errorResponse('Only instructors can have active sessions.', 403);
        }

        $now = now();
        $currentTime = $now->format('H:i:s');
        $currentDate = $now->toDateString();

        $sessions = EngagementSession::where('session_date', $currentDate)
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->whereHas('engagement', function ($query) use ($user) {
                $query->where('instructor_id', $user->id);
            })
            ->with('engagement:id,type,instructor_id,start_date,end_date')
            ->get();

        return $this->successResponse($sessions, 'Active sessions retrieved successfully.');
    }
}
