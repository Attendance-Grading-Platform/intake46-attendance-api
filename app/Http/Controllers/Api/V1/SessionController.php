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
}
