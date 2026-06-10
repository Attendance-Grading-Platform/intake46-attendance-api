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
use App\Services\AttendanceLedgerService;
use Illuminate\Support\Facades\DB;

echo "Starting AttendanceLedgerService tests...\n\n";

DB::beginTransaction();

try {
    $service = new AttendanceLedgerService();

    // Setup basic entities
    $branch = Branch::create(['name' => 'Ledger Test Branch']);
    $track = Track::create(['branch_id' => $branch->id, 'name' => 'Ledger Test Track']);
    $cohort = Cohort::create([
        'track_id' => $track->id, 
        'name' => 'Ledger Test Cohort', 
        'status' => 'active', 
        'started_at' => now(), 
        'ended_at' => now()->addMonths(3)
    ]);
    
    $student = User::factory()->create(['role' => 'student']);
    $student->enrolledCohorts()->attach($cohort->id, ['enrolled_at' => now()]);

    $engagement = Engagement::create([
        'instructor_id' => User::factory()->create(['role' => 'instructor'])->id,
        'type' => 'lecture',
        'start_date' => now(),
        'end_date' => now()->addDays(7),
        'hours_per_session' => 5,
    ]);
    $engagement->cohorts()->attach($cohort->id);

    $session1 = EngagementSession::create(['engagement_id' => $engagement->id, 'session_date' => now()->addDay(), 'delivered' => true]);
    $session2 = EngagementSession::create(['engagement_id' => $engagement->id, 'session_date' => now()->addDays(2), 'delivered' => true]);

    echo "✅ Setup complete.\n";

    // --- TEST 1: Deduct Unexcused (-25) ---
    $ledger1 = $service->deductUnexcused($student, $session1->id, $track->id);
    if ($ledger1->balance !== 225) {
        throw new \Exception("Deduct Unexcused failed. Expected 225, got {$ledger1->balance}");
    }
    echo "✅ Deduct Unexcused (-25) passed.\n";

    // --- TEST 2: Idempotency Guard (Unexcused) ---
    $ledger2 = $service->deductUnexcused($student, $session1->id, $track->id);
    if ($ledger2->balance !== 225) {
        throw new \Exception("Idempotency failed. Expected 225, got {$ledger2->balance}");
    }
    echo "✅ Idempotency Guard (Unexcused) passed.\n";

    // --- TEST 3: Deduct Excused (-5) ---
    $ledger3 = $service->deductExcused($student, $session2->id, $track->id);
    if ($ledger3->balance !== 220) {
        throw new \Exception("Deduct Excused failed. Expected 220, got {$ledger3->balance}");
    }
    echo "✅ Deduct Excused (-5) passed.\n";

    // --- TEST 4: Idempotency Guard (Excused) ---
    $ledger4 = $service->deductExcused($student, $session2->id, $track->id);
    if ($ledger4->balance !== 220) {
        throw new \Exception("Idempotency failed. Expected 220, got {$ledger4->balance}");
    }
    echo "✅ Idempotency Guard (Excused) passed.\n";

    // --- TEST 5: Floor at 0 ---
    // Force balance down
    $ledger4->update(['balance' => 10]);
    // Deduct 25 -> Should floor to 0, not -15
    $session3 = EngagementSession::create(['engagement_id' => $engagement->id, 'session_date' => now()->addDays(3), 'delivered' => true]);
    $ledger5 = $service->deductUnexcused($student, $session3->id, $track->id);
    if ($ledger5->balance !== 0) {
        throw new \Exception("Floor at 0 failed. Expected 0, got {$ledger5->balance}");
    }
    echo "✅ Floor at 0 passed.\n";

    // --- TEST 6: Risk Flag Evaluation ---
    $riskFlagExists = DB::table('student_risk_flags')->where('student_id', $student->id)->where('at_risk', true)->exists();
    if (!$riskFlagExists) {
        throw new \Exception("Risk flag not created when balance dropped to 0.");
    }
    echo "✅ Risk Flag Evaluation passed.\n";

    echo "\n🎉 All AttendanceLedgerService tests passed!\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

DB::rollBack();
