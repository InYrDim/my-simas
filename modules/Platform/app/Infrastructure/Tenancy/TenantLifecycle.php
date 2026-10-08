<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Domain\Models\SuspensionReason;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;

/**
 * Write path for a tenant's status and profile. Shared by the artisan
 * commands and the provider console so both flush the hydrator cache the
 * same way (a stale cached DTO would keep serving the old status).
 */
final class TenantLifecycle
{
    /**
     * Close the school. A school that is already suspended keeps the reason
     * it was suspended for: a billing run never turns a manual suspension
     * into one a payment could lift.
     */
    public function suspend(Tenant $tenant, SuspensionReason $reason = SuspensionReason::Manual): Tenant
    {
        if ($tenant->status === TenantStatus::Suspended) {
            return $tenant;
        }

        $tenant->suspended_reason = $reason;

        return $this->setStatus($tenant, TenantStatus::Suspended);
    }

    public function activate(Tenant $tenant): Tenant
    {
        return $this->setStatus($tenant, TenantStatus::Active);
    }

    /**
     * Reopen a school closed for billing, and only that kind: a payment or
     * a trial extension must not undo a manual suspension.
     *
     * @return bool whether the school was reopened
     */
    public function reactivateIfBillingSuspended(Tenant $tenant): bool
    {
        if ($tenant->status !== TenantStatus::Suspended || $tenant->suspended_reason !== SuspensionReason::Billing) {
            return false;
        }

        $this->activate($tenant);

        return true;
    }

    public function setStatus(Tenant $tenant, TenantStatus $status): Tenant
    {
        if ($tenant->status === $status) {
            return $tenant;
        }

        // The reason always matches the status; a suspension nobody gave a
        // reason for counts as the provider's own.
        $tenant->suspended_reason = $status === TenantStatus::Active
            ? null
            : ($tenant->suspended_reason ?? SuspensionReason::Manual);

        $tenant->status = $status;
        $tenant->save();

        TenantHydrator::flush($tenant->id);

        return $tenant;
    }

    /**
     * Change the school code (the tenant's slug). Links and QR codes that
     * carry the old code stop resolving at once, so the old lookup is
     * flushed with the tenant itself.
     */
    public function changeCode(Tenant $tenant, string $code): Tenant
    {
        $previous = $tenant->slug;

        if ($previous === $code) {
            return $tenant;
        }

        $tenant->slug = $code;
        $tenant->save();

        TenantHydrator::flush($tenant->id);
        SchoolCodeTenantResolver::forget($previous);
        SchoolCodeTenantResolver::forget($code);

        return $tenant;
    }

    /**
     * @param  array{name: string, timezone: string, domain: string|null}  $attributes
     */
    public function updateProfile(Tenant $tenant, array $attributes): Tenant
    {
        $tenant->fill($attributes)->save();

        TenantHydrator::flush($tenant->id);

        return $tenant;
    }
}
