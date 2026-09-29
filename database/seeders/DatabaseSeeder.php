<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\Database\Seeders\PlatformDevSeeder;

/**
 * NOTE: deliberately NO WithoutModelEvents. Tenant-scoped models fill
 * `tenant_id` in their `creating` hook (BelongsToTenant) — disabling
 * model events here silently produces NOT NULL violations on every
 * tenant-owned table (and would skip ULID-free timestamps etc.).
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Dev tenants (sekolah-a / sekolah-b) for subdomain testing —
        // only in local, never in staging/production.
        if (app()->isLocal()) {
            $this->call(PlatformDevSeeder::class);
        }
    }
}
