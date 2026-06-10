<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'expiry_date',
        'is_active',
        'compensation_type',
        'hourly_rate',
        'fixed_salary',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
            'hourly_rate' => 'decimal:2',
            'fixed_salary' => 'decimal:2',
        ];
    }

    /* ──────────────────────────────────────────────
     |  Engagement & Attendance Relationships
     |──────────────────────────────────────────────*/

    /**
     * Engagements where this user is the assigned instructor (ENG-4).
     */
    public function engagements(): HasMany
    {
        return $this->hasMany(Engagement::class, 'instructor_id');
    }

    /**
     * Attendance records for this user as a student.
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'student_id');
    }

    /**
     * The student's standalone attendance ledger (ATT-4, ATT-6).
     */
    public function attendanceLedger(): HasOne
    {
        return $this->hasOne(AttendanceLedger::class, 'student_id');
    }

    /* ──────────────────────────────────────────────
     |  Cohort & Lab Group Pivot Relationships
     |──────────────────────────────────────────────*/

    /**
     * Cohorts this user administers as a Track Admin (LC-2).
     * Pivot: cohort_track_admins (cohort_id, user_id)
     */
    public function administeredCohorts(): BelongsToMany
    {
        return $this->belongsToMany(Cohort::class, 'cohort_track_admins');
    }

    /**
     * Cohorts this user is enrolled in as a Student.
     * Pivot: cohort_students (cohort_id, user_id, enrolled_at)
     */
    public function enrolledCohorts(): BelongsToMany
    {
        return $this->belongsToMany(Cohort::class, 'cohort_students')
            ->withPivot('enrolled_at');
    }

    /**
     * Lab groups this user instructs (ACC-3, GRD-4).
     * Pivot: lab_group_instructors (lab_group_id, user_id)
     */
    public function instructedLabGroups(): BelongsToMany
    {
        return $this->belongsToMany(LabGroup::class, 'lab_group_instructors');
    }

    /**
     * Lab groups this user is enrolled in as a Student.
     * Pivot: lab_group_students (lab_group_id, user_id)
     */
    public function enrolledLabGroups(): BelongsToMany
    {
        return $this->belongsToMany(LabGroup::class, 'lab_group_students');
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
