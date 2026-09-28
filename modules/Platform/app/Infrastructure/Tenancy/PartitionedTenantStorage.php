<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Illuminate\Contracts\Filesystem\Filesystem;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantStorage;

/**
 * Default TenantStorage: tenants/{tenant_id}/{module}/{relative} on the
 * private disk; central code writes to central/{module}/. The context
 * is consulted on every call so queued/CLI code automatically lands in
 * the right partition.
 */
final class PartitionedTenantStorage implements TenantStorage
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function path(string $module, string $relative = ''): string
    {
        $tenantId = $this->context->id();

        $root = $tenantId === null
            ? 'central'
            : 'tenants/'.$tenantId;

        // Resolve "." and ".." segments inside the partition: popping
        // never goes above {root}/{module}/, so a hostile relative path
        // cannot climb out of the tenant's directory (or into central/).
        $segments = [];

        foreach (explode('/', $module.'/'.$relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return trim($root.'/'.implode('/', $segments), '/');
    }

    private function disk(): Filesystem
    {
        return app('filesystem')->disk('local');
    }

    public function get(string $module, string $relative): string
    {
        return (string) $this->disk()->get($this->path($module, $relative));
    }

    public function put(string $module, string $relative, string $contents): bool
    {
        $disk = $this->disk();

        return (bool) $disk->put($this->path($module, $relative), $contents);
    }

    public function delete(string $module, string $relative): bool
    {
        return (bool) $this->disk()->delete($this->path($module, $relative));
    }

    public function exists(string $module, string $relative): bool
    {
        return $this->disk()->exists($this->path($module, $relative));
    }
}
