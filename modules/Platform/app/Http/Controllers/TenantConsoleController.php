<?php

namespace Modules\Platform\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Infrastructure\Mock\ProviderMockData;

/**
 * Provider console tenant management (read-only mock for now).
 */
final class TenantConsoleController
{
    public function index(): Response
    {
        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => ProviderMockData::tenants(),
            'plans' => ProviderMockData::plans(),
        ]);
    }

    public function show(string $tenant): Response
    {
        $record = ProviderMockData::tenantById($tenant);

        abort_if($record === null, 404);

        return Inertia::render('Platform/Tenants/Show', [
            'tenant' => $record,
            'modules' => ProviderMockData::modules(),
            'permissionCatalog' => ProviderMockData::permissionCatalog(),
            'roles' => ProviderMockData::tenantRoles(),
            'plans' => ProviderMockData::plans(),
            'invoices' => array_values(array_filter(
                ProviderMockData::invoices(),
                fn (array $invoice): bool => $invoice['tenantId'] === $tenant,
            )),
        ]);
    }
}
