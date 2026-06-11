<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Track;
use App\Models\User;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CohortOverviewSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Track Admin exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@iti.test'],
            [
                'name' => 'Track Admin',
                'password' => Hash::make('password'),
                'role' => 'track_admin',
                'expiry_date' => now()->addYears(2),
                'is_active' => true,
            ]
        );

        // 2. Create Branch and Track
        $branch = Branch::firstOrCreate(['name' => 'Cairo HQ']);
        $track = Track::firstOrCreate(
            ['name' => 'PHP Laravel Development'],
            ['branch_id' => $branch->id]
        );

        // 3. Create Cohort
        $cohort = Cohort::firstOrCreate(
            ['name' => 'Intake 45 - Spring 2025'],
            [
                'track_id' => $track->id,
                'status' => 'active',
                'started_at' => now()->subMonths(2),
                'ended_at' => now()->addMonths(4),
            ]
        );

        // 4. Assign Admin to Cohort (Crucial for 403 fix)
        $cohort->trackAdmins()->syncWithoutDetaching([$admin->id]);

        // 5. Create Students and Enroll them
        for ($i = 1; $i <= 10; $i++) {
            $student = User::firstOrCreate(
                ['email' => "student{$i}@iti.test"],
                [
                    'name' => "Student {$i}",
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'expiry_date' => now()->addYear(),
                    'is_active' => true,
                ]
            );
            $cohort->students()->syncWithoutDetaching([$student->id => ['enrolled_at' => now()]]);
        }

        // 6. Create Courses with Components
        
        // Course 1: Fully Configured (Weights = 100)
        $course1 = Course::firstOrCreate(
            ['name' => 'Backend Fundamentals', 'cohort_id' => $cohort->id]
        );
        $course1->components()->delete();
        CourseComponent::create([
            'course_id' => $course1->id,
            'type' => 'lab_deliverable',
            'weight' => 60,
            'due_date' => now()->addWeeks(2),
        ]);
        CourseComponent::create([
            'course_id' => $course1->id,
            'type' => 'final_exam',
            'weight' => 40,
            'due_date' => now()->addMonths(1),
        ]);

        // Course 2: Warning State (Weights != 100)
        $course2 = Course::firstOrCreate(
            ['name' => 'Database Design', 'cohort_id' => $cohort->id]
        );
        $course2->components()->delete();
        CourseComponent::create([
            'course_id' => $course2->id,
            'type' => 'lab_deliverable',
            'weight' => 30,
            'due_date' => now()->addWeek(),
        ]);

        // Course 3: No Components
        Course::firstOrCreate(
            ['name' => 'Soft Skills', 'cohort_id' => $cohort->id]
        );
    }
}
