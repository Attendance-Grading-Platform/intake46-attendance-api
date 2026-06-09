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

        $branch = \App\Models\Branch::create(['name' => 'Test Branch']);
        $track = \App\Models\Track::create(['name' => 'Test Track', 'branch_id' => $branch->id]);

        $cohort = Cohort::create([
            'track_id' => $track->id,
            'name' => 'cohort 1',
            'status' => 'active',
            'started_at' => now()->subMonth(),
            'ended_at' => now()->addMonths(6),
        ]);
        
        $cohort->students()->attach($student->id, ['enrolled_at' => now()]);
        $cohort->trackAdmins()->attach($admin->id);

        $engagementId = DB::table('engagements')->insertGetId([
            'cohort_id' => $cohort->id,
            'instructor_id' => $admin->id,
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

        // student misses session
        $ledger = AttendanceLedger::create([
            'student_id' => $student->id,
            'balance' => 250
        ]);

        $ledger->balance -= 25;
        $ledger->save();

        $this->assertEquals(225, $ledger->fresh()->balance);

        // student submit excuse
        $response = $this->actingAs($student, 'sanctum')->postJson('/api/v1/excuse-requests', [
            'session_id' => $session->id,
            'reason' => 'i was sick yesterday'
        ]);

        $response->assertStatus(201);
        $excuseId = $response->json('data.id');

        // admin approve the excuse
        $response2 = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/excuse-requests/$excuseId", [
            'status' => 'approved'
        ]);

        $response2->assertStatus(200);
        //check ledger refund
        $this->assertEquals(245, $ledger->fresh()->balance);
    }
}
