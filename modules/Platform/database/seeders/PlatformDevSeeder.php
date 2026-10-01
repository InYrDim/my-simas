<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Domain\Models\TenantApplicationStatus;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\SubscriptionFactory;

/**
 * Local-development tenants. NEVER runs outside local: it creates
 * predictable slugs (sekolah-a/sekolah-b); log in with the school code
 * printed below (the tenant id).
 */
class PlatformDevSeeder extends Seeder
{
    public function run(ModuleRegistry $registry, ModuleFlagManager $flags): void
    {
        $definitions = [
            ['name' => 'SMA Sekolah A', 'slug' => 'sekolah-a', 'timezone' => 'Asia/Jakarta', 'modules' => ['core', 'identity', 'attendance', 'ppdb']],
            ['name' => 'SMA Sekolah B', 'slug' => 'sekolah-b', 'timezone' => 'Asia/Makassar', 'modules' => ['core']],
        ];

        foreach ($definitions as $definition) {
            $modules = $definition['modules'];
            unset($definition['modules']);

            /** @var Tenant $tenant */
            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => $definition['slug']],
                $definition + ['status' => 'active'],
            );

            foreach ($modules as $module) {
                if ($registry->exists($module)) {
                    $flags->enable($tenant->id, $module);
                }
            }

            $this->command->info("Seeded tenant [{$tenant->slug}] ({$tenant->timezone}) — school code: {$tenant->id}");
        }

        $this->call(BillingMasterDataSeeder::class);
        $this->seedSubscriptions();

        $this->seedProviderUser();
        $this->seedPendingApplication();
    }

    /**
     * sekolah-a is a paying subscriber, sekolah-b is on trial. Rows are
     * written directly (no module sync) so the dev tenants keep their
     * deliberately different module sets. Idempotent per tenant.
     */
    protected function seedSubscriptions(): void
    {
        $starter = Plan::query()->where('key', 'starter')->firstOrFail();
        $standard = Plan::query()->where('key', 'standard')->firstOrFail();

        $subscribers = [
            'sekolah-a' => SubscriptionFactory::new()->forPlan($standard->id)->active(20),
            'sekolah-b' => SubscriptionFactory::new()->forPlan($starter->id)->trialEndingIn(14),
        ];

        foreach ($subscribers as $slug => $factory) {
            $tenant = Tenant::query()->where('slug', $slug)->first();

            if ($tenant === null || Subscription::query()->where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            $factory->forTenant($tenant->id)->create();
        }
    }

    /**
     * One provider staff account for the console (login at
     * console.localhost/login).
     */
    protected function seedProviderUser(): void
    {
        ProviderUser::query()->firstOrCreate(
            ['email' => 'admin@simas.com'],
            ['name' => 'Provider Admin', 'password' => 'admin123'],
        );

        $this->command->info('Seeded provider user [admin@simas.com] (password: admin123).');
    }

    /**
     * One pending application (sekolah-c) so the review → ACC →
     * provisioning flow can be exercised end-to-end without filling
     * the onboarding form (applicant login kepsek@sekolah-c.test /
     * password). Idempotent: skipped when this applicant already has
     * an application in any state (one application per applicant).
     */
    protected function seedPendingApplication(): void
    {
        $exists = TenantApplication::query()
            ->where('applicant_email', 'kepsek@sekolah-c.test')
            ->exists();

        if ($exists) {
            return;
        }

        // The applicant account behind the application: log in at
        // /pemohon/masuk with kepsek@sekolah-c.test / password.
        $applicant = Applicant::query()->firstOrNew(['email' => 'kepsek@sekolah-c.test']);
        $applicant->forceFill([
            'name' => 'Kepala Sekolah C',
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();

        (new TenantApplication)->forceFill([
            'applicant_id' => $applicant->id,
            'school_name' => 'SMA Sekolah C',
            'desired_slug' => 'sekolah-c',
            'timezone' => 'Asia/Jakarta',
            'plan_key' => (string) config('billing.trial_plan'),
            'applicant_name' => $applicant->name,
            'applicant_email' => $applicant->email,
            'applicant_message' => 'Mohon di-ACC, kami siap mulai semester ini.',
            'status' => TenantApplicationStatus::Pending,
            'submitted_at' => now(),
        ])->save();

        $this->command->info('Seeded pending application [sekolah-c].');
    }
}
