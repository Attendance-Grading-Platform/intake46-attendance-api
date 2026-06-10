<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records a student's scan-in (arrived_at) and scan-out (left_at)
 * for a single session.
 *
 * @see ATT-1, ATT-2, ATT-3
 */
class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'student_id',
        'track_id',
        'status',
        'arrived_at',
        'left_at',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
        'left_at'    => 'datetime',
    ];

    /* ──────────────────────────────────────────────
     |  Relationships
     |──────────────────────────────────────────────*/

    public function session(): BelongsTo
    {
        return $this->belongsTo(EngagementSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /* ──────────────────────────────────────────────
     |  Helpers
     |──────────────────────────────────────────────*/

    /**
     * Did the student actually attend (scanned in)?
     */
    public function wasPresent(): bool
    {
        return $this->arrived_at !== null;
    }
}
