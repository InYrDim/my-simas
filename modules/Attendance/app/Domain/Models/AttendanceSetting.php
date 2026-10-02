<?php

namespace Modules\Attendance\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * A school's attendance settings: one row per tenant, created on first
 * use. `late_after` is the last time of day (`H:i:s`, on the school's
 * clock) a student still counts as on time at the gate.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $late_after
 */
#[Fillable(['late_after'])]
class AttendanceSetting extends Model
{
    use BelongsToTenant;

    public const DEFAULT_LATE_AFTER = '07:00:00';

    /**
     * The current tenant's settings, created with defaults when missing.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['late_after' => self::DEFAULT_LATE_AFTER]);
    }

    /**
     * The cut-off as `H:i`.
     */
    public function lateAfter(): string
    {
        return substr($this->late_after, 0, 5);
    }
}
