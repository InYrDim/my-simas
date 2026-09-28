<?php

namespace Modules\Platform\App\Infrastructure\Modules;

use Modules\Platform\App\Contracts\TenantCache;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Cache for per-tenant module flag lookups, partitioned through
 * TenantCache (so tenant A's flags never collide with tenant B's or
 * with central). Invalidate-on-write: any enable/disable busts the
 * tenant's key so flag changes are visible on the very next request.
 */
final class TenantModulesCache
{
    private const TTL_SECONDS = 300;

    public function __construct(
        private readonly TenantCache $cache,
    ) {}

    public function get(string $tenantId, string $module): ?bool
    {
        $value = $this->cache->get($this->key($tenantId, $module));

        return is_bool($value) ? $value : null;
    }

    public function put(string $tenantId, string $module, bool $enabled): void
    {
        $this->cache->put($this->key($tenantId, $module), $enabled, self::TTL_SECONDS);
    }

    public function forget(string $tenantId, string $module): void
    {
        $this->cache->forget($this->key($tenantId, $module));
    }

    private function key(string $tenantId, string $module): string
    {
        // Prefix with the tenant id explicitly: the lookup must be
        // valid even when called without an ambient tenant context.
        return 'modules:enabled:'.$tenantId.':'.$module;
    }

    /**
     * Whether this cache partition belongs to the given tenant —
     * TenantCache already prefixes tenant:{id}:, so the TenantContext
     * is only used by tests to assert partitioning.
     */
    public function belongsToCurrentPartition(TenantContext $context, string $tenantId): bool
    {
        return $context->id() === $tenantId;
    }
}
