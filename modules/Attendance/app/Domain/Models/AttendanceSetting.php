<?php

namespace Modules\Attendance\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * A school's attendance settings: one row per tenant, created on first
 * use. `late_after` is the last time of day (`H:i:s`, on the school's
 * clock) a student still counts as on time at the gate. `gate_enabled` and
 * `lesson_enabled` switch the gate (in and out) and the lesson attendance
 * on or off; both are on by default.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $late_after
 * @property bool $gate_enabled
 * @property bool $lesson_enabled
 */
#[Fillable(['late_after', 'gate_enabled', 'lesson_enabled'])]
class AttendanceSetting extends Model
{
    use BelongsToTenant;

    public const DEFAULT_LATE_AFTER = '07:00:00';

    /**
     * The current tenant's settings, created with defaults when missing.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'late_after' => self::DEFAULT_LATE_AFTER,
            'gate_enabled' => true,
            'lesson_enabled' => true,
        ]);
    }

    /**
     * Whether the school uses the gate. Reads without creating the row.
     */
    public static function gateEnabled(): bool
    {
        return self::query()->value('gate_enabled') ?? true;
    }

    /**
     * Whether the school uses lesson attendance. Reads without creating the row.
     */
    public static function lessonEnabled(): bool
    {
        return self::query()->value('lesson_enabled') ?? true;
    }

    /**
     * The cut-off as `H:i`.
     */
    public function lateAfter(): string
    {
        return substr($this->late_after, 0, 5);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['gate_enabled' => 'boolean', 'lesson_enabled' => 'boolean'];
    }
}
