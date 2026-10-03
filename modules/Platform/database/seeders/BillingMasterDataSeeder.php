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
 * modules/Platform/CONTRACT.md → "Master data & assumptions". Every plan includes
 * Absensi for now; which plan keeps it is sorted out later.
 */
class BillingMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['key' => 'starter', 'name' => 'Starter', 'price_monthly' => 150_000, 'price_yearly' => 1_500_000, 'max_users' => 25, 'sort_order' => 1],
            ['key' => 'standard', 'name' => 'Standard', 'price_monthly' => 350_000, 'price_yearly' => 3_500_000, 'max_users' => 100, 'sort_order' => 2],
            ['key' => 'pro', 'name' => 'Pro', 'price_monthly' => 750_000, 'price_yearly' => 7_500_000, 'max_users' => null, 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            Plan::query()->firstOrCreate(
                ['key' => $plan['key']],
                [...$plan, 'modules' => ['core', 'identity', 'attendance'], 'is_active' => true],
            );
        }

        $this->command->info('Seeded billing master data: plans starter, standard, pro.');
    }
}
