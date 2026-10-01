<?php

namespace Modules\Platform\App\Contracts;

/**
 * Read-only lookup of tenants for code that runs outside any tenant
 * context (provider-console pages in other modules). Returns TenantData
 * snapshots, never the internal Tenant model.
 */
interface TenantDirectory
{
    /**
     * Every (non-deleted) tenant, ordered by name.
     *
     * @return array<int, TenantData>
     */
    public function all(): array;

    /**
     * The given tenants keyed by id; unknown ids are simply absent.
     *
     * @param  array<int, string>  $ids
     * @return array<string, TenantData>
     */
    public function findMany(array $ids): array;
}
