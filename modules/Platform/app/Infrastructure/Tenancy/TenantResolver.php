<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Contracts\TenantData;

/**
 * Internal — other modules must not bind or replace this; middleware
 * only calls it. One strategy: the school code (tenant id today, NPSN
 * later) identifies the tenant; hosts are irrelevant.
 */
interface TenantResolver
{
    /**
     * Resolve the tenant for a school code.
     *
     * @throws TenantMissingException when no tenant matches
     */
    public function resolve(string $code): TenantData;
}
