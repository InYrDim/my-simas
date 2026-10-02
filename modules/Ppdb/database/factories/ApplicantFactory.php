<?php

namespace Modules\Ppdb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ppdb\App\Domain\Enums\ApplicantSource;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * Defaults to a girl registered by the committee, waiting for
 * verification. `period_id`, `wave_id`, `path_id` and `number` must be
 * given (the tests have no use for a number the factory made up).
 *
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    protected $model = Applicant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Nadia Putri',
            'gender' => 'P',
            'birth_date' => '2012-05-04',
            'origin_school' => 'SMPN 3 Bandung',
            'guardian_name' => 'Budi Santoso',
            'guardian_phone' => '081234567890',
            'source' => ApplicantSource::Staff,
            'registered_on' => '2027-01-12',
            'status' => ApplicantStatus::Submitted,
            'decision' => Decision::Pending,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['status' => ApplicantStatus::Verified]);
    }

    public function decided(Decision $decision, ?string $score = null): static
    {
        return $this->state(fn (): array => [
            'status' => ApplicantStatus::Verified,
            'decision' => $decision,
            'score' => $score,
        ]);
    }
}
