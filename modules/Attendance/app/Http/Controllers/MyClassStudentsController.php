<?php

namespace Modules\Attendance\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\DTOs\StudentRecord;
use Modules\Core\App\Contracts\StudentDirectory;
use Modules\Core\App\Contracts\TeacherSchedule;

/**
 * Kelas Saya › Kelas Aktif › one class: the students of a class the
 * signed-in teacher has lessons for (active students only, as the
 * directory gives them). Read-only; a class outside the
 * teacher's own timetable is not found.
 */
final class MyClassStudentsController
{
    use KnowsSignedInUser;

    public function __invoke(int $classId, TeacherSchedule $schedule, ClassDirectory $classes, StudentDirectory $students): Response
    {
        $userId = $this->signedInUserId();

        abort_if($userId === null || ! $this->teachesClass($schedule, $userId, $classId), 404);

        $class = $classes->find($classId);

        abort_if($class === null, 404);

        return Inertia::render('Attendance/MyClassStudents', [
            'class' => ['id' => $class->id, 'name' => $class->name],
            'students' => array_map(fn (StudentRecord $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
            ], $students->ofClass($classId)),
        ]);
    }

    private function teachesClass(TeacherSchedule $schedule, int $userId, int $classId): bool
    {
        foreach ($schedule->week($userId) as $day) {
            foreach ($day->lessons as $lesson) {
                if ($lesson->classId === $classId) {
                    return true;
                }
            }
        }

        return false;
    }
}
