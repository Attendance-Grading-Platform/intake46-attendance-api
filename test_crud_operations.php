<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Cohort;
use App\Models\Track;
use App\Models\Branch;
use App\Models\Engagement;
use App\Models\EngagementSession;
use App\Models\AttendanceRecord;
use App\Models\AttendanceLedger;
use App\Models\ExcuseRequest;
use Illuminate\Support\Facades\DB;

echo "Starting CRUD tests...\n\n";

DB::beginTransaction();

try {
    // 1. Setup Base Data
    $branch = Branch::create(['name' => 'Test Branch']);
    $track = Track::create(['branch_id' => $branch->id, 'name' => 'Test Track']);
    $cohort = Cohort::create(['track_id' => $track->id, 'name' => 'Test Cohort', 'status' => 'active', 'started_at' => now(), 'ended_at' => now()->addMonths(3)]);
    
    $instructor = User::factory()->create(['role' => 'instructor']);
    $student = User::factory()->create(['role' => 'student']);
    $student->enrolledCohorts()->attach($cohort->id, ['enrolled_at' => now()]);

    echo "✅ Base data created.\n";

    // 2. ENGAGEMENTS & ENGAGEMENT_COHORTS
    // Create
    $engagement = Engagement::create([
        'instructor_id' => $instructor->id,
        'type' => 'lecture',
        'start_date' => now(),
        'end_date' => now()->addDays(7),
        'hours_per_session' => 10,
    ]);
    $engagement->cohorts()->attach($cohort->id);
    echo "✅ Engagement Created (ID: {$engagement->id}).\n";

    // Read
    $readEngagement = Engagement::with('cohorts')->find($engagement->id);
    if (!$readEngagement || $readEngagement->cohorts->isEmpty()) {
        throw new Exception("Failed to read Engagement or Engagement Cohorts");
    }
    echo "✅ Engagement Read successfully.\n";

    // Update
    $engagement->update(['hours_per_session' => 15]);
    echo "✅ Engagement Updated.\n";

    // 3. SESSIONS
    // Create
    $session = EngagementSession::create([
        'engagement_id' => $engagement->id,
        'session_date' => now()->addDay(),
        'delivered' => false,
    ]);
    echo "✅ Session Created (ID: {$session->id}).\n";

    // Read & Update
    $session->update(['delivered' => true]);
    echo "✅ Session Updated.\n";

    // 4. ATTENDANCE_RECORDS
    // Create
    $attendanceRecord = AttendanceRecord::create([
        'session_id' => $session->id,
        'student_id' => $student->id,
        'track_id' => $track->id,
        'status' => 'present',
        'arrived_at' => now(),
    ]);
    echo "✅ Attendance Record Created.\n";
    
    // Read & Update
    $attendanceRecord->update(['left_at' => now()->addHours(2)]);
    echo "✅ Attendance Record Updated.\n";

    // 5. ATTENDANCE_LEDGERS
    // Create
    $ledger = AttendanceLedger::create([
        'student_id' => $student->id,
        'cohort_id' => $cohort->id,
        'balance' => 250,
    ]);
    echo "✅ Attendance Ledger Created.\n";

    // Read & Update
    $ledger->deductUnexcused();
    if ($ledger->balance !== 225) throw new Exception("Ledger deduct logic failed");
    echo "✅ Attendance Ledger Updated (Balance: {$ledger->balance}).\n";

    // 6. EXCUSE_REQUESTS
    // Create
    $excuse = ExcuseRequest::create([
        'student_id' => $student->id,
        'session_id' => $session->id,
        'status' => 'requested',
        'reason' => 'Doctor appointment',
    ]);
    echo "✅ Excuse Request Created.\n";

    // Read & Update
    $excuse->update(['status' => 'approved', 'reviewed_by' => $instructor->id, 'reviewed_at' => now()]);
    echo "✅ Excuse Request Updated.\n";

    // 7. STUDENT_RISK_FLAGS
    // The model doesn't exist, use DB facade
    DB::table('student_risk_flags')->insert([
        'student_id' => $student->id,
        'cohort_id' => $cohort->id,
        'at_risk' => true,
        'reasons' => json_encode(['Low attendance']),
    ]);
    echo "✅ Student Risk Flag Created.\n";

    $riskFlag = DB::table('student_risk_flags')->where('student_id', $student->id)->first();
    if (!$riskFlag) throw new Exception("Failed to read Risk Flag");
    
    DB::table('student_risk_flags')->where('id', $riskFlag->id)->update(['resolved_at' => now()]);
    echo "✅ Student Risk Flag Updated.\n";

    // 8. DELETES (in reverse order)
    DB::table('student_risk_flags')->where('id', $riskFlag->id)->delete();
    $excuse->delete();
    $ledger->delete();
    $attendanceRecord->delete();
    $session->delete();
    $engagement->cohorts()->detach();
    $engagement->delete();
    
    echo "✅ All Deletes successful.\n";
    echo "\n🎉 All CRUD operations completed successfully!\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

DB::rollBack();
