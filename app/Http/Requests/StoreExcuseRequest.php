<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExcuseRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreExcuseRequest — validates the payload when a student submits
 * an excuse request for a missed session.
 *
 * Authorization is handled via ExcuseRequestPolicy@create which
 * restricts this action to users with the 'student' role only.
 *
 * @see EXC-1: Student submits excuse
 * @see ExcuseRequestPolicy::create()
 */
class StoreExcuseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Delegates to ExcuseRequestPolicy@create — only students may submit.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ExcuseRequest::class);
    }

    /**
     * Validation rules for the excuse submission payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * session_id — The engagement session the student missed.
             * Must reference a valid row in the engagement_sessions table.
             */
            'session_id' => [
                'required',
                'integer',
                'exists:engagement_sessions,id',
            ],

            /*
             * reason — Free-text justification from the student.
             * Required and capped at 2000 characters to prevent abuse.
             */
            'reason' => [
                'required',
                'string',
                'max:2000',
            ],

            /*
             * attachment — Optional supporting document (medical report, etc.).
             * Accepts PDF and common image formats, max 1 MB (1024 KB).
             */
            'attachment' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,gif,webp',
                'max:1024', // 1 MB in kilobytes
            ],
        ];
    }

    /**
     * Additional validation after the base rules pass.
     *
     * Prevents a student from submitting duplicate excuse requests
     * for the same session.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('session_id')) {
                $exists = ExcuseRequest::where('student_id', $this->user()->id)
                    ->where('session_id', $this->input('session_id'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'session_id',
                        'You have already submitted an excuse request for this session.'
                    );
                }

                // Check if the student was actually marked absent
                $isAbsent = \App\Models\AttendanceRecord::where('student_id', $this->user()->id)
                    ->where('session_id', $this->input('session_id'))
                    ->where('status', 'absent')
                    ->exists();

                if (! $isAbsent) {
                    $validator->errors()->add(
                        'session_id',
                        'You can only submit an excuse for a session you were marked absent in.'
                    );
                }
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
            'reason'     => 'excuse reason',
            'attachment' => 'attachment file',
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
            'reason.max'        => 'The excuse reason must not exceed 2000 characters.',
            'attachment.mimes'  => 'The attachment must be a PDF or image file (jpg, jpeg, png, gif, webp).',
            'attachment.max'    => 'The attachment must not be larger than 1 MB.',
        ];
    }
}
