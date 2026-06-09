<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Submission extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function courseComponent(): BelongsTo
    {
        return $this->belongsTo(CourseComponent::class);
    }

    /**
     * Links the submission to its corresponding grade via shared keys.
     */
    public function grade(): HasOne
    {
        return $this->hasOne(Grade::class, 'student_id', 'student_id')
                    ->where('course_component_id', $this->course_component_id);
    }

    /**
     * Virtual attribute so the frontend doesn't break when looking for a 'status'.
     */
    public function getStatusAttribute(): string
    {
        return $this->grade ? 'graded' : 'pending';
    }

    public function course()
    {
        return $this->hasOneThrough(
            Course::class,
            CourseComponent::class,
            'id',
            'id',
            'course_component_id',
            'course_id'
        );
    }
}
