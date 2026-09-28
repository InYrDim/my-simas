<?php

namespace Modules\Platform\App\Contracts\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Infrastructure\Tenancy\TenantScope;

/**
 * Makes an Eloquent model tenant-scoped:
 *
 * - all queries are automatically filtered by the current tenant;
 * - queries without tenant context throw TenantNotSetException unless
 *   wrapped in TenantContext::runWithoutTenant();
 * - `creating` fills tenant_id from the context (never overwrites an
 *   explicitly provided one);
 * - `updating` prevents changing tenant_id of an existing row;
 * - a matching migration MUST have a `tenant_id` column via the
 *   Blueprint::tenantId() macro.
 *
 * Use ::withoutTenancy() for explicit cross-tenant queries (bulk admin
 * jobs etc.) — usage should be conspicuous. DB::table() queries are NOT
 * protected by this scope; do not use them for tenant data.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(app(TenantScope::class));

        static::creating(function (Model $model): void {
            if (array_key_exists('tenant_id', $model->getAttributes())
                && $model->getAttributeValue('tenant_id') !== null) {
                return;
            }

            $model->setAttribute('tenant_id', app(TenantContext::class)->currentOrFail()->id);
        });

        static::updating(function (Model $model): void {
            if (! $model->isDirty('tenant_id')) {
                return;
            }

            $original = $model->getOriginal('tenant_id');

            if ($original !== null && $original !== $model->getAttribute('tenant_id')) {
                $model->setAttribute('tenant_id', $original);
            }
        });
    }

    /**
     * Explicitly drop the tenant scope for this query (cross-tenant,
     * administrative queries). Conspicuous on purpose.
     *
     * @return Builder<static>
     */
    public static function withoutTenancy(): Builder
    {
        /** @var Builder<static> $query */
        $query = static::query();

        return $query->withoutGlobalScope(TenantScope::class);
    }
}
