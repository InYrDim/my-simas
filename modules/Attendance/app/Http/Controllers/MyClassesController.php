<?php

namespace Modules\Attendance\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Core\App\Contracts\DTOs\ScheduleDay;
use Modules\Core\App\Contracts\TeacherSchedule;

/**
 * Kelas Saya › Kelas Aktif: the classes the signed-in teacher really
 * teaches, taken from their lesson timetable and the bell slots — a
 * class only shows while it actually has lessons on the schedule.
 */
final class MyClassesController
{
    use KnowsSignedInUser;

    public function __invoke(TeacherSchedule $schedule): Response
    {
        $userId = $this->signedInUserId();

        return Inertia::render('Attendance/MyClasses', [
            'classes' => $this->classes($userId === null ? [] : $schedule->week($userId)),
        ]);
    }

    /**
     * @param  list<ScheduleDay>  $week
     * @return list<array{id: int, name: string, subjects: list<string>, days: list<string>, lessons: int}>
     */
    private function classes(array $week): array
    {
        /** @var array<int, array{id: int, name: string, subjects: list<string>, days: list<string>, lessons: int}> $classes */
        $classes = [];

        foreach ($week as $day) {
            foreach ($day->lessons as $lesson) {
                $classes[$lesson->classId] ??= [
                    'id' => $lesson->classId,
                    'name' => $lesson->className,
                    'subjects' => [],
                    'days' => [],
                    'lessons' => 0,
                ];

                if (! in_array($lesson->subjectName, $classes[$lesson->classId]['subjects'], true)) {
                    $classes[$lesson->classId]['subjects'][] = $lesson->subjectName;
                }

                if (! in_array($day->dayName, $classes[$lesson->classId]['days'], true)) {
                    $classes[$lesson->classId]['days'][] = $day->dayName;
                }

                $classes[$lesson->classId]['lessons']++;
            }
        }

        $list = array_values($classes);
        usort($list, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $list;
    }
}
