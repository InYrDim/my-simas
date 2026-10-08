<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Illuminate\Database\Eloquent\Collection;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Support\BillingClock;

/**
 * Read model for the console's revenue figures. Everything is computed
 * from rows and dates; there is no cached aggregate.
 */
final class BillingSummary
{
    /**
     * @return array{mrr: int, arr: int, activeSubscriptions: int, trial: int, trialExpired: int, dueOrOverdue: int, cancelled: int}
     */
    public function summary(): array
    {
        // A billing-exempt school owes nothing, so it is no revenue.
        $subscriptions = Subscription::query()->with(['plan', 'tenant'])->get()
            ->reject(fn (Subscription $s): bool => $s->tenant?->billing_exempt === true);

        $states = $subscriptions->groupBy(fn (Subscription $s): string => $s->displayState());

        $mrr = $subscriptions
            ->filter(fn (Subscription $s): bool => in_array($s->displayState(), [Subscription::STATE_ACTIVE, Subscription::STATE_DUE], true))
            ->sum(fn (Subscription $s): int => $s->plan?->monthlyEquivalent($s->billing_cycle) ?? 0);

        $count = fn (string ...$keys): int => collect($keys)->sum(fn (string $key): int => $states->get($key)?->count() ?? 0);

        return [
            'mrr' => (int) $mrr,
            'arr' => (int) $mrr * 12,
            'activeSubscriptions' => $count(Subscription::STATE_ACTIVE, Subscription::STATE_DUE),
            'trial' => $count(Subscription::STATE_TRIAL),
            'trialExpired' => $count(Subscription::STATE_TRIAL_EXPIRED),
            'dueOrOverdue' => $count(Subscription::STATE_DUE, Subscription::STATE_OVERDUE),
            'cancelled' => $count(Subscription::STATE_CANCELLED),
        ];
    }

    /**
     * Subscriptions that need the provider's attention: due, overdue,
     * trial expired, or a trial ending within the due-soon window.
     *
     * @return Collection<int, Subscription>
     */
    public function needsAttention(): Collection
    {
        $soon = BillingClock::today()->addDays((int) config('billing.due_soon_days', 7));

        return Subscription::query()
            ->with(['plan', 'tenant'])
            ->get()
            ->reject(fn (Subscription $s): bool => $s->tenant?->billing_exempt === true)
            ->filter(function (Subscription $s) use ($soon): bool {
                $state = $s->displayState();

                if ($state === Subscription::STATE_TRIAL) {
                    return $s->trial_ends_at !== null && $s->trial_ends_at->lte($soon);
                }

                return in_array($state, [Subscription::STATE_DUE, Subscription::STATE_OVERDUE, Subscription::STATE_TRIAL_EXPIRED], true);
            })
            ->sortBy(fn (Subscription $s): string => (string) $s->endsAt()?->toDateString())
            ->values();
    }

    /**
     * Last N calendar months (oldest first): new tenants and paid revenue.
     *
     * @return list<array{month: string, newTenants: int, revenue: int}>
     */
    public function monthlyTrend(int $months = 6): array
    {
        $first = BillingClock::today()->startOfMonth()->subMonths($months - 1);

        $revenueByMonth = Invoice::query()
            ->where('status', InvoiceStatus::Paid)
            ->where('paid_at', '>=', $first)
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Invoice $i): string => $i->paid_at->format('Y-m'))
            ->map(fn ($group): int => (int) $group->sum('amount'));

        $tenantsByMonth = Tenant::query()
            ->where('created_at', '>=', $first)
            ->get(['created_at'])
            ->groupBy(fn (Tenant $t): string => $t->created_at->format('Y-m'))
            ->map(fn ($group): int => $group->count());

        $trend = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $first->copy()->addMonths($i);

            $trend[] = [
                'month' => $month->copy()->settings(['locale' => 'id'])->translatedFormat('M'),
                'newTenants' => (int) ($tenantsByMonth[$month->format('Y-m')] ?? 0),
                'revenue' => (int) ($revenueByMonth[$month->format('Y-m')] ?? 0),
            ];
        }

        return $trend;
    }
}
