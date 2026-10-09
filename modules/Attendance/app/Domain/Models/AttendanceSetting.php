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
 * on or off; both are on by default. `lesson_scan_early_minutes` is how
 * long before a lesson starts a student may already be scanned into it.
 * `lesson_copy_previous_enabled` lets a teacher copy the roll of the
 * class's previous lesson of the day into the one being filled.
 * `static_qr_enabled` lets the school print and accept every student's
 * static QR; it is off by default.
 * `gate_opens_at` and `gate_closes_at` (`H:i:s`, on the school's clock) are
 * the hours the gate takes scans.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $late_after
 * @property bool $gate_enabled
 * @property bool $lesson_enabled
 * @property int $lesson_scan_early_minutes
 * @property bool $lesson_copy_previous_enabled
 * @property bool $static_qr_enabled
 * @property string $gate_opens_at
 * @property string $gate_closes_at
 */
#[Fillable(['late_after', 'gate_enabled', 'lesson_enabled', 'lesson_scan_early_minutes', 'lesson_copy_previous_enabled', 'static_qr_enabled', 'gate_opens_at', 'gate_closes_at'])]
class AttendanceSetting extends Model
{
    use BelongsToTenant;

    public const DEFAULT_LATE_AFTER = '07:00:00';

    public const DEFAULT_LESSON_SCAN_EARLY_MINUTES = 5;

    public const DEFAULT_GATE_OPENS_AT = '05:00:00';

    public const DEFAULT_GATE_CLOSES_AT = '18:00:00';

    /**
     * The current tenant's settings, created with defaults when missing.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'late_after' => self::DEFAULT_LATE_AFTER,
            'gate_enabled' => true,
            'lesson_enabled' => true,
            'lesson_scan_early_minutes' => self::DEFAULT_LESSON_SCAN_EARLY_MINUTES,
            'lesson_copy_previous_enabled' => true,
            'static_qr_enabled' => false,
            'gate_opens_at' => self::DEFAULT_GATE_OPENS_AT,
            'gate_closes_at' => self::DEFAULT_GATE_CLOSES_AT,
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
     * Whether a teacher may copy the previous lesson's roll. Reads without creating the row.
     */
    public static function copyPreviousEnabled(): bool
    {
        return self::query()->value('lesson_copy_previous_enabled') ?? true;
    }

    /**
     * Whether the static QR is on. Reads without creating the row.
     */
    public static function staticQrEnabled(): bool
    {
        return self::query()->value('static_qr_enabled') ?? false;
    }

    /**
     * The cut-off as `H:i`.
     */
    public function lateAfter(): string
    {
        return substr($this->late_after, 0, 5);
    }

    /**
     * The first minute the gate takes scans, as `H:i`.
     */
    public function gateOpensAt(): string
    {
        return substr($this->gate_opens_at, 0, 5);
    }

    /**
     * The last minute the gate takes scans, as `H:i`.
     */
    public function gateClosesAt(): string
    {
        return substr($this->gate_closes_at, 0, 5);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['gate_enabled' => 'boolean', 'lesson_enabled' => 'boolean', 'lesson_scan_early_minutes' => 'integer', 'lesson_copy_previous_enabled' => 'boolean', 'static_qr_enabled' => 'boolean'];
    }
}
