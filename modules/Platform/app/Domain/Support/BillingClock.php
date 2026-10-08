<?php

namespace Modules\Platform\App\Domain\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The provider's calendar day for billing. The application runs in UTC,
 * where a day ends at 07:00 in Jakarta, so "today" for a trial end, a
 * period end or a reminder is taken in `billing.timezone`.
 *
 * The date comes back as midnight in the application timezone, the same
 * shape as the date-cast attributes (`trial_ends_at`, `current_period_end`,
 * `due_at`), so comparing the two never shifts by the zone offset.
 *
 * A run can pin the day (`billing:daily --date=...`) to replay a missed
 * day or to look ahead; the pin must always be lifted again.
 */
final class BillingClock
{
    private static ?Carbon $pinned = null;

    public static function today(): Carbon
    {
        if (self::$pinned !== null) {
            return self::$pinned->copy();
        }

        $zone = (string) config('billing.timezone', 'Asia/Jakarta');

        return Carbon::parse(Carbon::now($zone)->toDateString());
    }

    /**
     * Treat the given date as today until it is unpinned (null).
     */
    public static function pin(?CarbonInterface $day): void
    {
        self::$pinned = $day === null ? null : Carbon::parse($day->toDateString());
    }
}
