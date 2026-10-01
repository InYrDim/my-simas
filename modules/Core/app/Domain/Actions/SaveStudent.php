<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\StudentClassHistory;

/**
 * Creates or updates a student and keeps the class history in step: only
 * an active student has a class, placing one writes the history row of the
 * class's academic year, and leaving (graduated, transferred, left) marks
 * the latest row.
 */
final class SaveStudent
{
    private const LEAVING_NOTES = [
        'graduated' => 'Lulus',
        'transferred' => 'Pindah sekolah',
        'left' => 'Keluar',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Student $student, array $data): Student
    {
        $student ??= new Student(['status' => 'active']);

        return DB::transaction(function () use ($student, $data): Student {
            $previousStatus = $student->exists ? $student->status : null;

            $student->fill($data);

            if (! $student->isActive()) {
                $student->class_id = null;
            }

            $student->save();

            $this->recordHistory($student, $previousStatus);

            return $student;
        });
    }

    private function recordHistory(Student $student, ?string $previousStatus): void
    {
        if ($student->class_id !== null) {
            $class = ClassGroup::query()->findOrFail($student->class_id);

            $row = StudentClassHistory::query()->firstOrNew([
                'student_id' => $student->id,
                'academic_year_id' => $class->academic_year_id,
            ]);

            $note = $row->exists && $row->class_id !== $class->id
                ? 'Pindah kelas'
                : ($row->note ?: 'Kelas aktif');

            $row->fill([
                'class_id' => $class->id,
                'class_name' => $class->name,
                'note' => $note,
            ])->save();

            return;
        }

        if ($previousStatus === 'active' && isset(self::LEAVING_NOTES[$student->status])) {
            StudentClassHistory::query()
                ->where('student_id', $student->id)
                ->orderByDesc('academic_year_id')
                ->first()
                ?->update(['note' => self::LEAVING_NOTES[$student->status]]);
        }
    }
}
