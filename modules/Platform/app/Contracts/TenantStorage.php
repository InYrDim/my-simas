<?php

namespace Modules\Platform\App\Contracts;

/**
 * Storage partitioned per tenant on the private disk: every path is
 * rooted at tenants/{tenant_id}/{module}/... so module code can never
 * reach outside its own tenant's directory.
 */
interface TenantStorage
{
    /**
     * Absolute (storage-app) path for a module-relative file:
     * tenants/{tenant_id}/{module}/{relative}. Central code (no tenant
     * context) gets central/{module}/{relative}.
     */
    public function path(string $module, string $relative = ''): string;

    /**
     * Read a file from the current tenant's directory.
     */
    public function get(string $module, string $relative): string;

    /**
     * Write a file into the current tenant's directory.
     */
    public function put(string $module, string $relative, string $contents): bool;

    /**
     * Delete a file from the current tenant's directory.
     */
    public function delete(string $module, string $relative): bool;

    /**
     * Whether a file exists in the current tenant's directory.
     */
    public function exists(string $module, string $relative): bool;
}
