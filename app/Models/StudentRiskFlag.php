<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRiskFlag extends Model
{
    protected $fillable = [
        'student_id',
        'cohort_id',
        'at_risk',
        'reasons',
        'flagged_at',
        'resolved_at'
    ];

    protected $casts = [
        'reasons' => 'array',
        'at_risk' => 'boolean',
        'flagged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }
}
