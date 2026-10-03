<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Attendance\Database\Seeders\AttendanceDemoSeeder;
use Modules\Core\Database\Seeders\CoreDemoAccountsSeeder;
use Modules\Core\Database\Seeders\CoreDemoSeeder;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Database\Seeders\BillingMasterDataSeeder;
use Modules\Ppdb\Database\Seeders\PpdbDemoSeeder;

/**
 * One complete demo school (`sekolah-demo`): every module on, a paying
 * subscription, a school admin, all master data (classes, students,
 * teachers, tenaga kependidikan), their accounts, attendance and PPDB.
 * Local only; safe to run again, every step skips what already exists.
 *
 *   php artisan db:seed --class="Database\Seeders\DemoSchoolSeeder"
 *
 * Root seeder = app-level glue, so it may wire the modules together.
 * Deliberately no WithoutModelEvents: `tenant_id` is filled by a model
 * event.
 */
class DemoSchoolSeeder extends Seeder
{
    public const SLUG = 'sekolah-demo';

    public const ADMIN_EMAIL = 'admin@sekolah.demo';

    public const ADMIN_PASSWORD = 'demo123';

    public function run(ModuleRegistry $registry, ModuleFlagManager $flags, TenantContext $context): void
    {
        if (! app()->isLocal()) {
            $this->command->error('The demo school is seeded in the local environment only.');

            return;
        }

        $this->call(BillingMasterDataSeeder::class);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => self::SLUG],
            ['name' => 'SMA Sekolah Demo', 'timezone' => 'Asia/Jakarta', 'status' => 'active'],
        );

        foreach (['core', 'identity', 'attendance', 'ppdb'] as $module) {
            if ($registry->exists($module)) {
                $flags->enable($tenant->id, $module);
            }
        }

        $this->seedSubscription($tenant);
        $this->seedAdmin($tenant);

        $context->run($tenant->id, function (): void {
            app(CoreDemoSeeder::class)->seedSchool();
            app(CoreDemoAccountsSeeder::class)->setCommand($this->command)->seedAccounts();
            app(AttendanceDemoSeeder::class)->seedSchool();
            app(PpdbDemoSeeder::class)->seedSchool();
        });

        $this->command->info("Demo school [{$tenant->slug}] ready — school code: {$tenant->id}");
        $this->command->info('Admin: '.self::ADMIN_EMAIL.' / '.self::ADMIN_PASSWORD);
    }

    private function seedSubscription(Tenant $tenant): void
    {
        if (Subscription::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $standard = Plan::query()->where('key', 'standard')->firstOrFail();

        SubscriptionFactory::new()->forPlan($standard->id)->active(20)->forTenant($tenant->id)->create();
    }

    private function seedAdmin(Tenant $tenant): void
    {
        // DB::table() bypasses the tenant scope, so no context is needed.
        $exists = DB::table('users')
            ->where('tenant_id', $tenant->id)
            ->where('email', self::ADMIN_EMAIL)
            ->exists();

        if ($exists) {
            return;
        }

        $user = UserFactory::new()->forTenant($tenant->id)->create([
            'name' => 'Admin Sekolah Demo',
            'email' => self::ADMIN_EMAIL,
            'password' => Hash::make(self::ADMIN_PASSWORD),
        ]);

        app(TenantContext::class)->run($tenant->id, function () use ($user): void {
            assert(method_exists($user, 'assignTenantRole'));
            $user->assignTenantRole('admin-sekolah');
        });
    }
}
