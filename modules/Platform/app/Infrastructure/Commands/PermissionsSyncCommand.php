<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Illuminate\Console\Command;
use Modules\Platform\App\Infrastructure\Permissions\PermissionSync;

class PermissionsSyncCommand extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Create all registered permissions (idempotent, never revokes)';

    public function handle(PermissionSync $sync): int
    {
        $created = $sync->sync();

        if ($created === []) {
            $this->info('Permissions already in sync. Nothing created.');

            return self::SUCCESS;
        }

        sort($created);

        $this->info('Created '.count($created).' permission(s):');
        $this->line('  '.implode("\n  ", $created));

        return self::SUCCESS;
    }
}
