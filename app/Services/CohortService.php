<?php

namespace App\Services;

use App\Models\Cohort;
use Illuminate\Validation\ValidationException;

class CohortService
{
    public function create(array $data): Cohort
    {
        $alreadyActive = Cohort::active()
                               ->forTrack($data['track_id'])
                               ->exists();

        if ($alreadyActive) {
            throw ValidationException::withMessages([
                'track_id' => 'This track already has an active cohort. Close it before creating a new one.',
            ]);
        }

        return Cohort::create($data);
    }


    public function close(Cohort $cohort): Cohort
    {
        $cohort->update([
            'status'   => 'closed',
            'ended_at' => now()->toDateString(),
        ]);

        return $cohort->fresh();
    }
}