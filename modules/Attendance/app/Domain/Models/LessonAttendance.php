<?php

namespace Modules\Attendance\App\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * One student's status in a lesson session.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $lesson_session_id
 * @property int $student_id
 * @property AttendanceStatus $status
 * @property RecordMethod $method
 * @property CarbonInterface|null $scanned_at
 */
#[Fillable(['lesson_session_id', 'student_id', 'status', 'method', 'scanned_at'])]
class LessonAttendance extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'method' => RecordMethod::class,
            'scanned_at' => 'datetime',
        ];
    }
}
