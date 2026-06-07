<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->student()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJson([
                'status'  => 'success',
                'message' => 'Logged out successfully.',
            ]);

        // Token should be revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_only_revokes_current_token(): void
    {
        $user = User::factory()->student()->create();

        // Simulate two devices
        $tokenA = $user->createToken('device_a')->plainTextToken;
        $tokenB = $user->createToken('device_b')->plainTextToken;
        $this->assertDatabaseCount('personal_access_tokens', 2);

        // Logout from device A
        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Only device A's token should be gone
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Device B's token should still authenticate successfully
        $this->withHeader('Authorization', "Bearer {$tokenB}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Unauthenticated.',
            ]);
    }
}
