<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;

/**
 * @extends Factory<ClassGroup>
 */
class ClassGroupFactory extends Factory
{
    protected $model = ClassGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'grade_id' => Grade::factory(),
            'name' => fake()->unique()->bothify('X ?? #'),
        ];
    }
}
