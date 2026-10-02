<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\CalendarEventFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * An event on the academic calendar. Stored in `calendar_events`.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $title
 * @property string $category one of self::CATEGORIES
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 */
#[UseFactory(CalendarEventFactory::class)]
#[Fillable(['title', 'category', 'start_date', 'end_date'])]
class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use BelongsToTenant, HasFactory;

    public const CATEGORIES = ['holiday', 'exam', 'activity'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];
    }
}
