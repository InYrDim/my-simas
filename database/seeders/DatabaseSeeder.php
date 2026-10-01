<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
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
        // Dev tenants (sekolah-a / sekolah-b) for local testing —
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

        // Idempotent: a re-seed must not trip unique(tenant_id, email).
        // DB::table() bypasses the tenant scope, so no context is needed.
        $exists = DB::table('users')
            ->where('tenant_id', $sekolahA->id)
            ->where('email', 'admin@sekolah-a.test')
            ->exists();

        if ($exists) {
            $this->command->info('Dev user [admin@sekolah-a.test] already seeded.');

            return;
        }

        $user = UserFactory::new()->forTenant($sekolahA->id)->create([
            'name' => 'Admin Sekolah A',
            'email' => 'admin@sekolah-a.test',
        ]);

        // Root seeder is app-level glue (Deptrac Database layer): it may
        // touch Identity's factory and Platform's contracts directly. Role
        // assignment resolves against the CURRENT tenant (fail-closed
        // without context), so run it inside the tenant's context. The
        // Identity User model itself may NOT be imported here (arch
        // test) — method_exists() narrows the factory's union return
        // type without naming the class.
        app(TenantContext::class)->run($sekolahA->id, function () use ($user): void {
            assert(method_exists($user, 'assignTenantRole'));
            $user->assignTenantRole('admin-sekolah');
        });

        $this->command->info('Seeded dev user [admin@sekolah-a.test] (password: password, role: admin-sekolah).');
    }
}
