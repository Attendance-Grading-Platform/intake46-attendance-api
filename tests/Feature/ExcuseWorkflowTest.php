<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AttendanceLedger;
use App\Models\Branch;
use App\Models\Cohort;
use App\Models\EngagementSession;
use App\Models\ExcuseRequest;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExcuseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $trackAdmin;
    private User $branchManager;
    private User $instructor;
    private Cohort $cohort;
    private EngagementSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create(['name' => 'Test Branch']);
        $track  = Track::create(['name' => 'Test Track', 'branch_id' => $branch->id]);

        $this->cohort = Cohort::create([
            'track_id'   => $track->id,
            'name'       => 'Test Cohort',
            'status'     => 'active',
            'started_at' => now()->subMonth(),
            'ended_at'   => now()->addMonths(6),
        ]);

        $this->student = User::create([
            'name'        => 'Test Student',
            'email'       => 'student@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'student',
            'is_active'   => true,
            'expiry_date' => now()->addYear(),
        ]);

        $this->trackAdmin = User::create([
            'name'        => 'Test Track Admin',
            'email'       => 'trackadmin@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'track_admin',
            'is_active'   => true,
            'expiry_date' => now()->addYear(),
        ]);

        $this->branchManager = User::create([
            'name'        => 'Test Branch Manager',
            'email'       => 'manager@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'branch_manager',
            'is_active'   => true,
            'expiry_date' => now()->addYear(),
        ]);

        $this->instructor = User::create([
            'name'        => 'Test Instructor',
            'email'       => 'instructor@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'instructor',
            'is_active'   => true,
            'expiry_date' => now()->addYear(),
        ]);

        // enroll student and assign track admin to cohort
        $this->cohort->students()->attach($this->student->id, ['enrolled_at' => now()]);
        $this->cohort->trackAdmins()->attach($this->trackAdmin->id);

        // create engagement using DB directly because cohort_id not in fillable
        $engagementId = DB::table('engagements')->insertGetId([
            'cohort_id'       => $this->cohort->id,
            'instructor_id'   => $this->instructor->id,
            'type'            => 'lab',
            'start_date'      => now()->subWeek()->toDateString(),
            'end_date'        => now()->addWeek()->toDateString(),
            'scheduled_hours' => 3,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->session = EngagementSession::create([
            'engagement_id' => $engagementId,
            'session_date'  => now()->subDay()->toDateString(),
            'delivered'     => true,
        ]);
    }

    // get auth headers for a user
    private function authAs(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;
        return [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ];
    }

    // create excuse request directly in db
    private function makeExcuse(string $status = 'requested'): ExcuseRequest
    {
        return ExcuseRequest::create([
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'status'     => $status,
            'reason'     => 'I was sick',
        ]);
    }

    /* --------------------------------------------------------
     |  POST /api/v1/excuse-requests
     |--------------------------------------------------------*/

    public function test_student_can_submit_excuse_request(): void
    {
        $response = $this->postJson('/api/v1/excuse-requests', [
            'session_id' => $this->session->id,
            'reason'     => 'I was sick that day',
        ], $this->authAs($this->student));

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('excuse_requests', [
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'status'     => 'requested',
        ]);
    }

    public function test_student_cannot_submit_duplicate_excuse_for_same_session(): void
    {
        $this->makeExcuse();

        $response = $this->postJson('/api/v1/excuse-requests', [
            'session_id' => $this->session->id,
            'reason'     => 'another reason',
        ], $this->authAs($this->student));

        $response->assertStatus(422);
    }

    public function test_excuse_requires_reason_field(): void
    {
        $response = $this->postJson('/api/v1/excuse-requests', [
            'session_id' => $this->session->id,
        ], $this->authAs($this->student));

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['reason']);
    }

    public function test_excuse_requires_valid_session_id(): void
    {
        $response = $this->postJson('/api/v1/excuse-requests', [
            'session_id' => 9999,
            'reason'     => 'sick',
        ], $this->authAs($this->student));

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['session_id']);
    }

    public function test_instructor_cannot_submit_excuse(): void
    {
        $response = $this->postJson('/api/v1/excuse-requests', [
            'session_id' => $this->session->id,
            'reason'     => 'I am instructor',
        ], $this->authAs($this->instructor));

        $response->assertStatus(403);
    }

    public function test_track_admin_cannot_submit_excuse(): void
    {
        $response = $this->postJson('/api/v1/excuse-requests', [
            'session_id' => $this->session->id,
            'reason'     => 'I am track admin',
        ], $this->authAs($this->trackAdmin));

        $response->assertStatus(403);
    }

    public function test_submit_requires_authentication(): void
    {
        $this->postJson('/api/v1/excuse-requests', [
            'session_id' => $this->session->id,
            'reason'     => 'sick',
        ])->assertStatus(401);
    }

    /* --------------------------------------------------------
     |  GET /api/v1/excuse-requests
     |--------------------------------------------------------*/

    public function test_student_can_see_only_own_excuse_requests(): void
    {
        $this->makeExcuse();

        $response = $this->getJson('/api/v1/excuse-requests', $this->authAs($this->student));

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('student_id');
        $this->assertTrue($ids->every(fn($id) => $id === $this->student->id));
    }

    public function test_track_admin_can_see_excuse_requests_of_his_cohort_students(): void
    {
        $this->makeExcuse();

        $response = $this->getJson('/api/v1/excuse-requests', $this->authAs($this->trackAdmin));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_branch_manager_can_see_all_excuse_requests(): void
    {
        $this->makeExcuse();

        $response = $this->getJson('/api/v1/excuse-requests', $this->authAs($this->branchManager));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /* --------------------------------------------------------
     |  GET /api/v1/excuse-requests/{id}
     |--------------------------------------------------------*/

    public function test_student_can_view_own_excuse(): void
    {
        $excuse = $this->makeExcuse();

        $response = $this->getJson("/api/v1/excuse-requests/{$excuse->id}", $this->authAs($this->student));

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $excuse->id);
    }

    public function test_student_cannot_view_other_student_excuse(): void
    {
        $excuse = $this->makeExcuse();

        $otherStudent = User::create([
            'name'        => 'Other',
            'email'       => 'other2@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'student',
            'is_active'   => true,
            'expiry_date' => now()->addYear(),
        ]);

        $response = $this->getJson("/api/v1/excuse-requests/{$excuse->id}", $this->authAs($otherStudent));

        $response->assertStatus(403);
    }

    /* --------------------------------------------------------
     |  PATCH /api/v1/excuse-requests/{id}
     |--------------------------------------------------------*/

    public function test_track_admin_can_approve_excuse(): void
    {
        $excuse = $this->makeExcuse();

        AttendanceLedger::create(['student_id' => $this->student->id, 'balance' => 225]);

        $response = $this->patchJson("/api/v1/excuse-requests/{$excuse->id}", [
            'status' => 'approved',
        ], $this->authAs($this->trackAdmin));

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('excuse_requests', [
            'id'          => $excuse->id,
            'status'      => 'approved',
            'reviewed_by' => $this->trackAdmin->id,
        ]);
    }

    public function test_approve_excuse_adjusts_ledger_balance(): void
    {
        $excuse = $this->makeExcuse();

        // student had 225 (250 - 25 for unexcused absence)
        AttendanceLedger::create(['student_id' => $this->student->id, 'balance' => 225]);

        $this->patchJson("/api/v1/excuse-requests/{$excuse->id}", [
            'status' => 'approved',
        ], $this->authAs($this->trackAdmin));

        // should become 245 because -25 changes to -5 (+20 net)
        $this->assertDatabaseHas('attendance_ledgers', [
            'student_id' => $this->student->id,
            'balance'    => 245,
        ]);
    }

    public function test_track_admin_can_reject_excuse(): void
    {
        $excuse = $this->makeExcuse();

        $response = $this->patchJson("/api/v1/excuse-requests/{$excuse->id}", [
            'status' => 'rejected',
        ], $this->authAs($this->trackAdmin));

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'rejected');
    }

    public function test_reject_excuse_does_not_change_ledger(): void
    {
        $excuse = $this->makeExcuse();

        AttendanceLedger::create(['student_id' => $this->student->id, 'balance' => 225]);

        $this->patchJson("/api/v1/excuse-requests/{$excuse->id}", [
            'status' => 'rejected',
        ], $this->authAs($this->trackAdmin));

        // balance stays the same when rejected
        $this->assertDatabaseHas('attendance_ledgers', [
            'student_id' => $this->student->id,
            'balance'    => 225,
        ]);
    }

    public function test_cannot_review_already_reviewed_excuse(): void
    {
        $excuse = $this->makeExcuse('approved');

        $response = $this->patchJson("/api/v1/excuse-requests/{$excuse->id}", [
            'status' => 'rejected',
        ], $this->authAs($this->trackAdmin));

        $response->assertStatus(422);
    }

    public function test_student_cannot_review_excuse(): void
    {
        $excuse = $this->makeExcuse();

        $response = $this->patchJson("/api/v1/excuse-requests/{$excuse->id}", [
            'status' => 'approved',
        ], $this->authAs($this->student));

        $response->assertStatus(403);
    }

    public function test_review_requires_valid_status(): void
    {
        $excuse = $this->makeExcuse();

        $response = $this->patchJson("/api/v1/excuse-requests/{$excuse->id}", [
            'status' => 'maybe',
        ], $this->authAs($this->trackAdmin));

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['status']);
    }

    /* --------------------------------------------------------
     |  DELETE /api/v1/excuse-requests/{id}
     |--------------------------------------------------------*/

    public function test_branch_manager_can_delete_excuse(): void
    {
        $excuse = $this->makeExcuse();

        $response = $this->deleteJson("/api/v1/excuse-requests/{$excuse->id}", [], $this->authAs($this->branchManager));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('excuse_requests', ['id' => $excuse->id]);
    }

    public function test_student_cannot_delete_excuse(): void
    {
        $excuse = $this->makeExcuse();

        $response = $this->deleteJson("/api/v1/excuse-requests/{$excuse->id}", [], $this->authAs($this->student));

        $response->assertStatus(403);
    }

    public function test_track_admin_cannot_delete_excuse(): void
    {
        $excuse = $this->makeExcuse();

        $response = $this->deleteJson("/api/v1/excuse-requests/{$excuse->id}", [], $this->authAs($this->trackAdmin));

        $response->assertStatus(403);
    }
}
