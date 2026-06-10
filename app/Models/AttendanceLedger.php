<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A standalone per-student point balance starting at 250.
 * Added directly to the Grand Total as-is (never folded into a course).
 *
 * @see ATT-4, ATT-5, ATT-6
 */
class AttendanceLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'cohort_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'integer',
    ];

    /* ──────────────────────────────────────────────
     |  Relationships
     |──────────────────────────────────────────────*/

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /* ──────────────────────────────────────────────
     |  Business Logic (ATT-5)
     |──────────────────────────────────────────────*/

    /**
     * Deduct points for an unexcused absence (-25).
     */
    public function deductUnexcused(): void
    {
        $this->decrement('balance', 25);
    }

    /**
     * Deduct points for an excused (approved) absence (-5).
     */
    public function deductExcused(): void
    {
        $this->decrement('balance', 5);
    }

    /**
     * Reverse an unexcused deduction and apply the excused one.
     * Called when a Track Admin approves a previously-rejected or pending excuse.
     */
    public function convertToExcused(): void
    {
        $this->increment('balance', 20); // -25 → -5 = +20 net
    }

    /**
     * ANL-1: Student is at risk when balance drops below 150.
     */
    public function isAtRisk(): bool
    {
        return $this->balance < 150;
    }
}
