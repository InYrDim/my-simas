<?php

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\App\Domain\Models\User;

/**
 * @extends Factory<User>
 */
#[UseModel(User::class)]
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Pin the user to a specific tenant. Without it, BelongsToTenant's
     * creating hook fills tenant_id from the ambient context — and
     * throws when there is none (fail closed).
     */
    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Deactivated account: audit stamp set, login refused, live
     * sessions ended by middleware. Roles are kept.
     */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Account that signs in by username and has no email (a student's
     * NIS, a teacher's NIP).
     */
    public function withUsername(string $username): static
    {
        return $this->state(fn (array $attributes): array => [
            'username' => $username,
            'email' => null,
            'email_verified_at' => null,
        ]);
    }

    /**
     * Password set by someone else: it has to be changed at first login.
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes): array => [
            'must_change_password' => true,
        ]);
    }

    /**
     * Invited user: no password yet — they activate via a set-password
     * link (Fase 2 token machinery).
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes): array => [
            'password' => null,
            'email_verified_at' => null,
        ]);
    }
}
