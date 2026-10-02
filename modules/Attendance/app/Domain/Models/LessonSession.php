<?php

namespace Modules\Attendance\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Attendance\Database\Factories\LessonSessionFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * Attendance of one class in one lesson slot of one day. The slot's times
 * are copied in; the subject and its teacher are optional.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $class_id
 * @property string $date Y-m-d
 * @property int $period_slot_id
 * @property string $start_time H:i:s
 * @property string $end_time H:i:s
 * @property int|null $subject_id
 * @property int|null $teacher_id
 * @property int|null $recorded_by
 */
#[UseFactory(LessonSessionFactory::class)]
#[Fillable(['class_id', 'date', 'period_slot_id', 'start_time', 'end_time', 'subject_id', 'teacher_id', 'recorded_by'])]
class LessonSession extends Model
{
    /** @use HasFactory<LessonSessionFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return HasMany<LessonAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class);
    }

    /**
     * `Y-m-d`, whatever the database hands back (SQLite and MySQL differ).
     */
    public function getDateAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }
}
