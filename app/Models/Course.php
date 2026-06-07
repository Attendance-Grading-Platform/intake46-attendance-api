<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'cohort_id',
        'name',
    ];

    protected $appends = ['is_ready'];

    /**
     * Check if the course is ready for grading (weights sum to exactly 100%).
     */
    public function getIsReadyAttribute(): bool
    {
        return round((float)$this->components()->sum('weight'), 2) === 100.0;
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(CourseComponent::class);
    }
}
