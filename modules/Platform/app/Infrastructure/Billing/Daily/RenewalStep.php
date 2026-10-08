<?php

namespace Modules\Platform\App\Infrastructure\Billing\Daily;

use Carbon\CarbonInterface;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;
use Throwable;

/**
 * Issues the renewal invoice ahead of the end of the paid period.
 *
 * Only an active, not-cancelled subscription of a school that is billed,
 * and only one with no unpaid invoice at all: an open upgrade or renewal
 * invoice is never replaced from here. A lapsed subscription gets its
 * invoice too, so a school in grace (or already closed) has something to
 * pay. `renew()` announces the new invoice by email.
 */
final class RenewalStep
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
    ) {}

    public function run(CarbonInterface $today, bool $dryRun): DailyStepResult
    {
        $until = $today->copy()->addDays((int) config('billing.renewal_invoice_days_before', 14));
        $lines = [];
        $failures = [];

        Subscription::query()
            ->with('tenant')
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', '<=', $until->toDateString())
            ->whereDoesntHave('invoices', fn ($invoices) => $invoices->where('status', InvoiceStatus::Unpaid))
            ->orderBy('id')
            ->each(function (Subscription $subscription) use ($dryRun, &$lines, &$failures): void {
                $tenant = $subscription->tenant;

                if ($tenant === null || $tenant->billing_exempt) {
                    return;
                }

                try {
                    if (! $dryRun) {
                        $this->subscriptions->renew($subscription);
                    }

                    $lines[] = "{$tenant->slug}: invoice perpanjangan untuk periode berakhir {$subscription->current_period_end->toDateString()}";
                } catch (Throwable $exception) {
                    report($exception);
                    $failures[] = "{$tenant->slug}: {$exception->getMessage()}";
                }
            });

        return new DailyStepResult('renewals', count($lines), $lines, $failures);
    }
}
