<?php

namespace Modules\Platform\App\Domain\Support;

use Illuminate\Support\Carbon;

/**
 * The provider's calendar day for billing. The application runs in UTC,
 * where a day ends at 07:00 in Jakarta, so "today" for a trial end, a
 * period end or a reminder is taken in `billing.timezone`.
 *
 * The date comes back as midnight in the application timezone, the same
 * shape as the date-cast attributes (`trial_ends_at`, `current_period_end`,
 * `due_at`), so comparing the two never shifts by the zone offset.
 */
final class BillingClock
{
    public static function today(): Carbon
    {
        $zone = (string) config('billing.timezone', 'Asia/Jakarta');

        return Carbon::parse(Carbon::now($zone)->toDateString());
    }
}
