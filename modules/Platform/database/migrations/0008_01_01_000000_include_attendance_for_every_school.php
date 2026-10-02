<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\DefaultModuleRegistry;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;

return new class extends Migration
{
    private const MODULE = 'attendance';

    /**
     * Absensi goes to every school for now (decision of 2 October 2026;
     * which plan includes it is sorted out later). Plans created before
     * the module existed list only core + identity, and a plan switches
     * off what it does not list — so every plan gets the module, and so
     * does every school that already exists. New plans get it from
     * BillingMasterDataSeeder.
     *
     * Nothing is taken away on rollback: a provider may have changed the
     * lists since.
     */
    public function up(): void
    {
        if (! app(DefaultModuleRegistry::class)->exists(self::MODULE)) {
            return;
        }

        Plan::query()->get()->each(function (Plan $plan): void {
            if (! in_array(self::MODULE, $plan->modules, true)) {
                $plan->forceFill(['modules' => [...$plan->modules, self::MODULE]])->save();
            }
        });

        $flags = app(ModuleFlagManager::class);
        $context = app(TenantContext::class);

        Tenant::query()->pluck('id')->each(function (string $tenantId) use ($flags, $context): void {
            $flags->enable($tenantId, self::MODULE);

            // The cached flag lives in the partition of whoever read it:
            // the school's own requests keep theirs under the school. The
            // call above dropped the central copy; this one drops the
            // school's, so nobody waits for the cache to expire.
            $context->run($tenantId, fn () => $flags->enable($tenantId, self::MODULE));
        });
    }

    public function down(): void {}
};
