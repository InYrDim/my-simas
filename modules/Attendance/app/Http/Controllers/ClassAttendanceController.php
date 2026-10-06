<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Actions\SaveOwnLessonAttendance;
use Modules\Attendance\App\Domain\Enums\LessonState;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Attendance\App\Domain\Queries\LessonRoll;
use Modules\Attendance\App\Domain\Queries\TeacherLessons;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Attendance\App\Http\Concerns\ReadsAttendanceFilters;
use Modules\Attendance\App\Http\Requests\OwnLessonAttendanceRequest;

/**
 * Kelas Saya › Absensi Kelas: today's lessons of the signed-in teacher,
 * with the lesson in its own hour open for filling and the rest shown
 * read-only — a finished record is changed from Riwayat Absensi.
 */
final class ClassAttendanceController
{
    use KnowsSignedInUser, ReadsAttendanceFilters;

    public function index(Request $request, TeacherLessons $lessons, LessonRoll $roll, SchoolClock $clock): Response
    {
        $userId = $this->signedInUserId();
        $today = $clock->today();
        $list = $userId === null ? [] : $lessons->on($userId, $today);
        $selected = $this->pick($list, $request->query('jam'));

        $session = $selected === null ? null : LessonSession::query()
            ->where('class_id', $selected['classId'])
            ->where('date', $today)
            ->where('period_slot_id', $selected['slotId'])
            ->first();

        return Inertia::render('Attendance/ClassRoll', [
            'date' => $this->dateProp($today, $clock),
            'lessons' => $list,
            'slotId' => $selected === null ? '' : (string) $selected['slotId'],
            'selected' => $selected,
            'editable' => ($selected['state'] ?? null) === LessonState::Running->value,
            'recorded' => $session !== null,
            'previous' => $selected === null || ! AttendanceSetting::copyPreviousEnabled() ? null : $this->previousRoll($selected, $today),
            'students' => $selected === null ? [] : $roll->forClass($selected['classId'], $today, $session),
        ]);
    }

    public function update(OwnLessonAttendanceRequest $request, SaveOwnLessonAttendance $save): RedirectResponse
    {
        $userId = $this->signedInUserId();

        if ($userId !== null) {
            $save->handle($userId, $request->day(), $request->slotId(), $request->marks(), whileRunning: true);
        }

        return back()->with('status', 'Absensi kelas disimpan.');
    }

    /**
     * The roll of the class's closest earlier lesson of the day, for the
     * teacher to copy; null when the class has none or it has no marks.
     *
     * @param  array{slotId: int, classId: int, startsAt: string}  $selected
     * @return array{label: string, marks: array<int, string>}|null
     */
    private function previousRoll(array $selected, string $today): ?array
    {
        $session = LessonSession::query()
            ->where('class_id', $selected['classId'])
            ->where('date', $today)
            ->where('start_time', '<', "{$selected['startsAt']}:00")
            ->orderByDesc('start_time')
            ->first();

        if ($session === null) {
            return null;
        }

        $marks = $session->attendances()->get()
            ->mapWithKeys(fn ($mark): array => [$mark->student_id => $mark->status->value])
            ->all();

        if ($marks === []) {
            return null;
        }

        return [
            'label' => substr($session->start_time, 0, 5).'–'.substr($session->end_time, 0, 5),
            'marks' => $marks,
        ];
    }

    /**
     * The lesson to open: the one asked for when it is the teacher's,
     * else the lesson running now, else the next still to come, else the
     * day's first — an empty list stays empty.
     *
     * @param  list<array{slotId: int, order: int, startsAt: string, endsAt: string, classId: int, className: string, subjectId: int, subjectName: string, state: string, recorded: bool, checked: bool}>  $lessons
     * @return array{slotId: int, order: int, startsAt: string, endsAt: string, classId: int, className: string, subjectId: int, subjectName: string, state: string, recorded: bool, checked: bool}|null
     */
    private function pick(array $lessons, mixed $requested): ?array
    {
        if (is_string($requested)) {
            foreach ($lessons as $lesson) {
                if ($lesson['slotId'] === (int) $requested) {
                    return $lesson;
                }
            }
        }

        foreach ($lessons as $lesson) {
            if ($lesson['state'] === LessonState::Running->value) {
                return $lesson;
            }
        }

        foreach ($lessons as $lesson) {
            if ($lesson['state'] === LessonState::Upcoming->value) {
                return $lesson;
            }
        }

        return $lessons[0] ?? null;
    }
}
