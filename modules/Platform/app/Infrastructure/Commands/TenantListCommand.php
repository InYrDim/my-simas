<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Illuminate\Console\Command;
use Modules\Platform\App\Domain\Models\Tenant;

class TenantListCommand extends Command
{
    protected $signature = 'tenant:list';

    protected $description = 'List all tenants (including soft-deleted)';

    public function handle(): int
    {
        $tenants = Tenant::withTrashed()
            ->orderBy('slug')
            ->get(['id', 'name', 'slug', 'timezone', 'status', 'deleted_at']);

        if ($tenants->isEmpty()) {
            $this->info('No tenants yet. Create one with tenant:create.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Timezone', 'Status', 'Deleted'],
            $tenants->map(fn (Tenant $t): array => [
                $t->id,
                $t->name,
                $t->slug,
                $t->timezone,
                $t->status->value,
                $t->deleted_at?->format('Y-m-d') ?? '-',
            ]),
        );

        return self::SUCCESS;
    }
}
