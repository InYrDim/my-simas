<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Modules\Platform\App\Contracts\TenantCache;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Default TenantCache: prefixes every key with tenant:{id}: (or
 * central:). Backed by the application's default cache store; the
 * repository is resolved lazily so tests can swap stores first.
 */
final class PartitionedTenantCache implements TenantCache
{
    private ?CacheRepository $repository = null;

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function repository(): CacheRepository
    {
        return $this->repository ??= app('cache')->store();
    }

    public function key(string $key): string
    {
        $tenantId = $this->context->id();

        return ($tenantId === null ? 'central:' : 'tenant:'.$tenantId.':').$key;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository()->get($this->key($key), $default);
    }

    public function put(string $key, mixed $value, DateInterval|int|DateTimeInterface|null $ttl = null): bool
    {
        return (bool) $this->repository()->put($this->key($key), $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->repository()->forget($this->key($key));
    }

    /**
     * Remember forever (until explicitly invalidated).
     *
     * @template TCacheValue
     *
     * @param  callable(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        // Wrap in a concrete Closure: the repository signature expects
        // Closure(): mixed and cannot infer from a bare callable.
        return $this->repository()->rememberForever(
            $this->key($key),
            fn (): mixed => $callback(),
        );
    }
}
