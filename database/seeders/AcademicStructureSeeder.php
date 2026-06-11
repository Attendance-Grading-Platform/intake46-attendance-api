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
        $cohort = Cohort::firstOrCreate([
            'name' => 'Intake 46 - PHP Laravel',
        ], [
            'track_id' => $track->id,
            'started_at' => now()->subDays(15),
            'ended_at' => now()->addMonths(3),
        ]);

        $admin = \App\Models\User::where('email', 'admin@iti.test')->first();
        if ($admin) {
            $cohort->trackAdmins()->syncWithoutDetaching([$admin->id]);
        }
        $testStudent = \App\Models\User::where('email', 'student@iti.test')->first();
        if ($testStudent) {
            $cohort->students()->syncWithoutDetaching([$testStudent->id => ['enrolled_at' => now()]]);
        }

        if ($admin) {
            // Seed Announcements for student dashboard
            \App\Models\Announcement::create([
                'cohort_id'    => $cohort->id,
                'author_id'    => $admin->id,
                'title'        => 'Welcome to Intake 46!',
                'body'         => 'We are excited to welcome you all to the Full Stack Web Development track. Please review the schedule and make sure your local dev environment is ready.',
                'published_at' => now()->subDays(5),
            ]);

            \App\Models\Announcement::create([
                'cohort_id'    => $cohort->id,
                'author_id'    => $admin->id,
                'title'        => 'Advanced Laravel Session Rescheduled',
                'body'         => 'The session on Advanced Laravel Design Patterns has been rescheduled to tomorrow at 9:00 AM. Attendance is mandatory.',
                'published_at' => now()->subDays(1),
            ]);
        }
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