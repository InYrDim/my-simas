<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Modules\Platform\App\Domain\Models\TenantStatus;

final class TenantActivateCommand extends TenantStatusCommand
{
    protected $signature = 'tenant:activate {tenant : Tenant slug}';

    protected $description = 'Reactivate a suspended tenant';

    protected function targetStatus(): TenantStatus
    {
        return TenantStatus::Active;
    }

    protected function verb(): string
    {
        return 'activated';
    }
}
