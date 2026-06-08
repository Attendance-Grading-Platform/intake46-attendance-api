<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'score' => $this->score,
            'final_score' => $this->final_score,
            'override_score' => $this->override_score,
            'is_overridden' => (bool)$this->is_overridden,
            'late_penalty' => $this->late_penalty,
            'course' => [
                'id' => $this->course->id,
                'name' => $this->course->name,
                'code' => $this->course->code,
            ],
            'lab_submission' => $this->whenLoaded('labSubmission', function () {
                return [
                    'id' => $this->labSubmission->id,
                    'submitted_at' => $this->labSubmission->created_at?->toIso8601String(),
                    'status' => $this->labSubmission->status,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
