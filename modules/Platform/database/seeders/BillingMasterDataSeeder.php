<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\App\Domain\Models\Plan;

/**
 * Initial subscription plans (master data). Safe to run in production and
 * idempotent by plan key: re-running never overwrites prices an operator
 * has since edited, it only creates missing plans.
 *
 *   php artisan db:seed --class="Modules\Platform\Database\Seeders\BillingMasterDataSeeder"
 *
 * The figures are assumptions (yearly = 10 × monthly) recorded in
 * modules/Platform/CONTRACT.md → "Master data & assumptions". Plans differ by
 * school size (student and staff-account limits) and by PPDB, which only
 * Pro includes; every plan includes Absensi. Storage has no limit until the
 * provider sets one in the console.
 */
class BillingMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $core = ['core', 'identity', 'attendance'];

        $plans = [
            ['key' => 'starter', 'name' => 'Starter', 'price_monthly' => 150_000, 'price_yearly' => 1_500_000, 'limits' => ['students' => 300, 'staff_accounts' => 25], 'modules' => $core, 'sort_order' => 1],
            ['key' => 'standard', 'name' => 'Standard', 'price_monthly' => 350_000, 'price_yearly' => 3_500_000, 'limits' => ['students' => 1_000, 'staff_accounts' => 100], 'modules' => $core, 'sort_order' => 2],
            ['key' => 'pro', 'name' => 'Pro', 'price_monthly' => 750_000, 'price_yearly' => 7_500_000, 'limits' => null, 'modules' => [...$core, 'ppdb'], 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            Plan::query()->firstOrCreate(
                ['key' => $plan['key']],
                [...$plan, 'is_active' => true, 'is_public' => true],
            );
        }

        $this->command->info('Seeded billing master data: plans starter, standard, pro.');
    }
}
