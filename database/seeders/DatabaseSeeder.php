<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Domain\Models\Tenant;
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
        if (! app()->isLocal()) {
            return;
        }

        $this->call(PlatformDevSeeder::class);

        $this->seedDevUsers();
    }

    /**
     * One login-able dev user per identity-enabled tenant. Lives in the
     * ROOT seeder because it is app-level wiring: Platform cannot import
     * Identity (layer order), and Identity cannot look tenants up
     * (Platform internals).
     */
    protected function seedDevUsers(): void
    {
        /** @var Tenant|null $sekolahA */
        $sekolahA = Tenant::query()->where('slug', 'sekolah-a')->first();

        if ($sekolahA === null) {
            return;
        }

        UserFactory::new()->forTenant($sekolahA->id)->create([
            'name' => 'Admin Sekolah A',
            'email' => 'admin@sekolah-a.test',
        ]);

        $this->command->info('Seeded dev user [admin@sekolah-a.test] (password: password).');
    }
}
