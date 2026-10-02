<?php

namespace Modules\Core\App\Contracts;

/**
 * Registry of the figures and panels shown on the Statistik page. Each
 * module registers its own provider from its own service provider; a
 * provider is asked only while its module is active for the current
 * tenant.
 */
interface StatisticsRegistry
{
    /**
     * Register a statistics provider for the given module key. Called from
     * the owning module's service provider during boot. Idempotent per
     * class.
     *
     * @param  class-string<StatisticsProvider>  $provider
     */
    public function register(string $module, string $provider): void;
}
