<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\ClassGroup;

/**
 * Sets the homeroom teacher of the active academic year's classes in one
 * sweep. A teacher holding several classes is allowed (the page flags it).
 */
final class AssignHomerooms
{
    /**
     * @param  array<int|string, int|null>  $homerooms  class id => teacher id (null clears it)
     *
     * @throws ValidationException when a class does not belong to the active academic year
     */
    public function handle(array $homerooms): void
    {
        $classes = ClassGroup::query()
            ->whereHas('academicYear', fn ($query) => $query->where('status', AcademicYearStatus::Active->value))
            ->whereIn('id', array_keys($homerooms))
            ->get()
            ->keyBy('id');

        foreach (array_keys($homerooms) as $classId) {
            if (! $classes->has((int) $classId)) {
                throw ValidationException::withMessages([
                    'homerooms' => 'Hanya kelas pada tahun ajaran aktif yang bisa diatur.',
                ]);
            }
        }

        DB::transaction(function () use ($classes, $homerooms): void {
            foreach ($classes as $class) {
                $class->forceFill(['homeroom_teacher_id' => $homerooms[$class->id]])->save();
            }
        });
    }
}
