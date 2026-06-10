<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\Track;
use App\Models\Cohort;
use App\Models\Course;

class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        // create branch and track
        $branch = Branch::firstOrCreate(['name' => 'Smart Village']);
        
        $track = Track::firstOrCreate([
            'name' => 'Full Stack Web Development',
            'branch_id' => $branch->id
        ]);

        // cohort must be active now
        $cohort = Cohort::create([
            'name' => 'Intake 46 - PHP Laravel',
            'track_id' => $track->id,
            'started_at' => now()->subDays(15),
            'ended_at' => now()->addMonths(3),
        ]);

        $courses = ['Laravel Advanced', 'React & Tailwind', 'Linux Administration'];

        foreach ($courses as $courseName) {
            $course = Course::create([
                'name' => $courseName,
                'cohort_id' => $cohort->id,
            ]);

            \App\Models\CourseComponent::create([
                'course_id' => $course->id,
                'type' => 'lab_deliverable',
                'weight' => 50,
                'due_date' => now()->addDays(10),
            ]);

            \App\Models\CourseComponent::create([
                'course_id' => $course->id,
                'type' => 'final_exam',
                'weight' => 50,
                'due_date' => now()->addDays(20),
            ]);
        }
    }
}