<?php

namespace Modules\Attendance\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Notifications\AttendanceNotices;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\StudentDirectory;

final class SaveDailyAttendance
{
    public function __construct(
        private readonly ClassDirectory $classes,
        private readonly StudentDirectory $students,
        private readonly SchoolClock $clock,
        private readonly AttendanceNotices $notices,
    ) {}

    /**
     * The day's status of the students of one class. A day that has not
     * come yet cannot be recorded, and only students of that class can be
     * marked. Saving again updates the rows; the gate times of a row are
     * never touched here.
     *
     * A guardian hears about it only when the day is today and the student
     * just became sick, excused or absent — saving the same thing twice
     * tells nobody twice.
     *
     * @param  string  $date  Y-m-d
     * @param  array<int, array{status: AttendanceStatus, note: ?string}>  $marks  keyed by student id
     *
     * @throws ValidationException
     */
    public function handle(int $classId, string $date, array $marks, ?int $recordedBy): void
    {
        if ($this->classes->find($classId) === null) {
            throw ValidationException::withMessages(['class_id' => 'Kelas tidak ditemukan.']);
        }

        if ($date > $this->clock->today()) {
            throw ValidationException::withMessages(['date' => 'Tanggal tidak boleh melewati hari ini.']);
        }

        $members = array_column($this->students->ofClass($classId), 'id');

        if (array_diff(array_keys($marks), $members) !== []) {
            throw ValidationException::withMessages(['marks' => 'Ada siswa yang bukan anggota kelas ini.']);
        }

        /** @var list<array{studentId: int, status: AttendanceStatus, note: ?string}> $absences */
        $absences = DB::transaction(function () use ($classId, $date, $marks, $recordedBy): array {
            $existing = DailyAttendance::query()
                ->where('date', $date)
                ->whereIn('student_id', array_keys($marks))
                ->get()
                ->keyBy('student_id');

            $absences = [];

            foreach ($marks as $studentId => $mark) {
                /** @var DailyAttendance|null $row */
                $row = $existing->get($studentId);
                $previous = $row?->status;

                $row ??= new DailyAttendance(['student_id' => $studentId, 'date' => $date]);
                $row->fill([
                    'class_id' => $classId,
                    'status' => $mark['status'],
                    'note' => $mark['note'],
                    'recorded_by' => $recordedBy,
                ])->save();

                if (! $mark['status']->countsAsPresent() && $previous !== $mark['status']) {
                    $absences[] = ['studentId' => $studentId, 'status' => $mark['status'], 'note' => $mark['note']];
                }
            }

            return $absences;
        });

        foreach ($absences as $absence) {
            $this->notices->dailyAbsence($absence['studentId'], $date, $absence['status'], $absence['note']);
        }
    }
}
