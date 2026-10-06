<?php

namespace Modules\Attendance\App\Domain\Actions;

use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Exceptions\AttendanceException;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Notifications\AttendanceNotices;
use Modules\Attendance\App\Domain\Support\ScanWindow;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\StudentDirectory;

final class RecordGateCheckOut
{
    public function __construct(
        private readonly StudentDirectory $students,
        private readonly SchoolClock $clock,
        private readonly AttendanceNotices $notices,
        private readonly ScanWindow $window,
    ) {}

    /**
     * A student leaving through the gate, now, while the gate takes scans
     * (see `ScanWindow`). Only a student who came in today can leave, and
     * only once. Leaving before the last lesson of the day is over is
     * marked `left_early`.
     *
     * @throws AttendanceException
     */
    public function handle(int $studentId, RecordMethod $method, ?int $recordedBy): DailyAttendance
    {
        $student = $this->students->find($studentId);

        if ($student === null) {
            throw new AttendanceException('Siswa tidak ditemukan.');
        }

        $this->window->assertGateOpen();

        $now = $this->clock->now();
        $row = DailyAttendance::query()->where('student_id', $studentId)->where('date', $now->toDateString())->first();

        if ($row?->checked_in_at === null) {
            throw new AttendanceException("{$student->name} belum tercatat masuk hari ini.");
        }

        if ($row->checked_out_at !== null) {
            throw new AttendanceException("{$student->name} sudah tercatat pulang pukul ".$this->clock->local($row->checked_out_at)->format('H.i').'.');
        }

        $lastLessonEnd = $this->window->lastLessonEnd();

        $row->fill([
            'left_early' => $lastLessonEnd !== null && $now->format('H:i') < $lastLessonEnd,
            'checked_out_at' => $this->clock->stored($now),
            'check_out_method' => $method,
            'recorded_by' => $recordedBy,
        ])->save();

        $this->notices->gateOut($studentId, $now);

        return $row;
    }
}
