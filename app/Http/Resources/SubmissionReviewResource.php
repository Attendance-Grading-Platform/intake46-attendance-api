<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'submission_url'      => $this->submission_url,
            'file_path'           => $this->file_path,
            'status'              => $this->status,
            'penalty_days'        => $this->penalty_days,
            'submitted_at'        => $this->submitted_at?->toIso8601String(),
            'student' => [
                'id'    => $this->student?->id,
                'name'  => $this->student?->name,
                'email' => $this->student?->email,
            ],
            'course_component' => [
                'id'   => $this->courseComponent?->id,
                'type' => $this->courseComponent?->type,
            ],
            'course' => [
                'id'   => $this->course?->id,
                'name' => $this->course?->name,
            ],
            'grade' => $this->whenLoaded('grade', function () {
                return [
                    'raw_score' => $this->grade->raw_score,
                    'raw_max'   => $this->grade->raw_max,
                ];
            }),
        ];
    }
}
