<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    protected $fillable = [
        'user_id',
        'course_component_id',
        'raw_score',
        'raw_max',
        'weight',
        'normalized_score'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(CourseComponent::class, 'course_component_id');
    }
}
