<?php

namespace Modules\Attendance\App\Domain\Actions;

use Modules\Attendance\App\Domain\Exceptions\AttendanceException;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\StudentDirectory;

final class CancelGateCheckOut
{
    public function __construct(
        private readonly StudentDirectory $students,
        private readonly SchoolClock $clock,
    ) {}

    /**
     * Takes back the going-home record of a student, today only: the time,
     * the method and the "pulang awal" mark go; the arrival and the day's
     * status stay. Nobody is notified.
     *
     * @throws AttendanceException
     */
    public function handle(int $studentId, ?int $recordedBy): DailyAttendance
    {
        $student = $this->students->find($studentId);

        if ($student === null) {
            throw new AttendanceException('Siswa tidak ditemukan.');
        }

        $row = DailyAttendance::query()->where('student_id', $studentId)->where('date', $this->clock->today())->first();

        if ($row?->checked_out_at === null) {
            throw new AttendanceException("{$student->name} belum tercatat pulang hari ini.");
        }

        $row->fill([
            'checked_out_at' => null,
            'check_out_method' => null,
            'left_early' => false,
            'recorded_by' => $recordedBy,
        ])->save();

        return $row;
    }
}
