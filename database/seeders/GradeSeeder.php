<?php

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        // Seed 20 random grades
        Grade::factory()->count(20)->create();
    }
}
