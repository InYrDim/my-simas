<?php

namespace Modules\Platform\App\Infrastructure\Billing\Daily;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Modules\Platform\App\Domain\Models\ProviderSetting;
use Modules\Platform\App\Domain\Support\BillingClock;
use Throwable;

/**
 * One day's billing work, in the order that matters: issue the renewal
 * invoices, remind, close the schools whose access ran out, then expire
 * gateway payments nobody finished. Every step runs on its own: one that
 * throws is reported and the next still runs.
 *
 * Safe to run twice on the same day and late: the steps look at dates and
 * at what was already sent or done, never at "how long ago".
 */
final class BillingDaily
{
    public const STEPS = ['renewals', 'reminders', 'suspensions', 'payments'];

    public const LAST_RUN_SETTING = 'billing.last_run_at';

    public function __construct(
        private readonly RenewalStep $renewals,
        private readonly ReminderStep $reminders,
        private readonly SuspensionStep $suspensions,
        private readonly PaymentExpiryStep $payments,
    ) {}

    /**
     * @param  list<string>  $only  step names; empty = all steps
     * @param  CarbonInterface|null  $date  run as if it were this day
     */
    public function run(?CarbonInterface $date = null, array $only = [], bool $dryRun = false, bool $force = false): BillingDailyRun
    {
        $steps = $only === [] ? self::STEPS : array_values(array_intersect(self::STEPS, $only));
        $results = [];

        BillingClock::pin($date);

        try {
            $today = BillingClock::today();

            foreach ($steps as $step) {
                try {
                    $results[] = match ($step) {
                        'renewals' => $this->renewals->run($today, $dryRun),
                        'reminders' => $this->reminders->run($today, $dryRun),
                        'suspensions' => $this->suspensions->run($today, $dryRun, $force),
                        'payments' => $this->payments->run($dryRun),
                    };
                } catch (Throwable $exception) {
                    report($exception);
                    $results[] = new DailyStepResult($step, failures: [$exception->getMessage()]);
                }
            }
        } finally {
            BillingClock::pin(null);
        }

        $run = new BillingDailyRun($today, $results, $dryRun);

        $this->record($run, $only === [] && $date === null);

        return $run;
    }

    /**
     * A finished full run stamps the time the console shows ("last run");
     * a dry run, a single step, a replay of another day, or a run with a
     * failed step does not, so a stopped job cannot hide behind them.
     */
    private function record(BillingDailyRun $run, bool $fullRealRun): void
    {
        if ($run->dryRun) {
            return;
        }

        Log::info('billing:daily', collect($run->results)
            ->mapWithKeys(fn (DailyStepResult $result): array => [$result->step => $result->count])
            ->all() + ['day' => $run->day->toDateString(), 'failed' => $run->hasFailures()]);

        if ($fullRealRun && ! $run->hasFailures()) {
            ProviderSetting::write(self::LAST_RUN_SETTING, now((string) config('billing.timezone', 'Asia/Jakarta'))->toIso8601String());
        }
    }
}
