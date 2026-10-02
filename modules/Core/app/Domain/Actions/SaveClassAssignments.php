<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\TeachingAssignment;

/**
 * Replaces the teaching assignments of one class with the given rows: a
 * subject missing from the rows loses its teacher.
 */
final class SaveClassAssignments
{
    /**
     * @param  list<array{subject_id: int, teacher_id: int, hours: int}>  $rows
     *
     * @throws ValidationException when the class is not in the active academic year
     */
    public function handle(ClassGroup $class, array $rows): void
    {
        $inActiveYear = AcademicYear::query()
            ->whereKey($class->academic_year_id)
            ->where('status', AcademicYearStatus::Active->value)
            ->exists();

        if (! $inActiveYear) {
            throw ValidationException::withMessages([
                'assignments' => 'Hanya kelas pada tahun ajaran aktif yang bisa diatur pengampunya.',
            ]);
        }

        DB::transaction(function () use ($class, $rows): void {
            TeachingAssignment::query()
                ->where('class_id', $class->id)
                ->whereNotIn('subject_id', array_column($rows, 'subject_id'))
                ->delete();

            foreach ($rows as $row) {
                TeachingAssignment::query()->updateOrCreate(
                    ['class_id' => $class->id, 'subject_id' => $row['subject_id']],
                    ['teacher_id' => $row['teacher_id'], 'hours_per_week' => $row['hours']],
                );
            }
        });
    }
}
