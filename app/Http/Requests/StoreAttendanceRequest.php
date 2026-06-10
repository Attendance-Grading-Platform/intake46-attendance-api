<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreAttendanceRequest — validates the QR scan payload submitted by
 * staff members (track admins, instructors, branch managers) via the
 * authenticated V1 API.
 *
 * Authorization is handled via the AttendanceRecordPolicy@create gate,
 * which denies students from manually creating attendance records through
 * the CRUD API.  Students scan via the public IoT /scan endpoint instead.
 *
 * @see ATT-1: Scan-in / scan-out flow
 * @see AttendanceRecordPolicy::create()
 */
class StoreAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Delegates to AttendanceRecordPolicy@create which allows only
     * branch_manager, track_admin, and instructor roles.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', AttendanceRecord::class);
    }

    /**
     * Validation rules for the QR scan payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * session_id — The engagement session being attended.
             * Must reference a valid row in the engagement_sessions table.
             */
            'session_id' => [
                'required',
                'integer',
                'exists:engagement_sessions,id',
            ],

            /*
             * student_id — The student whose attendance is being recorded.
             * Must reference a valid user with the 'student' role.
             */
            'student_id' => [
                'required',
                'integer',
                'exists:users,id',
                // Ensure the referenced user is actually a student
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = \App\Models\User::find($value);
                    if (! $user || $user->role !== 'student') {
                        $fail('The selected user must have the student role.');
                    }
                },
            ],

            /*
             * track_id — The track context for this attendance record.
             * Matches the ERD column added by the team on the
             * attendance_records table.
             */
            'track_id' => [
                'required',
                'integer',
                'exists:tracks,id',
            ],
        ];
    }

    /**
     * Additional validation after the base rules pass.
     *
     * Enforces two business invariants:
     * 1. A student cannot already have an attendance record for this session.
     * 2. If the authenticated user IS a student (edge-case), they can only
     *    scan for themselves — not for another student.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // ── Guard: prevent duplicate attendance records ──────────
            if ($this->filled(['session_id', 'student_id'])) {
                $exists = AttendanceRecord::where('session_id', $this->input('session_id'))
                    ->where('student_id', $this->input('student_id'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'student_id',
                        'An attendance record already exists for this student in the given session.'
                    );
                }
            }

            // ── Guard: self-scan integrity ──────────────────────────
            // If the authenticated user happens to be a student (shouldn't
            // pass authorize(), but defense-in-depth), they may only scan
            // for themselves.
            $authed = $this->user();
            if (
                $authed
                && $authed->role === 'student'
                && (int) $this->input('student_id') !== $authed->id
            ) {
                $validator->errors()->add(
                    'student_id',
                    'Students can only record attendance for themselves.'
                );
            }
        });
    }

    /**
     * Human-friendly attribute labels for error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'session_id' => 'session',
            'student_id' => 'student',
            'track_id'   => 'track',
        ];
    }

    /**
     * Custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_id.exists' => 'The selected session does not exist.',
            'student_id.exists' => 'The selected student does not exist.',
            'track_id.exists'   => 'The selected track does not exist.',
        ];
    }
}
