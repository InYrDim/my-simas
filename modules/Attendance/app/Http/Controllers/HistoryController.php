<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Actions\SaveOwnLessonAttendance;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\LessonAttendance;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Attendance\App\Domain\Queries\LessonRoll;
use Modules\Attendance\App\Domain\Queries\TeacherLessons;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Attendance\App\Http\Concerns\ReadsAttendanceFilters;
use Modules\Attendance\App\Http\Requests\OwnLessonAttendanceRequest;

/**
 * Kelas Saya › Riwayat Absensi: the signed-in teacher's scheduled lessons
 * of one day in a table, each with its saved recap, and the roll of the
 * chosen one open for correction — here a record is edited at any time,
 * not only while the lesson runs.
 */
final class HistoryController
{
    use KnowsSignedInUser, ReadsAttendanceFilters;

    public function index(Request $request, TeacherLessons $lessons, LessonRoll $roll, SchoolClock $clock): Response
    {
        $userId = $this->signedInUserId();
        $date = $this->requestedDate($request, $clock);
        $list = $userId === null ? [] : $lessons->on($userId, $date);
        $summaries = $this->summaries($list, $date);

        foreach ($list as $index => $lesson) {
            $list[$index] = [...$lesson, 'summary' => $summaries["{$lesson['classId']}:{$lesson['slotId']}"] ?? null];
        }

        $selected = $this->pick($list, $request->query('jam'));
        $session = $selected === null ? null : LessonSession::query()
            ->where('class_id', $selected['classId'])
            ->where('date', $date)
            ->where('period_slot_id', $selected['slotId'])
            ->first();

        return Inertia::render('Attendance/History', [
            'date' => $this->dateProp($date, $clock),
            'today' => $clock->today(),
            'lessons' => $list,
            'slotId' => $selected === null ? '' : (string) $selected['slotId'],
            'selected' => $selected,
            'recorded' => $session !== null,
            'students' => $selected === null ? [] : $roll->forClass($selected['classId'], $date, $session),
        ]);
    }

    public function update(OwnLessonAttendanceRequest $request, SaveOwnLessonAttendance $save): RedirectResponse
    {
        $userId = $this->signedInUserId();

        if ($userId !== null) {
            $save->handle($userId, $request->day(), $request->slotId(), $request->marks(), whileRunning: false);
        }

        return back()->with('status', 'Riwayat absensi diperbarui.');
    }

    /**
     * The saved recap of each of the day's lessons, keyed by class and
     * slot: how many students are present, sick, excused or absent.
     *
     * @param  list<array{slotId: int, classId: int}>  $lessons
     * @return array<string, array<string, int>>
     */
    private function summaries(array $lessons, string $date): array
    {
        $classIds = array_values(array_unique(array_column($lessons, 'classId')));

        if ($classIds === []) {
            return [];
        }

        $sessions = LessonSession::query()
            ->where('date', $date)
            ->whereIn('class_id', $classIds)
            ->get();

        $counts = LessonAttendance::query()
            ->whereIn('lesson_session_id', $sessions->pluck('id'))
            ->get()
            ->groupBy('lesson_session_id');

        /** @var array<string, array<string, int>> $summaries */
        $summaries = [];

        foreach ($sessions as $session) {
            $byStatus = $counts->get($session->id, collect())
                ->countBy(fn (LessonAttendance $row): string => $row->status->value);

            $summaries["{$session->class_id}:{$session->period_slot_id}"] = [
                AttendanceStatus::Present->value => (int) $byStatus->get(AttendanceStatus::Present->value, 0),
                AttendanceStatus::Sick->value => (int) $byStatus->get(AttendanceStatus::Sick->value, 0),
                AttendanceStatus::Permit->value => (int) $byStatus->get(AttendanceStatus::Permit->value, 0),
                AttendanceStatus::Absent->value => (int) $byStatus->get(AttendanceStatus::Absent->value, 0),
            ];
        }

        return $summaries;
    }

    /**
     * The lesson whose roll is open, when its slot is one of the day's.
     *
     * @param  list<array{slotId: int, order: int, startsAt: string, endsAt: string, classId: int, className: string, subjectId: int, subjectName: string, state: string, recorded: bool, checked: bool, summary: ?array<string, int>}>  $lessons
     * @return array<string, mixed>|null
     */
    private function pick(array $lessons, mixed $requested): ?array
    {
        if (! is_string($requested)) {
            return null;
        }

        foreach ($lessons as $lesson) {
            if ($lesson['slotId'] === (int) $requested) {
                return $lesson;
            }
        }

        return null;
    }
}
