<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

class EngagementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instructor_id' => User::factory(),
            'type' => 'instructor',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'hours_per_session' => 3,
            'scheduled_hours' => 18,
            'days_of_week' => ['Monday', 'Wednesday'],
        ];
    }
}
