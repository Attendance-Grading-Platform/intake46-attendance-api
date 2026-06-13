<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\User;
use App\Models\CourseComponent;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        // Seed 20 random grades
        Grade::factory()->count(20)->create();

        // Ensure the primary demo student has grades for all course components
        $student = User::where('email', 'ahmed.ali.46@student.iti.edu.eg')->first();
        if ($student) {
            $components = CourseComponent::all();
            foreach ($components as $component) {
                // Check if grade already exists for this student and component
                $exists = Grade::where('student_id', $student->id)
                    ->where('course_component_id', $component->id)
                    ->exists();

                if (!$exists) {
                    $score = rand(75, 98);
                    Grade::create([
                        'student_id'          => $student->id,
                        'course_component_id' => $component->id,
                        'raw_score'           => $score,
                        'raw_max'             => 100,
                        'weight'              => $component->weight,
                        'normalized_score'    => $score,
                    ]);
                }
            }
        }
    }
}
