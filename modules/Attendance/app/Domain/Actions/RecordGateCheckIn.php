<?php

namespace Modules\Attendance\App\Domain\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Exceptions\AttendanceException;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Notifications\AttendanceNotices;
use Modules\Attendance\App\Domain\Support\ScanWindow;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\StudentDirectory;

final class RecordGateCheckIn
{
    public function __construct(
        private readonly StudentDirectory $students,
        private readonly SchoolClock $clock,
        private readonly AttendanceNotices $notices,
        private readonly ScanWindow $window,
    ) {}

    /**
     * A student arriving at the gate, now, while the gate takes scans
     * (see `ScanWindow`). On time up to and including the
     * school's cut-off minute, late after it. Arriving overrules a status
     * of sick, excused or absent given earlier that day; arriving twice is
     * refused.
     *
     * @throws AttendanceException
     */
    public function handle(int $studentId, RecordMethod $method, ?int $recordedBy): DailyAttendance
    {
        $student = $this->students->find($studentId);

        if ($student === null || ! $student->active || $student->classId === null) {
            throw new AttendanceException('Siswa tidak ditemukan atau tidak aktif.');
        }

        $this->window->assertGateOpen();

        $now = $this->clock->now();
        $row = DailyAttendance::query()->where('student_id', $studentId)->where('date', $now->toDateString())->first();

        if ($row?->checked_in_at !== null) {
            throw new AttendanceException("{$student->name} sudah tercatat masuk pukul ".$this->clock->local($row->checked_in_at)->format('H.i').'.');
        }

        $status = $now->format('H:i') <= AttendanceSetting::current()->lateAfter()
            ? AttendanceStatus::Present
            : AttendanceStatus::Late;

        $row ??= new DailyAttendance(['student_id' => $studentId, 'date' => $now->toDateString()]);
        $row->fill([
            'class_id' => $student->classId,
            'status' => $status,
            'checked_in_at' => $this->clock->stored($now),
            'check_in_method' => $method,
            'recorded_by' => $recordedBy,
        ]);

        try {
            $row->save();
        } catch (UniqueConstraintViolationException) {
            // Two scanners read the same student at the same moment.
            throw new AttendanceException("{$student->name} sudah tercatat masuk.");
        }

        $this->notices->gateIn($studentId, $now, $status);

        return $row;
    }
}
