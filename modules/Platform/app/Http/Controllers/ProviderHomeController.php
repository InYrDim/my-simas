<?php

namespace Modules\Platform\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Domain\Models\TenantApplicationStatus;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\App\Http\Support\ConsoleResources;
use Modules\Platform\App\Infrastructure\Billing\BillingSummary;

/**
 * Provider console dashboard: what needs a decision today and the shape
 * of the platform. Every figure is read from the database.
 */
final class ProviderHomeController
{
    public function __construct(
        private readonly BillingSummary $summary,
    ) {}

    public function __invoke(): Response
    {
        $billing = $this->summary->summary();

        return Inertia::render('Platform/ProviderHome', [
            'stats' => [
                'activeTenants' => Tenant::query()->where('status', TenantStatus::Active)->count(),
                'suspendedTenants' => Tenant::query()->where('status', TenantStatus::Suspended)->count(),
                'pendingApplications' => TenantApplication::query()->where('status', TenantApplicationStatus::Pending)->count(),
                'trial' => $billing['trial'],
                'mrr' => $billing['mrr'],
                'endingSoon' => Subscription::query()
                    ->where('status', SubscriptionStatus::Active)
                    ->whereBetween('current_period_end', [BillingClock::today(), BillingClock::today()->addDays(30)])
                    ->count(),
            ],
            'attention' => $this->summary->needsAttention()
                ->map(fn (Subscription $subscription): array => ConsoleResources::subscription($subscription))
                ->all(),
            'trend' => $this->summary->monthlyTrend(6),
        ]);
    }
}
