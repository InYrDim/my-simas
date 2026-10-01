<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\StudentClassHistory;

/**
 * @extends Factory<StudentClassHistory>
 */
class StudentClassHistoryFactory extends Factory
{
    protected $model = StudentClassHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'class_id' => null,
            'class_name' => 'X 1',
            'note' => 'Kelas aktif',
        ];
    }
}
