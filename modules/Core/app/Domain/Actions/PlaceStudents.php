<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Enums\PlacementAction;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;

/**
 * Promotes, moves or graduates a set of students of one class in a single
 * transaction. Everything is checked before the first student changes, and
 * each change goes through SaveStudent so the class history stays right.
 */
final class PlaceStudents
{
    public function __construct(private readonly SaveStudent $saveStudent) {}

    /**
     * @param  list<int>  $studentIds
     * @return int the number of students placed
     *
     * @throws ValidationException
     */
    public function handle(PlacementAction $action, ClassGroup $source, ?ClassGroup $target, array $studentIds): int
    {
        $students = $this->studentsOf($source, $studentIds);

        if ($action !== PlacementAction::Graduate) {
            $this->assertTargetFits($action, $source, $target);
        }

        DB::transaction(function () use ($action, $students, $target): void {
            foreach ($students as $student) {
                $this->saveStudent->handle($student, $action === PlacementAction::Graduate
                    ? ['status' => 'graduated']
                    : ['class_id' => $target?->id]);
            }
        });

        return $students->count();
    }

    /**
     * @param  list<int>  $studentIds
     * @return Collection<int, Student>
     */
    private function studentsOf(ClassGroup $source, array $studentIds): Collection
    {
        $students = Student::query()->whereIn('id', $studentIds)->get();

        if ($students->count() !== count(array_unique($studentIds))) {
            throw ValidationException::withMessages(['student_ids' => 'Ada siswa yang tidak ditemukan.']);
        }

        foreach ($students as $student) {
            if (! $student->isActive() || $student->class_id !== $source->id) {
                throw ValidationException::withMessages([
                    'student_ids' => "{$student->name} bukan siswa aktif di rombel {$source->name}.",
                ]);
            }
        }

        return $students;
    }

    private function assertTargetFits(PlacementAction $action, ClassGroup $source, ?ClassGroup $target): void
    {
        if ($target === null) {
            throw ValidationException::withMessages(['target_class_id' => 'Rombel tujuan wajib dipilih.']);
        }

        if ($action === PlacementAction::Move) {
            if ($target->academic_year_id !== $source->academic_year_id || $target->id === $source->id) {
                throw ValidationException::withMessages([
                    'target_class_id' => 'Pindah rombel hanya ke rombel lain pada tahun ajaran yang sama.',
                ]);
            }

            return;
        }

        $sourceYear = AcademicYear::query()->findOrFail($source->academic_year_id);
        $targetYear = AcademicYear::query()->findOrFail($target->academic_year_id);

        if ($targetYear->start_date->lte($sourceYear->start_date)) {
            throw ValidationException::withMessages([
                'target_class_id' => 'Naik kelas harus ke rombel pada tahun ajaran yang lebih baru.',
            ]);
        }
    }
}
