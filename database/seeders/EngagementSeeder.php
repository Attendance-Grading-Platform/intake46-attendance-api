<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Engagement;
use App\Models\Cohort;
use App\Models\User;
use Carbon\Carbon;

class EngagementSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = User::where('role', 'instructor')->get();
        $cohorts = Cohort::all();

        if ($instructors->isEmpty() || $cohorts->isEmpty()) {
            return;
        }

        for ($i = 0; $i < 15; $i++) {
            $engagement = Engagement::create([
                'instructor_id'   => $instructors->random()->id,
                'type'            => ['lecture', 'lab', 'business_session'][array_rand(['lecture', 'lab', 'business_session'])],
                'start_date'      => Carbon::today()->subDays(rand(0, 30)),
                'end_date'        => Carbon::today()->addDays(rand(10, 60)),
                'scheduled_hours' => rand(2, 6),
            ]);

            // attach to 1-3 random Cohort records
            $engagement->cohorts()->attach(
                $cohorts->random(min(rand(1, 3), $cohorts->count()))->pluck('id')->toArray()
            );
        }
    }
}
