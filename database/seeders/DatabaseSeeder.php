<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * ┌─────────────────────────────────────────────────────┐
     * │  ITI ATTENDANCE PLATFORM — DEMO CREDENTIALS         │
     * │                                                      │
     * │  All accounts use password: "password"               │
     * │                                                      │
     * │  BRANCH MANAGER:                                     │
     * │    manager@iti.edu.eg                                │
     * │                                                      │
     * │  TRACK ADMINS:                                       │
     * │    karim.ashraf@iti.edu.eg    (Web Dev)              │
     * │    nour.samir@iti.edu.eg      (Mobile Dev)           │
     * │                                                      │
     * │  INSTRUCTORS:                                        │
     * │    amira.khaled@iti.edu.eg    (external)             │
     * │    youssef.nabil@iti.edu.eg   (external)             │
     * │    sara.elsayed@iti.edu.eg    (external)             │
     * │    ahmed.fouad@iti.edu.eg     (external)             │
     * │    laila.hassan@iti.edu.eg    (external)             │
     * │                                                      │
     * │  STUDENTS (Web):                                     │
     * │    ahmed.ali.46@student.iti.edu.eg                   │
     * │    salma.tamer.46@student.iti.edu.eg  (AT RISK)      │
     * │                                                      │
     * │  EDGE CASES:                                         │
     * │    expired@iti.edu.eg   (expired account)            │
     * │    inactive@iti.edu.eg  (deactivated account)        │
     * └─────────────────────────────────────────────────────┘
     */
    public function run(): void
    {
        $this->call([
            // ── Step 1: All users first ──────────────────
            UserSeeder::class,

            // ── Step 2: Academic structure ───────────────
            // Branch → Tracks → Cohorts → Courses →
            // Components → Enroll Students
            AcademicStructureSeeder::class,

            // ── Step 3: Lab groups ───────────────────────
            // Create groups → assign students → assign instructors
            LabGroupSeeder::class,

            // ── Step 4: Engagements + Sessions ───────────
            // Lectures, Labs, Business Sessions
            // Some delivered, some upcoming
            EngagementSeeder::class,

            // ── Step 5: Attendance + Ledger ──────────────
            // Scan records + ledger deductions + transactions
            // Creates at-risk students (ledger < 150)
            AttendanceLedgerSeeder::class,
            // ── Step 7: Grades ───────────────────────────
            // Raw scores → normalized → grand total
            // Overrides with notes
            // Creates at-risk students (course grade < 60)
            GradeSeeder::class,

            // ── Step 8: Submissions ───────────────────────
            // On time, late (with penalty), missing
            // URL and file types
            SubmissionSeeder::class,

            // ── Step 9: Announcements ─────────────────────
            // Post-it style announcements for students
            AnnouncementSeeder::class,

            // ── Step 10: Billing ──────────────────────────
            // Tracking billable hours for instructors
            BillingSeeder::class,

            // ── Step 11: Engagement module demo scenario ──
            EngagementModuleSeeder::class,
        ]);
    }
}