<?php

namespace Database\Seeders;

use App\Models\Cohort;
use App\Models\LabGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class LabGroupSeeder extends Seeder
{
    public function run(): void
    {
        $cohorts = Cohort::with('students')->get();
        $instructors = User::where('role', 'instructor')->get();

        foreach ($cohorts as $cohort) {
            $cohortStudents = $cohort->students;

            if ($cohortStudents->isEmpty()) {
                continue;
            }

            for ($i = 1; $i <= 3; $i++) {
                $labGroup = LabGroup::create([
                    'cohort_id' => $cohort->id,
                    'name'      => 'Lab Group ' . $i,
                ]);

                $labGroup->instructors()->attach(
                    $instructors->random(min(rand(1, 2), $instructors->count()))->pluck('id')->toArray()
                );

                $groupStudents = $cohortStudents->random(
                    min(rand(10, 15), $cohortStudents->count())
                );
                $labGroup->students()->attach($groupStudents->pluck('id')->toArray());
            }
        }
    }
}
