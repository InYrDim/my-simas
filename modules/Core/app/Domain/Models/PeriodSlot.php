<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\PeriodSlotFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * One slot of the school's bell schedule on a weekday. Times are stored
 * as `H:i:s`.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $day 1 (Senin) to 6 (Sabtu)
 * @property string $start_time
 * @property string $end_time
 * @property string $type one of self::TYPES
 */
#[UseFactory(PeriodSlotFactory::class)]
#[Fillable(['day', 'start_time', 'end_time', 'type'])]
class PeriodSlot extends Model
{
    /** @use HasFactory<PeriodSlotFactory> */
    use BelongsToTenant, HasFactory;

    public const LESSON = 'Pelajaran';

    public const TYPES = ['Pelajaran', 'Istirahat', 'Pembiasaan', 'Upacara', 'Lainnya'];

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
}
