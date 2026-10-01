<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Domain\Models\TenantApplicationStatus;

/**
 * @extends Factory<TenantApplication>
 */
class TenantApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TenantApplication::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_name' => 'SMA '.fake()->company(),
            'desired_slug' => str(fake()->unique()->slug(2))->replace('_', '-')->toString(),
            'timezone' => 'Asia/Jakarta',
            'applicant_name' => fake()->name(),
            'applicant_email' => fake()->unique()->safeEmail(),
            'applicant_message' => fake()->optional()->sentence(),
            'status' => TenantApplicationStatus::Pending,
        ];
    }

    /**
     * Explicit pending state (default, kept for readability).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantApplicationStatus::Pending,
        ]);
    }

    /**
     * An application submitted from an applicant account: bound by id,
     * with the account's name and email as the snapshot.
     */
    public function forApplicant(Applicant $applicant): static
    {
        return $this->state(fn (array $attributes): array => [
            'applicant_id' => $applicant->id,
            'applicant_name' => $applicant->name,
            'applicant_email' => $applicant->email,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Rejected by the provider with a note.
     */
    public function rejected(string $note = 'Data belum lengkap.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantApplicationStatus::Rejected,
            'admin_note' => $note,
            'decided_at' => now(),
        ]);
    }
}
