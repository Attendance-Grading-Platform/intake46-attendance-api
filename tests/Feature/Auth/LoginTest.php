<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────
    // Happy Path
    // ──────────────────────────────────────────────────────────

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->student()->create([
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'role'],
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'user' => [
                        'id'    => $user->id,
                        'email' => $user->email,
                        'role'  => 'student',
                    ],
                ],
            ]);

        // Confirm a token was actually persisted
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_deletes_previous_tokens(): void
    {
        $user = User::factory()->student()->create([
            'password' => 'secret123',
        ]);

        // Create an existing token
        $user->createToken('old_token');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Login again — old token should be wiped, new one created
        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    // ──────────────────────────────────────────────────────────
    // Invalid Credentials
    // ──────────────────────────────────────────────────────────

    public function test_login_with_wrong_password_returns_401(): void
    {
        $user = User::factory()->student()->create([
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Invalid email or password.',
            ]);
    }

    public function test_login_with_nonexistent_email_returns_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'ghost@example.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Invalid email or password.',
            ]);
    }

    // ──────────────────────────────────────────────────────────
    // Validation
    // ──────────────────────────────────────────────────────────

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ──────────────────────────────────────────────────────────
    // Account Lifecycle — Deactivated / Expired
    // ──────────────────────────────────────────────────────────

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->student()->inactive()->create([
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Your account has been deactivated.',
            ]);

        // No token should have been issued
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expired_user_cannot_login(): void
    {
        $user = User::factory()->student()->expired()->create([
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Your account has expired. Please contact your Branch Manager.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
