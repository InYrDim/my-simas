<?php

namespace Modules\Platform\App\Contracts;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Cache partitioned per tenant: every key is automatically prefixed with
 * the current tenant id (or "central" when no tenant is set), so the
 * same logical key never collides across tenants.
 */
interface TenantCache
{
    /**
     * The underlying repository, already prefixed (injectable in tests).
     */
    public function repository(): CacheRepository;

    /**
     * The fully-qualified cache key for the current context, e.g.
     * "tenant:01ABC:permissions:all" or "central:permissions:all".
     */
    public function key(string $key): string;

    /**
     * @return mixed
     */
    public function get(string $key, mixed $default = null);

    public function put(string $key, mixed $value, \DateInterval|int|\DateTimeInterface|null $ttl = null): bool;

    public function forget(string $key): bool;

    /**
     * Remember forever (until explicitly invalidated).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function rememberForever(string $key, callable $callback): mixed;
}
