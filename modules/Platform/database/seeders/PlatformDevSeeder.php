<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;

/**
 * Local-development tenants. NEVER runs outside local: it creates
 * predictable slugs (sekolah-a/sekolah-b) for subdomain testing.
 */
class PlatformDevSeeder extends Seeder
{
    public function run(ModuleRegistry $registry, ModuleFlagManager $flags): void
    {
        $definitions = [
            ['name' => 'SMA Sekolah A', 'slug' => 'sekolah-a', 'timezone' => 'Asia/Jakarta', 'modules' => ['core', 'identity']],
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

            $this->command->info("Seeded tenant [{$tenant->slug}] ({$tenant->timezone}).");
        }
    }
}
