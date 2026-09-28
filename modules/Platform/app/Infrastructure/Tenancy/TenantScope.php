<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Global scope applying the current tenant to every query. Inside
 * TenantContext::runWithoutTenant() the scope is a no-op; without any
 * context it fails closed with TenantNotSetException.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @implements Scope<TModel>
 */
final class TenantScope implements Scope
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = $this->context->id();

        // Explicit opt-out via runWithoutTenant().
        if ($this->context instanceof DefaultTenantContext && $this->context->isWithoutTenantRun()) {
            return;
        }

        if ($tenantId === null) {
            throw new TenantNotSetException(
                'Querying '.class_basename($model).' requires tenant context (central request?). '.
                'Wrap in TenantContext::runWithoutTenant() if cross-tenant access is intended.',
            );
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
