<?php

namespace Database\Factories;

use App\Models\Submission;
use App\Models\User;
use App\Models\CourseComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'student_id'          => User::where('role', 'student')->inRandomOrder()->first()->id,
            'course_component_id' => CourseComponent::inRandomOrder()->first()->id,
            'submission_url'      => $this->faker->url(),
            'file_path'           => 'submissions/' . $this->faker->uuid() . '.pdf',
            'submitted_at'        => now(),
            'penalty_days'        => 0,
        ];
    }
}
