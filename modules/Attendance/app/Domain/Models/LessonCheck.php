<?php

namespace Modules\Attendance\App\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * One teacher's own "done" mark for a lesson of one day (the todo on
 * Jadwal Hari Ini), written by the teacher behind `user_id`.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $user_id
 * @property int $class_id
 * @property string $date Y-m-d
 * @property int $period_slot_id
 * @property CarbonInterface $checked_at
 */
#[Fillable(['user_id', 'class_id', 'date', 'period_slot_id', 'checked_at'])]
class LessonCheck extends Model
{
    use BelongsToTenant;

    /**
     * `Y-m-d`, whatever the database hands back (SQLite and MySQL differ).
     */
    public function getDateAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }
}
