<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLedger;
use App\Models\AttendanceRecord;
use App\Models\ExcuseRequest;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExcuseRequestController extends Controller
{
    use ApiResponse;

    // list excuse requests based on who is asking
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExcuseRequest::class);

        $user = $request->user();

        $query = ExcuseRequest::with(['student:id,name,email', 'session', 'reviewer:id,name']);

        // student see only his own requests
        if ($user->role === 'student') {
            $query->where('student_id', $user->id);
        }

        if ($user->role === 'track_admin') {
            $cohortIds = $user->administeredCohorts()->pluck('cohorts.id');
            $query->whereHas('student.enrolledCohorts', function ($q) use ($cohortIds) {
                $q->whereIn('cohorts.id', $cohortIds);
            });
        }
        return $this->successResponse($query->latest()->get(), 'Excuse requests retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ExcuseRequest::class);

        $validated = $request->validate([
            'session_id' => 'required|exists:engagement_sessions,id',
            'reason'     => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:1024',
        ]);

        // check if student already submit excuse for this session
        $existing = ExcuseRequest::where('student_id', $request->user()->id)
            ->where('session_id', $validated['session_id'])
            ->first();

        if ($existing) {
            return $this->errorResponse('You already have excuse request for this session.', 422);
        }

        // save file if student upload attachment
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('excuse-attachments', 'local');
        }

        $excuse = ExcuseRequest::create([
            'student_id'      => $request->user()->id,
            'session_id'      => $validated['session_id'],
            'status'          => 'requested',
            'reason'          => $validated['reason'],
            'attachment_path' => $attachmentPath,
        ]);

        return $this->successResponse(
            $excuse->load(['student:id,name', 'session']),
            'Excuse request submitted successfully.',
            201
        );
    }

    public function show(ExcuseRequest $excuse): JsonResponse
    {
        $this->authorize('view', $excuse);

        return $this->successResponse(
            $excuse->load(['student:id,name,email', 'session', 'reviewer:id,name']),
            'Excuse request retrieved successfully.'
        );
    }

    // admin approve or reject the excuse
    public function review(Request $request, ExcuseRequest $excuse): JsonResponse
    {
        $this->authorize('update', $excuse);

        if (!$excuse->isPending()) {
            return $this->errorResponse('This excuse already reviewed, status is: ' . $excuse->status, 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        // CRIT-E1: Before crediting the ledger, confirm the student was actually
        // marked absent for this session. convertToExcused() reverses a -25 deduction;
        // calling it without a confirmed absence corrupts the ledger balance.
        if ($validated['status'] === 'approved') {
            // absence = arrived_at IS NULL (the table has no status column)
            $absenceRecord = AttendanceRecord::where('session_id', $excuse->session_id)
                ->where('student_id', $excuse->student_id)
                ->whereNull('arrived_at')
                ->first();

            if (!$absenceRecord) {
                return $this->errorResponse(
                    'Cannot approve: No absence record found for this session.',
                    422
                );
            }
        }

        DB::transaction(function () use ($excuse, $validated, $request) {
            $ledger = AttendanceLedger::where('student_id', $excuse->student_id)->first();

            if ($validated['status'] === 'approved') {
                if ($ledger) {
                    // SC-3: Replaces manual balance increment with transaction-based refund
                    $ledger->deductExcused($excuse->session_id, $excuse->id);
                } else {
                    $ledger = AttendanceLedger::create([
                        'student_id' => $excuse->student_id,
                        'balance'    => AttendanceLedger::INITIAL_BALANCE,
                    ]);
                    $ledger->deductExcused($excuse->session_id, $excuse->id);
                }
            } elseif ($validated['status'] === 'rejected') {
                // If it was previously approved (edge case or future feature) or if we just want to ensure it's unexcused
                if ($ledger) {
                    $ledger->revertToUnexcused($excuse->session_id);
                }
            }

            $excuse->update([
                'status'      => $validated['status'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return $this->successResponse(
            $excuse->fresh()->load(['student:id,name', 'session', 'reviewer:id,name']),
            'Excuse request ' . $validated['status'] . ' successfully.'
        );
    }

    // delete excuse only branch manager can do this
    public function destroy(ExcuseRequest $excuse): JsonResponse
    {
        $this->authorize('delete', $excuse);

        // delete attachment file from storage if exist
        if ($excuse->attachment_path) {
            Storage::disk('local')->delete($excuse->attachment_path);
        }

        $excuse->delete();

        return $this->successResponse(null, 'Excuse request deleted successfully.');
    }
}
