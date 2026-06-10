<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Cohort;
use App\Models\Engagement;
use App\Models\EngagementSession;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EngagementApiTest — Feature tests for the Engagement Scheduling
 * & Session Generation API.
 *
 * Covers: POST /api/v1/engagements (create + auto-generate sessions)
 *         GET  /api/v1/engagements (index)
 *         GET  /api/v1/engagements/{id} (show)
 *         Validation rules (StoreEngagementRequest)
 *
 * Uses SQLite in-memory (phpunit.xml) with RefreshDatabase.
 */
class EngagementApiTest extends TestCase
{
    use RefreshDatabase;

    /* ──────────────────────────────────────────────
     |  Shared Test Data Helpers
     |──────────────────────────────────────────────*/

    private User   $instructor;
    private User   $student;
    private User   $trackAdmin;
    private Cohort $cohort;

    /**
     * Boot the FK chain: Branch → Track → Cohort + Users.
     * Runs before each test method.
     */
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

        $this->instructor = User::create([
            'name'        => 'Test Instructor',
            'email'       => 'instructor@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'instructor',
            'is_active'   => true,
            'expiry_date' => now()->addYear(),
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
            'email'       => 'admin@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'track_admin',
            'is_active'   => true,
            'expiry_date' => now()->addYear(),
        ]);
        
        $this->cohort->trackAdmins()->attach($this->trackAdmin->id);
    }

    /**
     * Authenticate as the track admin and return Sanctum headers.
     */
    private function authHeaders(): array
    {
        $token = $this->trackAdmin->createToken('test')->plainTextToken;
        return [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Build a valid engagement payload. Override any key as needed.
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'cohort_id'       => $this->cohort->id,
            'instructor_id'   => $this->instructor->id,
            'type'            => 'lab',
            'start_date'      => now()->addDay()->toDateString(),
            'end_date'        => now()->addWeeks(2)->toDateString(),
            'scheduled_hours' => 4,
            'days_of_week'    => [0, 3],  // Sunday + Wednesday
        ], $overrides);
    }

    /* ──────────────────────────────────────────────
     |  POST /api/v1/engagements — Happy Path
     |──────────────────────────────────────────────*/

    public function test_store_creates_engagement_and_generates_sessions(): void
    {
        $response = $this->postJson(
            '/api/v1/engagements',
            $this->validPayload(),
            $this->authHeaders(),
        );

        $response->assertStatus(201)->dump()
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         'engagement' => ['id', 'instructor_id', 'type', 'start_date', 'end_date', 'scheduled_hours', 'cohort', 'cohorts'],
                         'sessions',
                         'sessions_count',
                     ],
                 ]);

        // Verify DB records exist
        $this->assertDatabaseHas('engagements', [
            'instructor_id' => $this->instructor->id,
            'type'          => 'lab',
        ]);

        $engagementId = $response->json('data.engagement.id');

        $this->assertDatabaseHas('engagement_cohorts', [
            'engagement_id' => $engagementId,
            'cohort_id'     => $this->cohort->id,
        ]);

        // Verify at least 1 session was generated
        $engagementId = $response->json('data.engagement.id');
        $this->assertGreaterThan(0, EngagementSession::where('engagement_id', $engagementId)->count());
    }

    public function test_generated_sessions_only_fall_on_requested_days(): void
    {
        // Request sessions only on Mondays (1) and Fridays (5)
        $payload = $this->validPayload([
            'start_date'   => '2026-07-01',  // Wednesday
            'end_date'     => '2026-07-31',  // Friday
            'days_of_week' => [1, 5],        // Monday + Friday
        ]);

        $response = $this->postJson('/api/v1/engagements', $payload, $this->authHeaders());
        $response->assertStatus(201)->dump();

        $sessions = collect($response->json('data.sessions'));
        $this->assertGreaterThan(0, $sessions->count());

        // Every generated session must fall on Monday (1) or Friday (5)
        $sessions->each(function (array $session) {
            $dayOfWeek = \Carbon\Carbon::parse($session['session_date'])->dayOfWeek;
            $this->assertContains($dayOfWeek, [1, 5],
                "Session on {$session['session_date']} falls on day {$dayOfWeek}, expected Mon(1) or Fri(5)."
            );
        });
    }

    public function test_sessions_are_all_marked_as_not_delivered(): void
    {
        $response = $this->postJson('/api/v1/engagements', $this->validPayload(), $this->authHeaders());
        $response->assertStatus(201)->dump();

        $sessions = collect($response->json('data.sessions'));
        $sessions->each(function (array $session) {
            $this->assertFalse($session['delivered'], 'Newly generated sessions must have delivered = false.');
        });
    }

    public function test_store_response_includes_instructor_and_cohort(): void
    {
        $response = $this->postJson('/api/v1/engagements', $this->validPayload(), $this->authHeaders());

        $response->assertStatus(201)->dump()
                 ->assertJsonPath('data.engagement.instructor.email', 'instructor@test.com')
                 ->assertJsonPath('data.engagement.cohort.name', 'Test Cohort');
    }

    /* ──────────────────────────────────────────────
     |  POST /api/v1/engagements — Validation
     |──────────────────────────────────────────────*/

    public function test_store_requires_all_fields(): void
    {
        $response = $this->postJson('/api/v1/engagements', [], $this->authHeaders());

        $response->assertStatus(422)
                 ->assertJsonValidationErrors([
                     'cohort_id',
                     'instructor_id',
                     'type',
                     'start_date',
                     'end_date',
                     'scheduled_hours',
                     'days_of_week',
                 ]);
    }

    public function test_store_rejects_invalid_cohort_id(): void
    {
        $response = $this->postJson(
            '/api/v1/engagements',
            $this->validPayload(['cohort_id' => 9999]),
            $this->authHeaders(),
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['cohort_id']);
    }

    public function test_store_rejects_non_instructor_user(): void
    {
        $response = $this->postJson(
            '/api/v1/engagements',
            $this->validPayload(['instructor_id' => $this->student->id]),
            $this->authHeaders(),
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['instructor_id']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $response = $this->postJson(
            '/api/v1/engagements',
            $this->validPayload(['type' => 'InvalidType']),
            $this->authHeaders(),
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_past_start_date(): void
    {
        $response = $this->postJson(
            '/api/v1/engagements',
            $this->validPayload(['start_date' => '2020-01-01']),
            $this->authHeaders(),
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['start_date']);
    }

    public function test_store_rejects_end_date_before_start_date(): void
    {
        $response = $this->postJson(
            '/api/v1/engagements',
            $this->validPayload([
                'start_date' => now()->addWeek()->toDateString(),
                'end_date'   => now()->addDay()->toDateString(),
            ]),
            $this->authHeaders(),
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['end_date']);
    }

    public function test_store_rejects_zero_scheduled_hours(): void
    {
        $response = $this->postJson(
            '/api/v1/engagements',
            $this->validPayload(['scheduled_hours' => 0]),
            $this->authHeaders(),
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['scheduled_hours']);
    }

    public function test_store_rejects_invalid_day_of_week(): void
    {
        $response = $this->postJson(
            '/api/v1/engagements',
            $this->validPayload(['days_of_week' => [9]]),
            $this->authHeaders(),
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['days_of_week.0']);
    }

    public function test_store_rejects_empty_days_of_week(): void
    {
        $response = $this->postJson(
            '/api/v1/engagements',
            $this->validPayload(['days_of_week' => []]),
            $this->authHeaders(),
        );

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['days_of_week']);
    }

    /* ──────────────────────────────────────────────
     |  POST /api/v1/engagements — Auth Guard
     |──────────────────────────────────────────────*/

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/engagements', $this->validPayload());

        $response->assertStatus(401);
    }

    /* ──────────────────────────────────────────────
     |  POST /api/v1/engagements — Atomicity
     |──────────────────────────────────────────────*/

    public function test_store_is_atomic_engagement_and_sessions_created_together(): void
    {
        $beforeEngagements = Engagement::count();
        $beforeSessions    = EngagementSession::count();

        $this->postJson('/api/v1/engagements', $this->validPayload(), $this->authHeaders())
             ->assertStatus(201);

        $this->assertGreaterThan($beforeEngagements, Engagement::count());
        $this->assertGreaterThan($beforeSessions, EngagementSession::count());
    }

    /* ──────────────────────────────────────────────
     |  GET /api/v1/engagements — Index
     |──────────────────────────────────────────────*/

    public function test_index_returns_engagements(): void
    {
        // Seed one engagement via the store endpoint
        $this->postJson('/api/v1/engagements', $this->validPayload(), $this->authHeaders())
             ->assertStatus(201);

        $response = $this->getJson('/api/v1/engagements', $this->authHeaders());

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/engagements')->assertStatus(401);
    }

    /* ──────────────────────────────────────────────
     |  GET /api/v1/engagements/{id} — Show
     |──────────────────────────────────────────────*/

    public function test_show_returns_engagement_with_sessions(): void
    {
        $createResponse = $this->postJson('/api/v1/engagements', $this->validPayload(), $this->authHeaders());
        $engagementId   = $createResponse->json('data.engagement.id');

        $response = $this->getJson("/api/v1/engagements/{$engagementId}", $this->authHeaders());

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.id', $engagementId)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'type', 'start_date', 'end_date',
                         'instructor' => ['id', 'name', 'email'],
                         'cohort'     => ['id', 'name'],
                         'sessions',
                     ],
                 ]);
    }

    public function test_show_returns_404_for_nonexistent_engagement(): void
    {
        $this->getJson('/api/v1/engagements/9999', $this->authHeaders())
             ->assertStatus(404);
    }
}
