<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),

            // Base Definition defaults to an active Student
            'role' => 'student',
            'is_active' => true,
            'expiry_date' => now()->addMonths(9)->toDateString(),
            'compensation_type' => null,
            'hourly_rate' => null,
            'fixed_salary' => null,
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Role States
    // ──────────────────────────────────────────────────────────

    /**
     * Explicit student state — identical to definition() defaults.
     * Exists for readability parity: `->student()` vs `->instructor()`.
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'student',
            'compensation_type' => null,
            'hourly_rate' => null,
            'fixed_salary' => null,
        ]);
    }

    public function branchManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'branch_manager',
            'expiry_date' => now()->addYears(5)->toDateString(),
            'compensation_type' => 'internal',
            'hourly_rate' => null,
            'fixed_salary' => 25000.00,
        ]);
    }

    public function trackAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'track_admin',
            'expiry_date' => now()->addYears(2)->toDateString(),
            'compensation_type' => 'internal',
            'hourly_rate' => 150.00,
            'fixed_salary' => 12000.00,
        ]);
    }

    /**
     * @param string $type 'internal' | 'external' | 'random'
     */
    public function instructor(string $type = 'external'): static
    {
        return $this->state(function (array $attributes) use ($type) {
            $resolved = $type === 'random'
                ? fake()->randomElement(['internal', 'external'])
                : $type;

            return [
                'role' => 'instructor',
                'expiry_date' => now()->addMonths(6)->toDateString(),
                'compensation_type' => $resolved,
                'hourly_rate' => $resolved === 'external'
                    ? fake()->randomFloat(2, 200, 500)
                    : fake()->randomFloat(2, 100, 200),
                'fixed_salary' => $resolved === 'internal'
                    ? fake()->randomFloat(2, 6000, 12000)
                    : null,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────
    // Account Status States
    // ──────────────────────────────────────────────────────────

    /**
     * Create an inactive user — useful for testing `is_active` filters.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create an expired user — useful for testing CheckAccountExpiry middleware.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => now()->subDays(fake()->numberBetween(1, 90))->toDateString(),
        ]);
    }

    /**
     * Create a user with an unverified email.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
