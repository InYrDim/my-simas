<?php

namespace Modules\Attendance\App\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\Database\Factories\DailyAttendanceFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * One student's attendance on one day: the day's status and, when the
 * student passed the gate, the times in and out. `date` is the day on the
 * school's clock and stays a plain `Y-m-d` string, so it compares the
 * same on every database.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $student_id
 * @property int $class_id the class at the time of the record
 * @property string $date Y-m-d
 * @property AttendanceStatus $status
 * @property CarbonInterface|null $checked_in_at
 * @property CarbonInterface|null $checked_out_at
 * @property RecordMethod|null $check_in_method
 * @property RecordMethod|null $check_out_method
 * @property bool $left_early went home before the last lesson of the day was over
 * @property string|null $note
 * @property int|null $recorded_by account id of whoever wrote the record last
 */
#[UseFactory(DailyAttendanceFactory::class)]
#[Fillable(['student_id', 'class_id', 'date', 'status', 'checked_in_at', 'checked_out_at', 'check_in_method', 'check_out_method', 'left_early', 'note', 'recorded_by'])]
class DailyAttendance extends Model
{
    /** @use HasFactory<DailyAttendanceFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'check_in_method' => RecordMethod::class,
            'check_out_method' => RecordMethod::class,
            'left_early' => 'boolean',
        ];
    }

    /**
     * `Y-m-d`, whatever the database hands back (SQLite and MySQL differ).
     */
    public function getDateAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }
}
