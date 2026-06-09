<?php

namespace Database\Seeders;

use App\Models\Submission;
use Illuminate\Database\Seeder;

class SubmissionSeeder extends Seeder
{
    public function run(): void
    {
        // Seed 20 random submissions
        Submission::factory()->count(20)->create();
    }
}
