<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExcuseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ReviewExcuseRequest — validates the payload when a Track Admin
 * (or Branch Manager) approves or rejects an excuse request.
 *
 * Authorization is handled via ExcuseRequestPolicy@update which
 * ensures only track_admin and branch_manager roles can review,
 * and that a track_admin can only review excuses from students
 * within their administered cohorts.
 *
 * @see EXC-3: Track Admin approves/rejects excuse
 * @see ExcuseRequestPolicy::update()
 */
class ReviewExcuseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Delegates to ExcuseRequestPolicy@update for contextual RBAC.
     * The route-model-bound ExcuseRequest is resolved from the {excuse}
     * route parameter.
     */
    public function authorize(): bool
    {
        $excuse = $this->route('excuse');

        return $this->user()->can('update', $excuse);
    }

    /**
     * Validation rules for the review action.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * status — The review decision.
             * Must be one of 'approved' or 'rejected'.
             * The 'requested' state is the initial default and cannot
             * be manually set during a review.
             */
            'status' => [
                'required',
                'string',
                Rule::in(['approved', 'rejected']),
            ],
        ];
    }

    /**
     * Additional validation after the base rules pass.
     *
     * Prevents reviewing an excuse that has already been processed
     * (i.e., is no longer in 'requested' status). This enforces the
     * state machine: requested → approved | rejected (terminal states).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var ExcuseRequest|null $excuse */
            $excuse = $this->route('excuse');

            if ($excuse && ! $excuse->isPending()) {
                $validator->errors()->add(
                    'status',
                    "This excuse request has already been {$excuse->status}. It cannot be reviewed again."
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
            'status' => 'review decision',
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
            'status.required' => 'A review decision (approved or rejected) is required.',
            'status.in'       => 'The review decision must be either "approved" or "rejected".',
        ];
    }
}
