<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewExcuseRequest;
use App\Http\Requests\StoreExcuseRequest;
use App\Models\AttendanceLedger;
use App\Models\AttendanceRecord;
use App\Models\ExcuseRequest;
use App\Services\ExcuseService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;



/**
 * ExcuseRequestController — Manages the excuse request lifecycle
 * within the V1 API surface.
 *
 * Workflow (state machine):
 *   requested  ──→  approved   (Track Admin / Branch Manager)
 *              └──→  rejected   (Track Admin / Branch Manager)
 *
 * Architecture:
 *  - Skinny controller pattern: file uploads, ledger point adjustments,
 *    attendance record status mutations, and risk-flag re-evaluation
 *    all live in the injected ExcuseService.
 *  - Authorization is split between:
 *      • StoreExcuseRequest   → ExcuseRequestPolicy@create  (student only)
 *      • ReviewExcuseRequest  → ExcuseRequestPolicy@update   (track_admin / branch_manager, cohort-scoped)
 *      • Inline $this->authorize() calls for read and delete operations.
 *
 * @see EXC-1: Student submits excuse
 * @see EXC-3: Track Admin approves/rejects excuse
 * @see ExcuseRequestPolicy
 */
class ExcuseRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ExcuseService $excuseService,
    ) {}

    /* ──────────────────────────────────────────────────────────
     |  GET /api/v1/excuse-requests
     |──────────────────────────────────────────────────────────
     |  Lists excuse requests scoped to the authenticated user's
     |  role and organizational context:
     |    - Students:      own requests only.
     |    - Track Admins:  requests from students in their cohorts.
     |    - Branch Manager: all requests.
     |──────────────────────────────────────────────────────────*/

    /**
     * List excuse requests filtered by the authenticated user's scope.
     *
     * GET /api/v1/excuse-requests
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExcuseRequest::class);

        $user = $request->user();

        $query = ExcuseRequest::with(['student:id,name,email', 'student.enrolledLabGroups:id,name', 'session.engagement', 'reviewer:id,name']);

        // EXC-1: Students see only their own requests
        if ($user->role === 'student') {
            $query->where('student_id', $user->id);
        }

        // EXC-3: Track Admins see requests from students in their administered cohorts
        if ($user->role === 'track_admin') {
            $cohortIds = $user->administeredCohorts()->pluck('cohorts.id');
            $query->whereHas('student.enrolledCohorts', function ($q) use ($cohortIds) {
                $q->whereIn('cohorts.id', $cohortIds);
            });
        }

        // Branch Managers see all requests (no additional filter needed)

        return $this->successResponse(
            $query->latest()->get(),
            'Excuse requests retrieved successfully.'
        );
    }

    /* ──────────────────────────────────────────────────────────
     |  POST /api/v1/excuse-requests
     |──────────────────────────────────────────────────────────
     |  Student submits a new excuse request for a missed session.
     |
     |  The StoreExcuseRequest handles:
     |    1. Authorization via ExcuseRequestPolicy@create
     |       (only students may submit).
     |    2. Payload validation (session_id, reason, optional attachment).
     |    3. Duplicate-submission guard (one excuse per session per student).
     |
     |  File storage and any side-effects are delegated to ExcuseService.
     |──────────────────────────────────────────────────────────*/

    /**
     * Submit a new excuse request.
     *
     * POST /api/v1/excuse-requests
     *
     * @param  StoreExcuseRequest  $request  Validated & authorized payload.
     * @return JsonResponse
     */
    public function store(StoreExcuseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Delegate to the service layer. ExcuseService::submitExcuse()
        // handles file upload storage and creates the ExcuseRequest record
        // with status = 'requested'.
        $excuse = $this->excuseService->submitExcuse(
            student:    $request->user(),
            sessionId:  (int) $validated['session_id'],
            reason:     $validated['reason'],
            attachment: $request->file('attachment'),
        );

        return $this->successResponse(
            $excuse->load(['student:id,name', 'session']),
            'Excuse request submitted successfully.',
            201
        );
    }

    /* ──────────────────────────────────────────────────────────
     |  GET /api/v1/excuse-requests/{excuse}
     |──────────────────────────────────────────────────────────*/

    /**
     * View a specific excuse request.
     *
     * GET /api/v1/excuse-requests/{excuse}
     *
     * @param  ExcuseRequest  $excuse  Route-model-bound instance.
     * @return JsonResponse
     */
    public function show(ExcuseRequest $excuse): JsonResponse
    {
        $this->authorize('view', $excuse);

        return $this->successResponse(
            $excuse->load(['student:id,name,email', 'session', 'reviewer:id,name']),
            'Excuse request retrieved successfully.'
        );
    }

    /* ──────────────────────────────────────────────────────────
     |  PATCH /api/v1/excuse-requests/{excuse}
     |──────────────────────────────────────────────────────────
     |  Track Admin (or Branch Manager) reviews the excuse by
     |  setting its status to 'approved' or 'rejected'.
     |
     |  The ReviewExcuseRequest handles:
     |    1. Authorization via ExcuseRequestPolicy@update
     |       (contextual RBAC: admin must own the student's cohort).
     |    2. Status validation (must be 'approved' or 'rejected').
     |    3. State-machine guard (only 'requested' → terminal state).
     |
     |  All ledger mutations, attendance record status changes,
     |  and risk-flag evaluation are delegated to ExcuseService.
     |──────────────────────────────────────────────────────────*/

    /**
     * Review (approve or reject) an excuse request.
     *
     * PATCH /api/v1/excuse-requests/{excuse}
     *
     * @param  ReviewExcuseRequest  $request  Validated & authorized payload.
     * @param  ExcuseRequest        $excuse   Route-model-bound instance.
     * @return JsonResponse
     */
    public function review(ReviewExcuseRequest $request, ExcuseRequest $excuse): JsonResponse
    {
        $validated = $request->validated();

        // Delegate to the service layer. ExcuseService::reviewExcuse()
        // handles the full transactional workflow:
        //   1. Updating the excuse status, reviewer, and timestamp.
        //   2. If approved: converting the attendance record status
        //      from 'absent' to 'excused', adjusting the ledger
        //      balance (+20 net via convertToExcused()), and
        //      re-evaluating student risk flags.
        //   3. If rejected: simply marking the excuse as rejected
        //      with no ledger changes.
        $excuse = $this->excuseService->reviewExcuse(
            excuse:     $excuse,
            status:     $validated['status'],
            reviewedBy: $request->user(),
        );

        return $this->successResponse(
            $excuse->load(['student:id,name', 'session', 'reviewer:id,name']),
            "Excuse request {$validated['status']} successfully."
        );
    }

    /* ──────────────────────────────────────────────────────────
     |  DELETE /api/v1/excuse-requests/{excuse}
     |──────────────────────────────────────────────────────────
     |  Restricted to Branch Managers only (via ExcuseRequestPolicy@delete).
     |  Attachment cleanup is delegated to ExcuseService.
     |──────────────────────────────────────────────────────────*/

    /**
     * Delete an excuse request.
     *
     * DELETE /api/v1/excuse-requests/{excuse}
     *
     * @param  ExcuseRequest  $excuse  Route-model-bound instance.
     * @return JsonResponse
     */
    public function destroy(ExcuseRequest $excuse): JsonResponse
    {
        $this->authorize('delete', $excuse);

        // Delegate cleanup (attachment file deletion) to the service.
        $this->excuseService->deleteExcuse($excuse);

        return $this->successResponse(null, 'Excuse request deleted successfully.');
    }
}
