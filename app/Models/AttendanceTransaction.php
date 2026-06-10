<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttendanceTransaction — Records every point change in a student's ledger.
 * This provides a full audit trail and allows recalculating the balance
 * to prevent data drift (SC-3).
 */
class AttendanceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_ledger_id',
        'type',           // 'unexcused', 'excused', 'adjustment'
        'points',         // Negative for deductions, positive for refunds
        'description',    // Descriptive note
        'session_id',     // Linked session
        'excuse_id',      // Linked excuse request (if any)
    ];

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(AttendanceLedger::class, 'attendance_ledger_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EngagementSession::class, 'session_id');
    }

    public function excuse(): BelongsTo
    {
        return $this->belongsTo(ExcuseRequest::class, 'excuse_id');
    }
}
