<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantData;

/**
 * Default TenantContext: per-process state (request-scoped by nature;
 * resolved per request by the middleware). Also bridges context changes
 * to the permission layer via TenantBridge (Stage 6 wires the Spatie
 * side; the bridge point exists from day one).
 */
final class DefaultTenantContext implements TenantContext
{
    private ?string $tenantId = null;

    private ?TenantData $tenant = null;

    public function __construct(
        private readonly TenantBridge $bridge,
    ) {}

    public function current(): ?TenantData
    {
        return $this->tenant;
    }

    public function currentOrFail(): TenantData
    {
        return $this->tenant ?? throw new TenantNotSetException;
    }

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function timezone(): string
    {
        $timezone = $this->tenant?->timezone;

        return $timezone ?? config('tenancy.default_timezone', 'UTC');
    }

    public function set(string $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->bridge->onTenantSet($tenantId);
    }

    public function forget(): void
    {
        $this->tenantId = null;
        $this->tenant = null;
        $this->bridge->onTenantForget();
    }

    public function run(string $tenantId, callable $callback): mixed
    {
        $previousId = $this->tenantId;
        $previousTenant = $this->tenant;

        try {
            $this->set($tenantId);

            if ($this->tenant === null) {
                // set() via bridge does not hydrate the DTO when called
                // directly (tests, CLI); hydrate now, once.
                $this->tenant = $this->bridge->hydrateTenant($tenantId);
            }

            return $callback();
        } finally {
            $this->restore($previousId, $previousTenant);
        }
    }

    public function runWithoutTenant(callable $callback): mixed
    {
        $previousId = $this->tenantId;
        $previousTenant = $this->tenant;

        try {
            $this->tenantId = null;
            $this->tenant = null;
            $this->bridge->onTenantForget();

            return $callback();
        } finally {
            $this->restore($previousId, $previousTenant);
        }
    }

    /**
     * Adopt a fully-resolved tenant DTO (used by ResolveTenant after the
     * resolver returned). Internal API — not part of the public contract.
     */
    public function adopt(TenantData $tenant): void
    {
        $this->tenantId = $tenant->id;
        $this->tenant = $tenant;
        $this->bridge->onTenantAdopted($tenant);
    }

    /**
     * Restore the previous context (nested run/runWithoutTenant safe).
     */
    private function restore(?string $previousId, ?TenantData $previousTenant): void
    {
        if ($previousId === null) {
            $this->forget();

            return;
        }

        $this->tenantId = $previousId;
        $this->tenant = $previousTenant;
        $this->bridge->onTenantRestored($previousTenant, $previousId);
    }
}
