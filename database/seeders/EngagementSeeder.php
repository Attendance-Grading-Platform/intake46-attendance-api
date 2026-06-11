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
                'start_date'      => Carbon::today()->subDays(rand(10, 30)),
                'end_date'        => Carbon::today()->addDays(rand(10, 60)),
                'scheduled_hours' => rand(2, 6),
            ]);

            // attach to 1-3 random Cohort records
            $attachedCohorts = $cohorts->random(min(rand(1, 3), $cohorts->count()));
            $engagement->cohorts()->attach($attachedCohorts->pluck('id')->toArray());

            // Create 3 sessions in the past for this engagement
            for ($sNum = 1; $sNum <= 3; $sNum++) {
                $sessionDate = Carbon::today()->subDays($sNum * 3);
                $session = \App\Models\EngagementSession::create([
                    'engagement_id' => $engagement->id,
                    'session_date'  => $sessionDate,
                    'delivered'     => true,
                ]);

                // Create attendance records for all students in the attached cohorts
                foreach ($attachedCohorts as $cohort) {
                    foreach ($cohort->students as $student) {
                        // 85% chance of being present, 15% absent
                        $isPresent = (rand(1, 100) <= 85);
                        \App\Models\AttendanceRecord::create([
                            'session_id' => $session->id,
                            'student_id' => $student->id,
                            'arrived_at' => $isPresent ? $sessionDate->copy()->setTime(9, rand(0, 20)) : null,
                            'left_at'    => $isPresent ? $sessionDate->copy()->setTime(13, 0) : null,
                        ]);
                    }
                }
            }
        }

        // Seed tags for some students
        $trackAdmin = User::where('role', 'track_admin')->first();
        if ($trackAdmin) {
            $students = User::where('role', 'student')->get();
            $tagsList = ['academic_concern', 'attendance_concern', 'top_performer', 'low_performance'];
            
            foreach ($students as $student) {
                // 30% chance a student gets 1 or 2 tags
                if (rand(1, 100) <= 30) {
                    $numTags = rand(1, 2);
                    $selectedTags = (array) array_rand(array_flip($tagsList), $numTags);
                    foreach ($selectedTags as $tagName) {
                        \App\Models\StudentTag::create([
                            'student_id' => $student->id,
                            'created_by' => $trackAdmin->id,
                            'tag'        => $tagName,
                            'note'       => 'Automatically seeded tag for testing.',
                        ]);
                    }
                }
            }
        }
    }
}
