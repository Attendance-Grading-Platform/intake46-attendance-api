<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A standalone per-student point balance starting at 250.
 * Added directly to the Grand Total as-is (never folded into a course).
 *
 * @see ATT-4, ATT-5, ATT-6, SC-3
 */
class AttendanceLedger extends Model
{
    use HasFactory;

    /** ATT-4: Every student's ledger starts at 250 points. */
    const INITIAL_BALANCE = 250;

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

    public function transactions(): HasMany
    {
        return $this->hasMany(AttendanceTransaction::class, 'attendance_ledger_id');
    }

    /* ──────────────────────────────────────────────
     |  Business Logic (SC-3)
     |──────────────────────────────────────────────*/

    /**
     * SC-3: Hybrid recalculation ensures the balance field never drifts.
     * Always sums up transactions and subtracts from INITIAL_BALANCE.
     */
    public function recalculateBalance(): void
    {
        // Points are stored as positive/negative in the transactions table
        // Total balance = 250 + sum(points)
        $totalChanges = $this->transactions()->sum('points') ?? 0;
        
        $newBalance = self::INITIAL_BALANCE + (int) $totalChanges;
        
        // Ledger can never go below 0 (spec)
        $this->update(['balance' => max(0, $newBalance)]);
    }

    /**
     * Deduct points for an unexcused absence (-25).
     */
    public function deductUnexcused(int $sessionId): void
    {
        $this->transactions()->updateOrCreate(
            ['session_id' => $sessionId],
            [
                'type'   => 'unexcused',
                'points' => -25,
                'description' => 'Unexcused absence'
            ]
        );
        $this->recalculateBalance();
    }

    /**
     * Apply an excused (approved) absence (-5).
     * Usually called via ExcuseRequestController.
     */
    public function deductExcused(int $sessionId, int $excuseId): void
    {
        $this->transactions()->updateOrCreate(
            ['session_id' => $sessionId],
            [
                'type'      => 'excused',
                'points'    => -5,
                'excuse_id' => $excuseId,
                'description' => 'Excused absence approved'
            ]
        );
        $this->recalculateBalance();
    }

    /**
     * Reverse an approved excuse (back to unexcused).
     */
    public function revertToUnexcused(int $sessionId): void
    {
        $this->transactions()->where('session_id', $sessionId)->update([
            'type'   => 'unexcused',
            'points' => -25,
            'description' => 'Excuse rejected, reverted to unexcused absence'
        ]);
        $this->recalculateBalance();
    }

    /**
     * ANL-1: Student is at risk when balance drops below 150.
     */
    public function isAtRisk(): bool
    {
        return $this->balance < 150;
    }
}
