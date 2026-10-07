<?php

namespace Modules\Core\App\Contracts;

/**
 * Registry of the blocks shown on the Beranda. Each module registers its
 * own provider from its own service provider; a provider is asked only
 * while its module is active for the current tenant.
 */
interface DashboardRegistry
{
    /**
     * Register a dashboard provider for the given module key. Called from
     * the owning module's service provider during boot. Idempotent per
     * class.
     *
     * @param  class-string<DashboardWidgetProvider>  $provider
     */
    public function register(string $module, string $provider): void;
}
