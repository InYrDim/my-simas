<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Illuminate\Support\Facades\Cache;
use Modules\Platform\App\Domain\Models\Tenant;

/**
 * Internal helper to load Tenant models by id with a small cache. The
 * tenants table is central data — it has no tenant_id and is never
 * scoped.
 */
final class TenantHydrator
{
    private const CACHE_TTL = 300;

    public static function find(string $tenantId): ?Tenant
    {
        $key = 'platform:tenant:id:'.$tenantId;

        $attributes = Cache::get($key);

        // Cache a plain attribute ARRAY, never the Eloquent model:
        // cached models unserialize into __PHP_Incomplete_Class when
        // the classmap shifts (autoload regen, refactor) and pin the
        // object graph into the cache. Arrays are inert and safe.
        if (! is_array($attributes) || ($attributes['id'] ?? null) !== $tenantId) {
            // Never cache null: a tenant deleted after being cached
            // would be invisible for the whole TTL. Old garbage entries
            // (null / __PHP_Incomplete_Class / wrong id) fail this
            // validation and are simply re-fetched.
            $model = Tenant::query()->find($tenantId);

            if ($model === null) {
                return null;
            }

            $attributes = $model->getAttributes();
            Cache::put($key, $attributes, self::CACHE_TTL);
        }

        // setRawAttributes (NOT newInstance): newInstance() goes through
        // fill() and would drop every non-fillable column — notably the
        // primary key, leaving a model whose ->id is null.
        return (new Tenant)->setRawAttributes($attributes, true);
    }

    /**
     * Bust the cached DTO for a tenant (status changes, edits).
     */
    public static function flush(string $tenantId): void
    {
        Cache::forget('platform:tenant:id:'.$tenantId);
    }
}
