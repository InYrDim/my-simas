<?php

namespace Modules\Platform\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Infrastructure\Mock\ProviderMockData;

/**
 * Provider console dashboard. UI-first stage: every figure comes from
 * ProviderMockData until the real aggregates exist.
 */
final class ProviderHomeController
{
    public function __invoke(): Response
    {
        $tenants = collect(ProviderMockData::tenants());
        $subscriptions = collect(ProviderMockData::subscriptions());
        $billing = ProviderMockData::billingSummary();

        return Inertia::render('Platform/ProviderHome', [
            'stats' => [
                'activeTenants' => $tenants->where('status', 'active')->count(),
                'suspendedTenants' => $tenants->where('status', 'suspended')->count(),
                'pendingApplications' => 1,
                'mrr' => $billing['mrr'],
                'endingSoon' => $subscriptions
                    ->filter(fn (array $s): bool => $s['status'] === 'active' && $s['endsAt'] <= now()->addDays(30)->toDateString())
                    ->count(),
            ],
            'attention' => $subscriptions
                ->whereIn('status', ['due', 'overdue'])
                ->values(),
            'trend' => ProviderMockData::monthlyTrend(),
            'activity' => ProviderMockData::activity(),
        ]);
    }
}
