<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LabGroup extends Model
{
    protected $fillable = ['cohort_id', 'name'];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lab_group_students')
                    ->withTimestamps();
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lab_group_instructors')
                    ->withTimestamps();
    }
}
