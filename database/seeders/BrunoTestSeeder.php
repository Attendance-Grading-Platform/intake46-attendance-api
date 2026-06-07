<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BrunoTestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Branch Manager
        User::updateOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Branch Manager',
                'password' => Hash::make('password'),
                'role' => 'branch_manager',
                'expiry_date' => now()->addYear(),
                'is_active' => true,
            ]
        );

        // 2. Create Track Admin
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Track Admin',
                'password' => Hash::make('password'),
                'role' => 'track_admin',
                'expiry_date' => now()->addYear(),
                'is_active' => true,
            ]
        );

        // 3. Create Branch and Track
        $branch = Branch::updateOrCreate(['name' => 'Cairo HQ']);
        Track::updateOrCreate(
            ['name' => 'Backend Performance'],
            ['branch_id' => $branch->id]
        );

        // 4. Create Test Students
        for ($i = 1; $i <= 5; $i++) {
            User::updateOrCreate(
                ['email' => "student{$i}@example.com"],
                [
                    'name' => "Student {$i}",
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'expiry_date' => now()->addYear(),
                    'is_active' => true,
                ]
            );
        }
    }
}
