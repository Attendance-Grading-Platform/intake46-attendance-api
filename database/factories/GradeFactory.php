<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\User;
use App\Models\CourseComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'student_id'          => User::where('role', 'student')->inRandomOrder()->first()->id,
            'course_component_id' => CourseComponent::inRandomOrder()->first()->id,
            'raw_score'           => $this->faker->randomFloat(2, 0, 100),
            'raw_max'             => 100,
            'weight'              => 50,
            'normalized_score'    => $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
