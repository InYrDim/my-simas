<?php

namespace Modules\Platform\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Infrastructure\Mock\ProviderMockData;

/**
 * Provider console subscription billing (monthly/yearly plans per
 * tenant). Draft UI over mock data; the model is refined once the
 * screens are walked through.
 */
final class BillingController
{
    public function index(): Response
    {
        return Inertia::render('Platform/Billing/Index', [
            'summary' => ProviderMockData::billingSummary(),
            'trend' => ProviderMockData::monthlyTrend(),
            'attention' => array_values(array_filter(
                ProviderMockData::subscriptions(),
                fn (array $s): bool => in_array($s['status'], ['due', 'overdue'], true),
            )),
        ]);
    }

    public function subscriptions(): Response
    {
        return Inertia::render('Platform/Billing/Subscriptions', [
            'subscriptions' => ProviderMockData::subscriptions(),
            'plans' => ProviderMockData::plans(),
        ]);
    }

    public function plans(): Response
    {
        return Inertia::render('Platform/Billing/Plans', [
            'plans' => ProviderMockData::plans(),
            'modules' => ProviderMockData::modules(),
        ]);
    }

    public function invoices(): Response
    {
        return Inertia::render('Platform/Billing/Invoices', [
            'invoices' => ProviderMockData::invoices(),
        ]);
    }
}
