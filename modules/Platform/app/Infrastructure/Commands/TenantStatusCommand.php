<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Illuminate\Console\Command;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Infrastructure\Tenancy\TenantHydrator;

abstract class TenantStatusCommand extends Command
{
    protected $signature = 'tenant:status-base {tenant : Tenant slug}';

    /**
     * The target status for the concrete command.
     */
    abstract protected function targetStatus(): TenantStatus;

    /**
     * Verb used in output messages.
     */
    abstract protected function verb(): string;

    public function handle(): int
    {
        $slug = mb_strtolower(trim((string) $this->argument('tenant')));

        /** @var Tenant|null $tenant */
        $tenant = Tenant::withTrashed()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->error("Tenant [{$slug}] not found.");

            return self::FAILURE;
        }

        if ($tenant->status === $this->targetStatus()) {
            $this->warn("Tenant [{$slug}] is already {$this->targetStatus()->value}.");

            return self::SUCCESS;
        }

        $tenant->status = $this->targetStatus();
        $tenant->save();

        // Flag lookups may be cached per tenant; status changes do not
        // affect module flags, but the hydrator cache holds the old DTO.
        TenantHydrator::flush($tenant->id);

        $this->info("Tenant [{$slug}] {$this->verb()}.");

        return self::SUCCESS;
    }
}
