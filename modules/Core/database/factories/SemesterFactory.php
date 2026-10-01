<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\Semester;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Ganjil',
            'start_date' => '2025-07-14',
            'end_date' => '2025-12-20',
        ];
    }
}
