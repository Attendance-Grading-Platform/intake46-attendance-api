<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * An Engagement is a teaching booking with a type, a date range,
 * scheduled session hours, and an assigned instructor.
 *
 * @see ENG-3, ENG-4, ENG-5
 */
class Engagement extends Model
{
    use HasFactory;

    protected $appends = ['cohort'];

    protected $fillable = [
        'instructor_id',
        'type',
        'start_date',
        'end_date',
        'hours_per_session',
        'scheduled_hours',
        'days_of_week',
        'daily_start_time',
        'daily_end_time',
    ];

    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'hours_per_session' => 'decimal:2',
        'scheduled_hours'   => 'integer',
        'days_of_week'      => 'array',
        'daily_start_time'  => 'string',
        'daily_end_time'    => 'string',
    ];

/* ──────────────────────────────────────────────
    |  Scopes
    |──────────────────────────────────────────────*/

    /**
     * Engagements whose active window includes the current date.
     * Used for ENG-5 / ANN-2 time-based authorization.
     */
    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today);
    }

    /**
     * Filter engagements by type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

/* ──────────────────────────────────────────────
    |  Relationships
    |──────────────────────────────────────────────*/

    /**
     * The cohorts attending this engagement (ENG-3 / ERD Compliance).
     * Pivot: engagement_cohorts
     */
    public function cohorts(): BelongsToMany
    {
        return $this->belongsToMany(Cohort::class, 'engagement_cohorts');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EngagementSession::class);
    }

    /**
     * Shortcut: all attendance records across every session of this engagement.
     */
    public function attendanceRecords(): HasManyThrough
    {
        return $this->hasManyThrough(AttendanceRecord::class, EngagementSession::class);
    }

/* ──────────────────────────────────────────────
    |  Helpers
    |──────────────────────────────────────────────*/

    /**
     * Total delivered hours for billing (BIL-1).
     * Sum of scheduled_hours for every session flagged as delivered.
     */
    public function deliveredHours(): int
    {
        return (int) ($this->sessions()
                        ->where('delivered', true)
                        ->count() * $this->hours_per_session);
    }

    public function getCohortAttribute(): ?Cohort
    {
        return $this->cohorts->first();

    }
}
