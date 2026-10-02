<?php

namespace Modules\Attendance\App\Domain\Actions;

use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Exceptions\AttendanceException;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Notifications\AttendanceNotices;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\StudentDirectory;

final class RecordGateCheckOut
{
    public function __construct(
        private readonly StudentDirectory $students,
        private readonly SchoolClock $clock,
        private readonly AttendanceNotices $notices,
    ) {}

    /**
     * A student leaving through the gate, now. Only a student who came in
     * today can leave, and only once.
     *
     * @throws AttendanceException
     */
    public function handle(int $studentId, RecordMethod $method, ?int $recordedBy): DailyAttendance
    {
        $student = $this->students->find($studentId);

        if ($student === null) {
            throw new AttendanceException('Siswa tidak ditemukan.');
        }

        $now = $this->clock->now();
        $row = DailyAttendance::query()->where('student_id', $studentId)->where('date', $now->toDateString())->first();

        if ($row?->checked_in_at === null) {
            throw new AttendanceException("{$student->name} belum tercatat masuk hari ini.");
        }

        if ($row->checked_out_at !== null) {
            throw new AttendanceException("{$student->name} sudah tercatat pulang pukul ".$this->clock->local($row->checked_out_at)->format('H.i').'.');
        }

        $row->fill([
            'checked_out_at' => $this->clock->stored($now),
            'check_out_method' => $method,
            'recorded_by' => $recordedBy,
        ])->save();

        $this->notices->gateOut($studentId, $now);

        return $row;
    }
}
