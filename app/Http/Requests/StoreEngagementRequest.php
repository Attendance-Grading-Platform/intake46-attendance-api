<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreEngagementRequest — validates the payload for creating a new Engagement
 * and its auto-generated sessions.
 *
 * @see ENG-3: Engagement creation
 * @see ENG-4: Session auto-generation from days_of_week
 */
class StoreEngagementRequest extends FormRequest
{
    /**
     * Any authenticated user who passes the route middleware may attempt.
     * Fine-grained RBAC is handled in the controller via $this->authorize().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cohort_id' => [
                'required',
                'integer',
                'exists:cohorts,id',
            ],

            'instructor_id' => [
                'required',
                'integer',
                'exists:users,id',
                // Ensure the referenced user is actually an instructor
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = User::find($value);
                    if (! $user || $user->role !== 'instructor') {
                        $fail('The selected user must have the instructor role.');
                    }
                },
            ],

            'type' => [
                'required',
                Rule::in(['lecture', 'lab', 'business']),
            ],

            'start_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'scheduled_hours' => [
                'required',
                'integer',
                'min:1',
            ],

            /*
             * days_of_week — ISO day-of-week integers.
             *
             * Carbon uses ISO-8601 by default:
             *   1 = Monday, 2 = Tuesday, … , 7 = Sunday
             *
             * The session-generation algorithm in EngagementService
             * iterates through the date range and checks
             * Carbon::dayOfWeekIso against these values.
             */
            'days_of_week' => [
                'required',
                'array',
                'min:1',
            ],
            'days_of_week.*' => [
                'integer',
                'between:0,6',
            ],

            'daily_start_time' => [
                'nullable',
                'date_format:H:i:s',
            ],

            'daily_end_time' => [
                'nullable',
                'date_format:H:i:s',
                'after:daily_start_time',
            ],
        ];
    }

    /**
     * Human-friendly attribute labels for error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cohort_id'      => 'cohort',
            'instructor_id'  => 'instructor',
            'days_of_week'   => 'days of week',
            'days_of_week.*' => 'day of week',
            'start_date'     => 'start date',
            'end_date'       => 'end date',
            'scheduled_hours' => 'scheduled hours',
            'daily_start_time' => 'daily start time',
            'daily_end_time'   => 'daily end time',
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
            'type.in'                  => 'Engagement type must be one of: lecture, lab, or business.',
            'start_date.after_or_equal' => 'The start date must be today or a future date.',
            'end_date.after_or_equal'   => 'The end date must be equal to or after the start date.',
            'days_of_week.required'     => 'At least one day of the week must be selected for session scheduling.',
            'days_of_week.*.between'    => 'Each day must be an integer from 0 (Sunday) to 6 (Saturday).',
            'daily_start_time.date_format' => 'Daily start time must be in H:i:s format.',
            'daily_end_time.date_format'   => 'Daily end time must be in H:i:s format.',
            'daily_end_time.after'      => 'Daily end time must be after daily start time.',
        ];
    }
}
