<?php

namespace Modules\Platform\App\Infrastructure\Billing\Daily;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\ProviderSetting;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SuspensionReason;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Infrastructure\Billing\BillingNotifier;
use Modules\Platform\App\Infrastructure\Tenancy\TenantLifecycle;
use Throwable;

/**
 * Closes the schools whose access has run out (see
 * Subscription::accessLapsed): a trial or paid period plus its grace, or
 * a cancelled subscription at the end it had.
 *
 * A school already suspended (for either reason) and a billing-exempt one
 * are left alone. A guard stops one run from closing many schools at once
 * (a wrong clock, a payment nobody recorded): past the cap nothing is
 * suspended and the run says so, until it is repeated with --force.
 */
final class SuspensionStep
{
    public const BLOCKED_SETTING = 'billing.suspend_blocked';

    public function __construct(
        private readonly TenantLifecycle $lifecycle,
        private readonly BillingNotifier $notifier,
    ) {}

    public function run(CarbonInterface $today, bool $dryRun, bool $force): DailyStepResult
    {
        $candidates = Subscription::query()
            ->with('tenant')
            ->orderBy('id')
            ->get()
            ->filter(fn (Subscription $subscription): bool => $subscription->tenant !== null
                && $subscription->tenant->status === TenantStatus::Active
                && ! $subscription->tenant->billing_exempt
                && $subscription->accessLapsed($today))
            ->values();

        if ($this->exceedsCap($candidates->count()) && ! $force) {
            $lines = $candidates
                ->map(fn (Subscription $subscription): string => "{$subscription->tenant->slug}: tertahan, akses habis {$subscription->accessEndsAt()?->toDateString()}")
                ->all();

            if (! $dryRun) {
                ProviderSetting::write(self::BLOCKED_SETTING, [
                    'at' => now()->toIso8601String(),
                    'count' => $candidates->count(),
                ]);

                Log::warning('billing:daily held back a mass suspension', ['schools' => $candidates->count()]);
            }

            return new DailyStepResult('suspensions', 0, $lines, blocked: true);
        }

        if (! $dryRun) {
            ProviderSetting::write(self::BLOCKED_SETTING, null);
        }

        $lines = [];
        $failures = [];

        foreach ($candidates as $subscription) {
            $tenant = $subscription->tenant;

            try {
                if (! $dryRun) {
                    $this->suspend($tenant, $subscription);
                }

                $lines[] = "{$tenant->slug}: disuspend, akses habis {$subscription->accessEndsAt()?->toDateString()}";
            } catch (Throwable $exception) {
                report($exception);
                $failures[] = "{$tenant->slug}: {$exception->getMessage()}";
            }
        }

        return new DailyStepResult('suspensions', count($lines), $lines, $failures);
    }

    private function suspend(Tenant $tenant, Subscription $subscription): void
    {
        $this->lifecycle->suspend($tenant, SuspensionReason::Billing);

        $invoice = Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('subscription_id', $subscription->id)
            ->where('status', InvoiceStatus::Unpaid)
            ->orderByDesc('id')
            ->first();

        $lastAccessDay = $subscription->accessEndsAt();

        if ($lastAccessDay !== null) {
            $this->notifier->accessStopped($subscription, $invoice, $lastAccessDay);
        }
    }

    /**
     * Past `suspend_cap.count` schools, or past `suspend_cap.percent` of
     * the schools that are billed (the percentage only counts once there
     * are at least `suspend_cap.percent_min_schools` of them, or a single
     * overdue school among three would always trip it).
     */
    private function exceedsCap(int $candidates): bool
    {
        $cap = (array) config('billing.suspend_cap', []);

        if ($candidates > (int) ($cap['count'] ?? 5)) {
            return true;
        }

        $billed = Tenant::query()->where('billing_exempt', false)->whereHas('subscription')->count();

        return $billed >= (int) ($cap['percent_min_schools'] ?? 10)
            && $candidates > $billed * (int) ($cap['percent'] ?? 20) / 100;
    }
}
