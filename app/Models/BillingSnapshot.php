<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'cohort_id',
        'period',
        'compensation_type',
        'delivered_hours',
        'fixed_salary_component',
        'hourly_component',
        'total_amount'
    ];

    protected $casts = [
        'delivered_hours' => 'decimal:2',
        'fixed_salary_component' => 'decimal:2',
        'hourly_component' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(User::class, 'person_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }
}
