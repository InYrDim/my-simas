<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Models\DailyAttendance;

/**
 * Defaults to a student marked present by hand. `student_id` and
 * `class_id` are Core's ids and must be given.
 *
 * @extends Factory<DailyAttendance>
 */
class DailyAttendanceFactory extends Factory
{
    protected $model = DailyAttendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
        ];
    }

    public function status(AttendanceStatus $status, ?string $note = null): static
    {
        return $this->state(fn (): array => ['status' => $status, 'note' => $note]);
    }

    /**
     * Scanned in at the gate at the given moment (UTC).
     */
    public function checkedIn(string $at, AttendanceStatus $status = AttendanceStatus::Present): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'checked_in_at' => $at,
            'check_in_method' => RecordMethod::Qr,
        ]);
    }
}
