<?php

namespace Modules\Core\App\Domain\Queries;

use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;

/**
 * The classes of the active academic year that the teacher behind a
 * signed-in account looks after: the ones they are homeroom teacher of
 * and the ones they teach a subject in.
 */
final class TeacherClasses
{
    /**
     * @return list<array{id: int, name: string, homeroom: bool, subjects: list<string>}>
     */
    public function forUser(?int $userId): array
    {
        $teacher = $userId === null ? null : Teacher::query()->where('user_id', $userId)->first();

        if ($teacher === null) {
            return [];
        }

        $inActiveYear = fn ($query) => $query->where('status', AcademicYearStatus::Active->value);

        $classes = [];

        $homerooms = ClassGroup::query()
            ->where('homeroom_teacher_id', $teacher->id)
            ->whereHas('academicYear', $inActiveYear)
            ->get();

        foreach ($homerooms as $class) {
            $classes[$class->id] = ['id' => $class->id, 'name' => $class->name, 'homeroom' => true, 'subjects' => []];
        }

        $assignments = TeachingAssignment::query()
            ->with(['classGroup', 'subject'])
            ->where('teacher_id', $teacher->id)
            ->whereHas('classGroup.academicYear', $inActiveYear)
            ->get();

        foreach ($assignments as $assignment) {
            $class = $assignment->classGroup;

            $classes[$class->id] ??= ['id' => $class->id, 'name' => $class->name, 'homeroom' => false, 'subjects' => []];
            $classes[$class->id]['subjects'][] = $assignment->subject->name;
        }

        $list = array_values($classes);
        usort($list, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $list;
    }
}
