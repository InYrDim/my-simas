<?php

namespace Modules\Platform\App\Contracts\Events;

/**
 * Fired when a new tenant is provisioned. Other modules listen to seed
 * per-tenant defaults (e.g. default roles in Fase 2).
 */
final class TenantCreated
{
    public function __construct(
        public readonly string $tenantId,
    ) {}
}
