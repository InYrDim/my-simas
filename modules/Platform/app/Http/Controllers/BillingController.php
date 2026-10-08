<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\ProviderSetting;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Http\Support\ConsoleResources;
use Modules\Platform\App\Infrastructure\Billing\BillingSummary;
use Modules\Platform\App\Infrastructure\Billing\Daily\BillingDaily;
use Modules\Platform\App\Infrastructure\Billing\Daily\SuspensionStep;

/**
 * Provider console billing overview and the subscription list. Plans and
 * invoices have their own controllers.
 */
final class BillingController
{
    public function __construct(
        private readonly BillingSummary $summary,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Platform/Billing/Index', [
            'summary' => $this->summary->summary(),
            'trend' => $this->summary->monthlyTrend(6),
            'attention' => $this->summary->needsAttention()
                ->map(fn (Subscription $subscription): array => ConsoleResources::subscription($subscription))
                ->all(),
            'daily' => $this->dailyStatus(),
        ]);
    }

    /**
     * Whether the billing:daily cron job is alive: when it last finished a
     * full run, whether that is too long ago, and whether it is holding
     * back a mass suspension that waits for the provider.
     *
     * @return array{lastRunAt: string|null, stale: bool, staleDays: int, blocked: array{at: string, count: int}|null}
     */
    private function dailyStatus(): array
    {
        $lastRun = ProviderSetting::read(BillingDaily::LAST_RUN_SETTING);
        $staleDays = (int) config('billing.cron_stale_days', 2);
        $blocked = ProviderSetting::read(SuspensionStep::BLOCKED_SETTING);

        return [
            'lastRunAt' => is_string($lastRun) ? $lastRun : null,
            'stale' => ! is_string($lastRun) || Carbon::parse($lastRun)->lt(now()->subDays($staleDays)),
            'staleDays' => $staleDays,
            'blocked' => is_array($blocked) ? ['at' => (string) ($blocked['at'] ?? ''), 'count' => (int) ($blocked['count'] ?? 0)] : null,
        ];
    }

    public function subscriptions(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'cycle' => ['nullable', Rule::enum(BillingCycle::class)],
            'plan' => ['nullable', 'string', 'max:100'],
        ]);

        $subscriptions = Subscription::query()
            ->with(['plan', 'tenant'])
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->whereHas(
                'tenant',
                fn ($tenant) => $tenant->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%'),
            ))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['cycle'] ?? null, fn ($query, string $cycle) => $query->where('billing_cycle', $cycle))
            ->when($filters['plan'] ?? null, fn ($query, string $plan) => $query->whereHas(
                'plan',
                fn ($inner) => $inner->where('key', $plan),
            ))
            ->orderByRaw('case when status = ? then 0 else 1 end', [SubscriptionStatus::Trial->value])
            ->orderBy('current_period_end')
            ->orderBy('trial_ends_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Subscription $subscription): array => ConsoleResources::subscription($subscription));

        return Inertia::render('Platform/Billing/Subscriptions', [
            'subscriptions' => $subscriptions,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'cycle' => $filters['cycle'] ?? '',
                'plan' => $filters['plan'] ?? '',
            ],
            'plans' => Plan::query()->orderBy('sort_order')->get(['key', 'name']),
        ]);
    }
}
