<?php

namespace Database\Factories;

use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

class CohortFactory extends Factory
{
    public function definition(): array
    {
        return [
            'track_id' => Track::factory(),
            'name' => 'Cohort ' . $this->faker->unique()->numberBetween(1, 100),
            'status' => 'active',
            'started_at' => now(),
            'ended_at' => now()->addMonths(3),
        ];
    }
}
