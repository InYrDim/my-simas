<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CachePruneExpiredCommand extends Command
{
    protected $signature = 'cache:prune-expired';

    protected $description = 'Delete expired rows from the database cache table (a database store only removes a row when its key is read again)';

    public function handle(): int
    {
        if (config('cache.default') !== 'database') {
            $this->info('The cache store is not "database". Nothing to prune.');

            return self::SUCCESS;
        }

        $store = (array) config('cache.stores.database');

        $deleted = DB::connection($store['connection'] ?? null)
            ->table((string) ($store['table'] ?? 'cache'))
            ->where('expiration', '<=', now()->getTimestamp())
            ->delete();

        $this->info("Deleted {$deleted} expired cache row(s).");

        return self::SUCCESS;
    }
}
