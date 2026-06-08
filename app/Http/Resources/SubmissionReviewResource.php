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
            'id' => $this->id,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'status' => $this->status,
            'submitted_at' => $this->created_at?->toIso8601String(),
            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
            ],
            'course' => [
                'id' => $this->course?->id,
                'name' => $this->course?->name,
            ],
        ];
    }
}
