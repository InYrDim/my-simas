<?php

namespace Modules\Ppdb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;

/**
 * Defaults to a verified account that has not joined a school.
 *
 * @extends Factory<PpdbAccount>
 */
class PpdbAccountFactory extends Factory
{
    protected $model = PpdbAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Siti Aminah',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password', // bcrypt rounds set in phpunit.xml
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An account whose owner has not opened the verification link yet.
     */
    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    /**
     * An account that has joined the school.
     */
    public function joined(string $tenantId): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenantId]);
    }
}
