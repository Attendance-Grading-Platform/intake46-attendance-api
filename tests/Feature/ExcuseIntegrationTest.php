<?php

namespace Tests\Feature;

use App\Models\AttendanceLedger;
use App\Models\Cohort;
use App\Models\EngagementSession;
use App\Models\ExcuseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExcuseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_excuse_workflow_from_absence_to_refund()
    {
        // 1. setup users
        $student = User::create([
            'name' => 'student test',
            'email' => 'student@test.com',
            'password' => bcrypt('123456'),
            'role' => 'student',
            'is_active' => true,
            'expiry_date' => now()->addYear(),
        ]);

        $admin = User::create([
            'name' => 'admin test',
            'email' => 'admin@test.com',
            'password' => bcrypt('123456'),
            'role' => 'track_admin',
            'is_active' => true,
            'expiry_date' => now()->addYear(),
        ]);

        // setup branch, track, cohort and session
        $branch = \App\Models\Branch::create(['name' => 'Test Branch']);
        $track = \App\Models\Track::create(['name' => 'Test Track', 'branch_id' => $branch->id]);

        $cohort = Cohort::create([
            'track_id' => $track->id,
            'name' => 'cohort 1',
            'status' => 'active',
            'started_at' => now()->subMonth(),
            'ended_at' => now()->addMonths(6),
        ]);
        
        // attach student to cohort so admin policy allows review
        $cohort->students()->attach($student->id, ['enrolled_at' => now()]);
        
        // attach admin to cohort
        $cohort->trackAdmins()->attach($admin->id);

        $engagementId = DB::table('engagements')->insertGetId([
            'cohort_id' => $cohort->id,
            'instructor_id' => $admin->id, // just use admin as instructor for test
            'type' => 'lab',
            'start_date' => now()->subDays(2),
            'end_date' => now()->addDays(2),
            'scheduled_hours' => 3,
        ]);

        $session = EngagementSession::create([
            'engagement_id' => $engagementId,
            'session_date' => now()->subDay(),
            'delivered' => true,
        ]);

        // 2. student misses session (deduct 25 points)
        // start ledger with 250
        $ledger = AttendanceLedger::create([
            'student_id' => $student->id,
            'balance' => 250
        ]);

        // system deduct 25 for unexcused absence
        $ledger->balance -= 25;
        $ledger->save();

        $this->assertEquals(225, $ledger->fresh()->balance);

        // 3. student submit excuse
        $response = $this->actingAs($student, 'sanctum')->postJson('/api/v1/excuse-requests', [
            'session_id' => $session->id,
            'reason' => 'i was sick yesterday'
        ]);

        $response->assertStatus(201);
        $excuseId = $response->json('data.id');

        // 4. admin approve the excuse
        $response2 = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/excuse-requests/$excuseId", [
            'status' => 'approved'
        ]);

        $response2->assertStatus(200);

        // 5. check ledger refund 20 points (-25 becomes -5)
        // so 225 + 20 = 245
        $this->assertEquals(245, $ledger->fresh()->balance);
    }
}
