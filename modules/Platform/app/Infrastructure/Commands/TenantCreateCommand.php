<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Illuminate\Console\Command;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;

use function Laravel\Prompts\error;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create
        {name : Human-readable tenant name}
        {slug : Unique slug, lowercase letters/digits/dashes}
        {--timezone= : IANA timezone (default: config tenancy.default_timezone)}
        {--modules=* : Module keys to enable for this tenant}';

    protected $description = 'Create a new tenant (school)';

    public function handle(
        ModuleRegistry $registry,
        ModuleFlagManager $flags,
    ): int {
        $slug = mb_strtolower(trim((string) $this->argument('slug')));

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            error("Slug [{$slug}] must be lowercase letters, digits, and dashes.");

            return self::FAILURE;
        }

        if (in_array($slug, config('tenancy.reserved_slugs', []), true)) {
            error("Slug [{$slug}] is reserved (central/infrastructure host shadowing).");

            return self::FAILURE;
        }

        if (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            error("Slug [{$slug}] is already taken.");

            return self::FAILURE;
        }

        $timezone = (string) ($this->option('timezone') ?: config('tenancy.default_timezone'));

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            error("Timezone [{$timezone}] is not a valid IANA timezone.");

            return self::FAILURE;
        }

        foreach ((array) $this->option('modules') as $module) {
            if (! $registry->exists($module)) {
                error("Module [{$module}] is not registered. Registered: ".
                    implode(', ', array_keys($registry->all()) ?: ['(none)']));

                return self::FAILURE;
            }
        }

        $tenant = Tenant::query()->create([
            'name' => trim((string) $this->argument('name')),
            'slug' => $slug,
            'timezone' => $timezone,
            'status' => TenantStatus::Active,
        ]);

        foreach ((array) $this->option('modules') as $module) {
            $flags->enable($tenant->id, $module);
        }

        $this->info("Tenant [{$tenant->name}] created. School code (login): {$tenant->slug}");

        return self::SUCCESS;
    }
}
