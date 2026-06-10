<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Track extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = ['branch_id', 'name'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    public function activeCohort(): ?Cohort
    {
        return $this->cohorts()
                    ->where('status', 'active')
                    ->first();
    }
}