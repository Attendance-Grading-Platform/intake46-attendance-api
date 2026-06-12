<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cohort extends Model
{
    use HasFactory;

    protected $fillable = [
        'track_id',
        'name',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at'   => 'date',
    ];

    // Scope: active cohorts only
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForTrack(Builder $query, int $trackId): Builder
    {
        return $query->where('track_id', $trackId);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * Track Admins assigned to this cohort (LC-2).
     * Pivot: cohort_track_admins (cohort_id, user_id)
     */
    public function trackAdmins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cohort_track_admins');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * All teaching engagements scheduled for this cohort (ENG-3).
     * Pivot: engagement_cohorts
     */
    public function engagements(): BelongsToMany
    {
        return $this->belongsToMany(Engagement::class, 'engagement_cohorts');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cohort_students')
                    ->withPivot('enrolled_at');
    }

    public function labGroups(): HasMany
    {
        return $this->hasMany(LabGroup::class);
    }
}
