<?php

namespace Modules\Platform\App\Contracts;

use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;

/**
 * Read-only snapshot of the current tenant. The internal Tenant model is
 * never handed to other modules — this is what TenantContext returns.
 */
final readonly class TenantData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $timezone,
        public TenantStatus $status,
    ) {}

    /**
     * Build the DTO from the internal model (Platform-internal use only;
     * consumers never receive the model itself).
     */
    public static function fromTenant(Tenant $tenant): self
    {
        return new self(
            id: $tenant->id,
            name: $tenant->name,
            slug: $tenant->slug,
            timezone: $tenant->timezone,
            status: $tenant->status,
        );
    }
}
