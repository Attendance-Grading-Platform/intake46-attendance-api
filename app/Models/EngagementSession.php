<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An EngagementSession is a single scheduled date within an Engagement.
 * The `delivered` flag drives billable-hour calculation (BIL-1).
 *
 * Table name is 'engagement_sessions' to avoid collision with Laravel's
 * built-in 'sessions' table.
 */
class EngagementSession extends Model
{
    use HasFactory;

    protected $table = 'engagement_sessions';

    protected $fillable = [
        'engagement_id',
        'session_date',
        'start_time',
        'end_time',
        'delivered',
    ];

    protected $casts = [
        'session_date' => 'date',
        'start_time'   => 'string',
        'end_time'     => 'string',
        'delivered'    => 'boolean',
    ];

/* ──────────────────────────────────────────────
    |  Relationships
    |──────────────────────────────────────────────*/

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }
}
