<?php

namespace Database\Seeders;

use App\Models\AttendanceLedger;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Cohort;
use App\Models\Engagement;
use App\Models\EngagementSession;
use App\Models\Track;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * EngagementModuleSeeder — Seeds a realistic relational scenario
 * for the Engagement & Attendance module.
 *
 * Creates: Branch → Track → Cohort → Engagement → Sessions → AttendanceRecords
 *          + AttendanceLedgers for each student.
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │  SEEDED TEST ACCOUNTS (password: "password")               │
 * │                                                            │
 * │  lab-instructor@iti.test  → instructor (external)          │
 * │  student-a@iti.test       → student (on-time scan)         │
 * │  student-b@iti.test       → student (late scan)            │
 * └─────────────────────────────────────────────────────────────┘
 */
class EngagementModuleSeeder extends Seeder
{
    public function run(): void
    {
        // ─────────────────────────────────────────────────────────
        // 1. Infrastructure: Branch → Track → Cohort
        // ─────────────────────────────────────────────────────────

        $branch = Branch::firstOrCreate(['name' => 'Cairo HQ']);

        $track = Track::firstOrCreate(
            ['name' => 'Web Development', 'branch_id' => $branch->id],
        );

        $cohort = Cohort::firstOrCreate(
            ['name' => 'Web — Intake 46', 'track_id' => $track->id],
            [
                'status'     => 'active',
                'started_at' => Carbon::today()->subWeeks(4),
                'ended_at'   => Carbon::today()->addMonths(5),
            ],
        );

        // ─────────────────────────────────────────────────────────
        // 2. Users: 1 Instructor + 2 Students
        // ─────────────────────────────────────────────────────────

        $instructor = User::firstOrCreate(
            ['email' => 'lab-instructor@iti.test'],
            [
                'name'              => 'Lab Instructor',
                'password'          => Hash::make('password'),
                'role'              => 'instructor',
                'is_active'         => true,
                'expiry_date'       => Carbon::today()->addMonths(6),
                'compensation_type' => 'external',
                'hourly_rate'       => 350.00,
                'fixed_salary'      => null,
            ],
        );

        $studentA = User::firstOrCreate(
            ['email' => 'student-a@iti.test'],
            [
                'name'        => 'Alice Student',
                'password'    => Hash::make('password'),
                'role'        => 'student',
                'is_active'   => true,
                'expiry_date' => Carbon::today()->addMonths(9),
            ],
        );

        $studentB = User::firstOrCreate(
            ['email' => 'student-b@iti.test'],
            [
                'name'        => 'Bob Student',
                'password'    => Hash::make('password'),
                'role'        => 'student',
                'is_active'   => true,
                'expiry_date' => Carbon::today()->addMonths(9),
            ],
        );

        // Enroll students in the cohort (pivot: cohort_students)
        $cohort->students()->syncWithoutDetaching([
            $studentA->id,
            $studentB->id,
        ]);

        // ─────────────────────────────────────────────────────────
        // 3. Engagement: 1 Lab engagement, 4 scheduled hours
        // ─────────────────────────────────────────────────────────

        $engagement = Engagement::firstOrCreate(
            [
                'instructor_id' => $instructor->id,
                'type'          => 'lab',
            ],
            [
                'start_date'      => Carbon::today()->subWeeks(2),
                'end_date'        => Carbon::today()->addWeeks(2),
                'hours_per_session' => 4,
            ],
        );
        $engagement->cohorts()->syncWithoutDetaching([$cohort->id]);

        $engagement->cohorts()->syncWithoutDetaching([$cohort->id]);

        // ─────────────────────────────────────────────────────────
        // 4. Sessions: 2 dates (1 delivered, 1 upcoming)
        // ─────────────────────────────────────────────────────────

        $deliveredSession = EngagementSession::firstOrCreate(
            [
                'engagement_id' => $engagement->id,
                'session_date'  => Carbon::today()->subWeek(),
            ],
            ['delivered' => true],
        );

        EngagementSession::firstOrCreate(
            [
                'engagement_id' => $engagement->id,
                'session_date'  => Carbon::today()->addWeek(),
            ],
            ['delivered' => false],
        );

        // ─────────────────────────────────────────────────────────
        // 5. Attendance Ledgers (ATT-4: balance starts at 250)
        // ─────────────────────────────────────────────────────────

        AttendanceLedger::firstOrCreate(
            ['student_id' => $studentA->id],
            ['cohort_id'  => $cohort->id, 'balance' => 250],
        );

        AttendanceLedger::firstOrCreate(
            ['student_id' => $studentB->id],
            ['cohort_id'  => $cohort->id, 'balance' => 250],
        );

        // ─────────────────────────────────────────────────────────
        // 6. Attendance Records for the delivered session
        //    - Alice: arrived on time (09:00), left at 13:00
        //    - Bob:   arrived late   (10:30), left at 13:00
        // ─────────────────────────────────────────────────────────

        $sessionDate = $deliveredSession->session_date;

        AttendanceRecord::firstOrCreate(
            [
                'session_id' => $deliveredSession->id,
                'student_id' => $studentA->id,
            ],
            [
                'track_id'   => $track->id,
                'status'     => 'present',
                'arrived_at' => $sessionDate->copy()->setTime(9, 0),
                'left_at'    => $sessionDate->copy()->setTime(13, 0),
            ],
        );

        AttendanceRecord::firstOrCreate(
            [
                'session_id' => $deliveredSession->id,
                'student_id' => $studentB->id,
            ],
            [
                'track_id'   => $track->id,
                'status'     => 'present',
                'arrived_at' => $sessionDate->copy()->setTime(10, 30),
                'left_at'    => $sessionDate->copy()->setTime(13, 0),
            ],
        );

        // ─────────────────────────────────────────────────────────
        // 7. Seed an Excuse Request for testing frontend
        // ─────────────────────────────────────────────────────────
        \App\Models\ExcuseRequest::firstOrCreate(
            [
                'student_id' => $studentA->id,
                'session_id' => $deliveredSession->id,
            ],
            [
                'status' => 'requested',
                'reason' => 'I had a doctor appointment and could not attend.',
            ]
        );

        $this->command->info('✅ EngagementModuleSeeder completed.');
        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Instructor',         1],
                ['Students',           2],
                ['Cohort',             1],
                ['Engagement (Lab)',   1],
                ['Sessions',           2],
                ['Attendance Ledgers', 2],
                ['Attendance Records', 2],
            ],
        );
    }
}
