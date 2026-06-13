<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExcuseRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ExcuseService — encapsulates all business logic for the excuse
 * request lifecycle.
 *
 * This service is injected into ExcuseRequestController and is the
 * single point of responsibility for:
 *   1. Storing uploaded attachments and creating ExcuseRequest records.
 *   2. Processing review decisions (approved / rejected).
 *   3. Adjusting the AttendanceLedger balance on approval.
 *   4. Updating the linked AttendanceRecord status (absent → excused).
 *   5. Re-evaluating StudentRiskFlags after ledger changes.
 *   6. Cleaning up attachment files on deletion.
 *
 * @see EXC-1, EXC-3, ATT-5
 */
class ExcuseService
{
    /**
     * Submit a new excuse request on behalf of a student.
     *
     * Handles file storage for the optional attachment and persists
     * the ExcuseRequest with an initial status of 'requested'.
     *
     * @param  User               $student     The authenticated student.
     * @param  int                $sessionId   The missed session ID.
     * @param  string             $reason      The student's justification.
     * @param  UploadedFile|null  $attachment  Optional supporting document.
     * @return ExcuseRequest      The newly created excuse request.
     */
    public function submitExcuse(User $student, int $sessionId, string $reason, ?UploadedFile $attachment = null): ExcuseRequest
    {
        // Store attachment if provided
        $attachmentPath = null;
        if ($attachment) {
            $attachmentPath = $attachment->store('excuse-attachments', 'local');

        }

        return ExcuseRequest::create([
            'student_id'      => $student->id,
            'session_id'      => $sessionId,
            'status'          => 'requested',
            'reason'          => $reason,
            'attachment_path' => $attachmentPath,
        ]);
    }

    /**
     * Review (approve or reject) an excuse request.
     *
     * Wraps the entire operation in a database transaction to ensure
     * atomicity between the excuse status update and any ledger mutations.
     *
     * @param  ExcuseRequest  $excuse      The excuse request being reviewed.
     * @param  string         $status      The review decision ('approved' or 'rejected').
     * @param  User           $reviewedBy  The admin performing the review.
     * @return ExcuseRequest  The updated excuse request.
     *
     * @todo Implement full review logic:
     *       - On approval: update the linked AttendanceRecord status from
     *         'absent' to 'excused', call ledger->convertToExcused(),
     *         and re-evaluate StudentRiskFlags.
     *       - On rejection: no ledger changes needed.
     */
    public function reviewExcuse(ExcuseRequest $excuse, string $status, User $reviewedBy): ExcuseRequest
    {
        return DB::transaction(function () use ($excuse, $status, $reviewedBy) {
            // Update the excuse request with the review decision
            $excuse->update([
                'status'      => $status,
                'reviewed_by' => $reviewedBy->id,
                'reviewed_at' => now(),
            ]);

            if ($status === 'approved') {
                $ledger = \App\Models\AttendanceLedger::where('student_id', $excuse->student_id)->first();
                if ($ledger) {
                    $ledger->deductExcused($excuse->session_id, $excuse->id);
                    
                    if ($ledger->fresh()->isAtRisk()) {
                        \App\Models\StudentRiskFlag::firstOrCreate([
                            'student_id' => $excuse->student_id,
                            'status' => 'active'
                        ], [
                            'flagged_at' => now(),
                            'reason' => 'Ledger balance below 150 points after excused absence.'
                        ]);
                    }
                }

                \App\Models\AttendanceRecord::where('session_id', $excuse->session_id)
                    ->where('student_id', $excuse->student_id)
                    ->update(['status' => 'excused']);
            } elseif ($status === 'rejected') {
                $ledger = \App\Models\AttendanceLedger::where('student_id', $excuse->student_id)->first();
                if ($ledger) {
                    $ledger->revertToUnexcused($excuse->session_id);
                }

                \App\Models\AttendanceRecord::where('session_id', $excuse->session_id)
                    ->where('student_id', $excuse->student_id)
                    ->update(['status' => 'absent']);

            }

            return $excuse->fresh();
        });
    }

    /**
     * Delete an excuse request and clean up its attachment file.
     *
     * @param  ExcuseRequest  $excuse  The excuse request to delete.
     * @return void
     */
    public function deleteExcuse(ExcuseRequest $excuse): void
    {
        // Clean up the attachment file from storage if it exists
        if ($excuse->attachment_path) {
            Storage::disk('local')->delete($excuse->attachment_path);

        }

        $excuse->delete();
    }
}
