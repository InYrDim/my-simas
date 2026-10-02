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

    /**
     * The accounts with these ids in the CURRENT tenant, keyed by id; an
     * id of another school or of no account is simply absent.
     *
     * @param  array<int, int>  $ids
     * @return array<int, UserRecord>
     */
    public function findMany(array $ids): array;

    /**
     * Count the accounts of the CURRENT tenant so other modules can
     * report on a school without importing the User model. Fails
     * closed without tenant context, like every user read.
     *
     * @throws Exceptions\TenantNotSetException without a tenant context.
     */
    public function currentTenantSummary(): UserSummary;
}
