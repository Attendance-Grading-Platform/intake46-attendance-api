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
            'id'               => $this->id,
            'student_id'       => $this->student_id,
            'raw_score'        => $this->raw_score,
            'raw_max'          => $this->raw_max,
            'normalized_score' => $this->normalized_score,
            'final_score'      => $this->final_score ?? $this->raw_score,
            'is_overridden'    => $this->overridden_by !== null,
            'original_value'   => $this->when($this->overridden_by !== null, $this->original_value),
            'override_note'    => $this->when($this->overridden_by !== null, $this->override_note),
            'course_component' => [
                'id'     => $this->courseComponent?->id,
                'type'   => $this->courseComponent?->type,
                'weight' => $this->courseComponent?->weight,
            ],
            'course' => [
                'id'   => $this->courseComponent?->course?->id,
                'name' => $this->courseComponent?->course?->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
