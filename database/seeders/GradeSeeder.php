<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\User;
use App\Models\CourseComponent;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    /**
     * Hardcoded grade entries: [student_email, raw_score, normalized_score].
     * One entry per student–component pair. Components are resolved by ID order.
     */
    private static function gradeData(): array
    {
        return [
            // Web-track students
            ['ahmed.ali.46@student.iti.edu.eg',      92, 92],
            ['alaa.ibrahim.46@student.iti.edu.eg',   85, 85],
            ['bassem.khaled.46@student.iti.edu.eg',  78, 78],
            ['dina.mostafa.46@student.iti.edu.eg',   90, 90],
            ['eslam.youssef.46@student.iti.edu.eg',  88, 88],
            ['fatma.ahmed.46@student.iti.edu.eg',    76, 76],
            ['galal.hassan.46@student.iti.edu.eg',   80, 80],
            ['heba.tarek.46@student.iti.edu.eg',     95, 95],
            ['islam.nabil.46@student.iti.edu.eg',    83, 83],
            ['jana.sameh.46@student.iti.edu.eg',     77, 77],
            ['karim.adel.46@student.iti.edu.eg',     91, 91],
            ['lara.gamal.46@student.iti.edu.eg',     84, 84],
            ['mahmoud.wael.46@student.iti.edu.eg',   70, 70],
            ['nada.hossam.46@student.iti.edu.eg',    89, 89],
            ['omar.sherif.46@student.iti.edu.eg',    93, 93],
            ['passant.emad.46@student.iti.edu.eg',   75, 75],
            ['ramy.ayman.46@student.iti.edu.eg',     87, 87],
            ['salma.tamer.46@student.iti.edu.eg',    82, 82],
            ['tamer.hesham.46@student.iti.edu.eg',   79, 79],
            ['aya.osama.46@student.iti.edu.eg',      86, 86],
            // Mobile-track students
            ['adam.sherif.46@student.iti.edu.eg',    88, 88],
            ['bishoy.emad.46@student.iti.edu.eg',    74, 74],
            ['christine.hany.46@student.iti.edu.eg', 91, 91],
            ['david.ramzy.46@student.iti.edu.eg',    83, 83],
            ['engy.tarek.46@student.iti.edu.eg',     77, 77],
            ['fady.maged.46@student.iti.edu.eg',     90, 90],
            ['george.nabil.46@student.iti.edu.eg',   85, 85],
            ['hany.samir.46@student.iti.edu.eg',     72, 72],
            ['irene.amir.46@student.iti.edu.eg',     94, 94],
            ['joseph.makram.46@student.iti.edu.eg',  81, 81],
        ];
    }

    public function run(): void
    {
        $components = CourseComponent::orderBy('id')->get();

        if ($components->isEmpty()) {
            $this->command->warn('GradeSeeder: No CourseComponents found – skipping grade seed.');
            return;
        }

        // Use the first component as the default for the hardcoded list.
        // If there are multiple components, cycle through them.
        $componentCount = $components->count();

        foreach (self::gradeData() as $index => [$email, $rawScore, $normalizedScore]) {
            $student = User::where('email', $email)->first();

            if (! $student) {
                continue;
            }

            // Assign to a component cycling through all available ones
            $component = $components[$index % $componentCount];

            $exists = Grade::where('student_id', $student->id)
                ->where('course_component_id', $component->id)
                ->exists();

            if (! $exists) {
                Grade::create([
                    'student_id'          => $student->id,
                    'course_component_id' => $component->id,
                    'raw_score'           => $rawScore,
                    'raw_max'             => 100,
                    'weight'              => $component->weight,
                    'normalized_score'    => $normalizedScore,
                ]);
            }
        }

        // Ensure the primary demo student has grades for ALL course components
        $primary = User::where('email', 'ahmed.ali.46@student.iti.edu.eg')->first();
        if ($primary) {
            $scores = [92, 88, 95, 80, 85];  // one preset score per component
            foreach ($components as $i => $component) {
                $exists = Grade::where('student_id', $primary->id)
                    ->where('course_component_id', $component->id)
                    ->exists();

                if (! $exists) {
                    $score = $scores[$i % count($scores)];
                    Grade::create([
                        'student_id'          => $primary->id,
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
