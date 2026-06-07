<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cohort extends Model
{
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
}