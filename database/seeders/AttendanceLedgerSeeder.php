<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\AttendanceLedger;

class AttendanceLedgerSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', 'student')->get();

        foreach ($students as $student) {
            $cohortId = $student->enrolledCohorts()->first()?->id ?? \App\Models\Cohort::first()?->id ?? 1;
            AttendanceLedger::firstOrCreate(
                ['student_id' => $student->id],
                [
                    'cohort_id' => $cohortId,
                    'balance' => AttendanceLedger::INITIAL_BALANCE
                ]
            );
        }
    }
}
