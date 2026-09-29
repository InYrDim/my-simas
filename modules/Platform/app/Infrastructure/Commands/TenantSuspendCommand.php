<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Modules\Platform\App\Domain\Models\TenantStatus;

final class TenantSuspendCommand extends TenantStatusCommand
{
    protected $signature = 'tenant:suspend {tenant : Tenant slug}';

    protected $description = 'Suspend a tenant (all its requests get 403)';

    protected function targetStatus(): TenantStatus
    {
        return TenantStatus::Suspended;
    }

    protected function verb(): string
    {
        return 'suspended';
    }
}
