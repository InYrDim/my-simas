<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Illuminate\Support\Facades\Event;
use Modules\Platform\App\Contracts\Events\TenantCreated;

/**
 * Internal indirection so Platform internals can dispatch public events
 * (and, from Stage 6, reset Spatie permission caches) without the
 * contract layer depending on anything.
 */
final class TenantEvents
{
    public static function dispatchTenantCreated(string $tenantId): void
    {
        Event::dispatch(new TenantCreated($tenantId));
    }
}
