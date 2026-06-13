<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Seeder;

class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'Smart Village']);

        $webAdmin = User::where('email', 'karim.ashraf@iti.edu.eg')->first();
        $mobileAdmin = User::where('email', 'nour.samir@iti.edu.eg')->first();

        $webTrack = Track::firstOrCreate(
            ['name' => 'Full Stack Web Development', 'branch_id' => $branch->id],
        );

        $mobileTrack = Track::firstOrCreate(
            ['name' => 'Mobile Application Development', 'branch_id' => $branch->id],
        );

        $webCohort = Cohort::firstOrCreate(
            ['name' => 'Intake 46 - Web Development'],
            [
                'track_id'   => $webTrack->id,
                'status'     => 'active',
                'started_at' => now()->subDays(45),
                'ended_at'   => now()->addMonths(4),
            ],
        );

        $mobileCohort = Cohort::firstOrCreate(
            ['name' => 'Intake 46 - Mobile Development'],
            [
                'track_id'   => $mobileTrack->id,
                'status'     => 'active',
                'started_at' => now()->subDays(45),
                'ended_at'   => now()->addMonths(4),
            ],
        );

        if ($webAdmin) {
            $webCohort->trackAdmins()->syncWithoutDetaching([$webAdmin->id]);
        }

        if ($mobileAdmin) {
            $mobileCohort->trackAdmins()->syncWithoutDetaching([$mobileAdmin->id]);
        }

        $this->enrollStudents($webCohort, UserSeeder::webStudentEmails());
        $this->enrollStudents($mobileCohort, UserSeeder::mobileStudentEmails());

        $this->seedCourses($webCohort, [
            'Laravel Advanced',
            'Vue.js & TypeScript',
            'Database Design & PostgreSQL',
        ]);

        $this->seedCourses($mobileCohort, [
            'Flutter Development',
            'React Native',
            'Mobile UI/UX Design',
        ]);
    }

    private function enrollStudents(Cohort $cohort, array $emails): void
    {
        $students = User::whereIn('email', $emails)->get();

        $cohort->students()->syncWithoutDetaching(
            $students->mapWithKeys(fn (User $student) => [
                $student->id => ['enrolled_at' => now()],
            ])->all(),
        );
    }

    private function seedCourses(Cohort $cohort, array $courseNames): void
    {
        foreach ($courseNames as $courseName) {
            $course = Course::firstOrCreate([
                'name'      => $courseName,
                'cohort_id' => $cohort->id,
            ]);

            if ($course->components()->exists()) {
                continue;
            }

            CourseComponent::create([
                'course_id' => $course->id,
                'type'      => 'lab_deliverable',
                'weight'    => 50,
                'due_date'  => now()->addDays(10),
            ]);

            CourseComponent::create([
                'course_id' => $course->id,
                'type'      => 'final_exam',
                'weight'    => 50,
                'due_date'  => now()->addDays(20),
            ]);
        }
    }
}
