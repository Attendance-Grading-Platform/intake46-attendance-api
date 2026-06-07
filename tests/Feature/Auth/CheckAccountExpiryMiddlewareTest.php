<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckAccountExpiryMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────
    // Active user — middleware lets request through
    // ──────────────────────────────────────────────────────────

    public function test_active_user_can_access_protected_routes(): void
    {
        $user = User::factory()->student()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        // Hit the logout endpoint as a proxy for "any protected route"
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
    }

    // ──────────────────────────────────────────────────────────
    // Deactivated user — middleware blocks with 401
    // ──────────────────────────────────────────────────────────

    public function test_deactivated_user_is_blocked_by_middleware(): void
    {
        $user = User::factory()->student()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        // Deactivate the user AFTER they have a valid token
        $user->update(['is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Your account has been deactivated.',
            ]);

        // Middleware should have revoked the token
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ──────────────────────────────────────────────────────────
    // Expired user — middleware blocks with 401
    // ──────────────────────────────────────────────────────────

    public function test_expired_user_is_blocked_by_middleware(): void
    {
        $user = User::factory()->student()->create([
            'expiry_date' => now()->subDay()->toDateString(),
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Your account has expired. Please contact administration.',
            ]);

        // Middleware should have revoked the token
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_expiring_today_is_not_blocked(): void
    {
        $user = User::factory()->student()->create([
            'expiry_date' => now()->toDateString(),
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        // Today is NOT past the expiry — should be allowed
        $response->assertOk();
    }

    public function test_user_with_far_future_expiry_is_not_blocked(): void
    {
        $user = User::factory()->student()->create([
            'expiry_date' => now()->addYears(10)->toDateString(),
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        // Far-future expiry = effectively no expiry constraint
        $response->assertOk();
    }
}
