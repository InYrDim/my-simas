<?php

namespace Modules\Attendance\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\ClassTimetable;
use Modules\Core\App\Contracts\DTOs\ClassRecord;
use Modules\Core\App\Contracts\DTOs\ClassSubject;
use Modules\Core\App\Contracts\DTOs\ScheduleDay;
use Modules\Core\App\Contracts\DTOs\ScheduleLesson;
use Modules\Core\App\Contracts\DTOs\StudentRecord;
use Modules\Core\App\Contracts\StudentDirectory;

/**
 * Kelas Saya (student): the class the signed-in student sits in — info
 * and classmates, the weekly timetable, the subjects with their
 * teachers. Nothing here takes an id: the class is the one of the
 * student behind the account. A student with no class gets empty pages.
 */
final class MyClassController
{
    use KnowsSignedInUser;

    public function __construct(
        private readonly StudentDirectory $students,
        private readonly ClassDirectory $classes,
    ) {}

    public function info(): Response
    {
        $class = $this->class();

        return Inertia::render('Attendance/MyClassInfo', [
            'class' => $class === null ? null : [
                'name' => $class->name,
                'homeroom' => $class->homeroomName,
                'studentCount' => $class->studentCount,
            ],
            'classmates' => $class === null ? [] : array_map(
                fn (StudentRecord $student): string => $student->name,
                $this->students->ofClass($class->id),
            ),
        ]);
    }

    public function timetable(ClassTimetable $timetable): Response
    {
        $class = $this->class();

        return Inertia::render('Attendance/MyClassTimetable', [
            'className' => $class?->name,
            'days' => $class === null ? [] : array_map(fn (ScheduleDay $day): array => [
                'day' => $day->dayName,
                'dayNumber' => $day->day,
                'lessons' => array_map(fn (ScheduleLesson $lesson): array => [
                    'order' => $lesson->order,
                    'start' => $lesson->startsAt,
                    'end' => $lesson->endsAt,
                    'subject' => $lesson->subjectName,
                    'teacher' => $lesson->teacherName,
                ], $day->lessons),
            ], $timetable->week($class->id)),
        ]);
    }

    public function subjects(): Response
    {
        $class = $this->class();

        return Inertia::render('Attendance/MyClassSubjects', [
            'className' => $class?->name,
            'subjects' => $class === null ? [] : array_map(fn (ClassSubject $subject): array => [
                'subject' => $subject->subjectName,
                'teacher' => $subject->teacherName,
            ], $this->classes->subjectsOf($class->id)),
        ]);
    }

    /**
     * The class of the signed-in student; 403 when the account is no
     * active student's, null while the student has no class.
     */
    private function class(): ?ClassRecord
    {
        $userId = $this->signedInUserId();
        $student = $userId === null ? null : $this->students->findByUserId($userId);

        abort_if($student === null || ! $student->active, 403);

        return $student->classId === null ? null : $this->classes->find($student->classId);
    }
}
