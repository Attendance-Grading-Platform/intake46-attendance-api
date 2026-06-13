<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BillingSnapshot;
use App\Models\User;
use App\Models\Cohort;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $webCohort = Cohort::whereHas('track', fn($q) =>
            $q->where('name', 'like', '%Web%'))->first();

        $instructors = [
            [
                'email'            => 'amira.khaled@iti.edu.eg',
                'delivered_hours'  => 72,
                'fixed_salary'     => 0,
                'hourly_component' => 72 * 400,
                'total_amount'     => 72 * 400,
            ],
            [
                'email'            => 'youssef.nabil@iti.edu.eg',
                'delivered_hours'  => 60,
                'fixed_salary'     => 0,
                'hourly_component' => 60 * 350,
                'total_amount'     => 60 * 350,
            ],
            [
                'email'            => 'sara.elsayed@iti.edu.eg',
                'delivered_hours'  => 48,
                'fixed_salary'     => 0,
                'hourly_component' => 48 * 400,
                'total_amount'     => 48 * 400,
            ],
            // Internal Track Admin who also taught
            [
                'email'            => 'karim.ashraf@iti.edu.eg',
                'delivered_hours'  => 30,
                'fixed_salary'     => 10000,
                'hourly_component' => 30 * 250,
                'total_amount'     => 10000 + (30 * 250),
            ],
        ];

        foreach ($instructors as $data) {
            $user = User::where('email', $data['email'])->first();
            if (!$user) continue;

            BillingSnapshot::create([
                'person_id'           => $user->id,
                'cohort_id'           => $webCohort->id,
                'period'              => '2026-Q1',
                'compensation_type'   => $user->compensation_type,
                'delivered_hours'     => $data['delivered_hours'],
                'fixed_salary_component' => $data['fixed_salary'],
                'hourly_component'    => $data['hourly_component'],
                'total_amount'        => $data['total_amount'],
            ]);
        }
    }
}