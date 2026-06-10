<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'raw_score'        => 'decimal:2',
        'raw_max'          => 'decimal:2',
        'normalized_score' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function courseComponent(): BelongsTo
    {
        return $this->belongsTo(CourseComponent::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function overriddenByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
