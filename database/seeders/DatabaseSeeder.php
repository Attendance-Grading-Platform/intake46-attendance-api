<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * ┌─────────────────────────────────────────────────────────────┐
     * │  TEAM LOGIN CREDENTIALS                                    │
     * │                                                            │
     * │  All static accounts use password: "password"              │
     * │                                                            │
     * │  manager@iti.test       → branch_manager                   │
     * │  admin@iti.test         → track_admin                      │
     * │  instructor@iti.test    → instructor (external)            │
     * │  instructor-int@iti.test→ instructor (internal)            │
     * │  student@iti.test       → student                          │
     * │  expired@iti.test       → student (expired account)        │
     * │  inactive@iti.test      → student (deactivated account)    │
     * └─────────────────────────────────────────────────────────────┘
     */
    public function run(): void
    {
        // ─────────────────────────────────────────────────────────
        // 1. Static Accounts — deterministic, for team dev/testing
        // ─────────────────────────────────────────────────────────

        User::factory()->branchManager()->create([
            'name' => 'Admin Manager',
            'email' => 'manager@iti.test',
        ]);

        User::factory()->trackAdmin()->create([
            'name' => 'Track Admin',
            'email' => 'admin@iti.test',
        ]);

        User::factory()->instructor('external')->create([
            'name' => 'External Instructor',
            'email' => 'instructor@iti.test',
        ]);

        User::factory()->instructor('internal')->create([
            'name' => 'Internal Instructor',
            'email' => 'instructor-int@iti.test',
        ]);

        User::factory()->student()->create([
            'name' => 'Test Student',
            'email' => 'student@iti.test',
        ]);

        // Edge-case accounts for middleware / filter testing
        User::factory()->student()->expired()->create([
            'name' => 'Expired Student',
            'email' => 'expired@iti.test',
        ]);

        User::factory()->student()->inactive()->create([
            'name' => 'Inactive Student',
            'email' => 'inactive@iti.test',
        ]);

        // ─────────────────────────────────────────────────────────
        // 2. Bulk Data — random, for pagination / dashboards / UI
        // ─────────────────────────────────────────────────────────

        // Branch Managers (small count — realistic)
        User::factory()->count(2)->branchManager()->create();

        // Track Admins
        User::factory()->count(5)->trackAdmin()->create();

        // Instructors — mix of internal and external
        User::factory()->count(5)->instructor('internal')->create();
        User::factory()->count(8)->instructor('external')->create();
        User::factory()->count(5)->instructor('random')->create();

        // Students — the bulk of users
        User::factory()->count(40)->student()->create();

        // Edge-case students for realistic dashboard stats
        User::factory()->count(5)->student()->expired()->create();
        User::factory()->count(3)->student()->inactive()->create();
        User::factory()->count(3)->student()->unverified()->create();

        // Expired instructor for billing edge cases
        User::factory()->count(2)->instructor('external')->expired()->create();
    }
}
