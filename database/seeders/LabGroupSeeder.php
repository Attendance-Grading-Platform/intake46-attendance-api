<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cohort;
use App\Models\LabGroup;
use App\Models\User;

class LabGroupSeeder extends Seeder
{
    public function run(): void
    {
        $cohorts = Cohort::all();
        $instructors = User::where('role', 'instructor')->get();
        $allStudents = User::where('role', 'student')->get();

        foreach ($cohorts as $cohort) {
            // First ensure students are enrolled in the cohort
            $cohortStudents = $allStudents->random(min(20, $allStudents->count()));
            
            // Attach students to cohort if not already attached
            $cohort->students()->syncWithoutDetaching(
                $cohortStudents->mapWithKeys(function ($student) {
                    return [$student->id => ['enrolled_at' => now()]];
                })->toArray()
            );
            
            // Create LabGroups for this cohort
            for ($i = 1; $i <= 3; $i++) {
                $labGroup = LabGroup::create([
                    'cohort_id' => $cohort->id,
                    'name' => 'Lab Group ' . $i,
                ]);

                // Attach 1-2 instructors
                $labGroup->instructors()->attach(
                    $instructors->random(min(rand(1, 2), $instructors->count()))->pluck('id')->toArray()
                );

                // Attach 10-15 students from THIS cohort
                $groupStudents = $cohortStudents->random(min(rand(10, 15), $cohortStudents->count()));
                $labGroup->students()->attach($groupStudents->pluck('id')->toArray());
            }
        }
    }
}
