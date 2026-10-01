<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\AcademicYear;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2090);

        return [
            'name' => $startYear.'/'.($startYear + 1),
            'curriculum' => 'Kurikulum Merdeka',
            'start_date' => $startYear.'-07-13',
            'end_date' => ($startYear + 1).'-06-26',
            'status' => AcademicYearStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => AcademicYearStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => AcademicYearStatus::Archived]);
    }
}
