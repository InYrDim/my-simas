<?php

namespace Modules\Identity\App\Contracts;

interface ResolvesUsers
{
    /**
     * Retrieve a user record by email within the CURRENT tenant
     * (unique(tenant_id, email)). Throws TenantNotSetException when
     * called without tenant context — user lookup is always
     * tenant-scoped.
     */
    public function findByEmail(string $email): ?UserRecord;
}
