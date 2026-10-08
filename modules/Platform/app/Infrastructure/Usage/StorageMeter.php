<?php

namespace Modules\Platform\App\Infrastructure\Usage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Modules\Platform\App\Contracts\TenantCache;
use Modules\Platform\App\Contracts\TenantStorage;

/**
 * Bytes of every file under the current school's directory on the private
 * disk (the database is not counted). Cached for 15 minutes.
 */
final class StorageMeter
{
    public const CACHE_KEY = 'usage.storage_bytes';

    public const CACHE_MINUTES = 15;

    private const BYTES_PER_MB = 1048576;

    public function __construct(
        private readonly TenantStorage $storage,
        private readonly TenantCache $cache,
    ) {}

    public function bytes(): int
    {
        $cached = $this->cache->get(self::CACHE_KEY);

        if (is_int($cached)) {
            return $cached;
        }

        /** @var Filesystem $disk */
        $disk = app('filesystem')->disk('local');
        $bytes = 0;

        foreach ($disk->allFiles($this->storage->path('')) as $file) {
            $bytes += $disk->size($file);
        }

        $this->cache->put(self::CACHE_KEY, $bytes, now()->addMinutes(self::CACHE_MINUTES));

        return $bytes;
    }

    /**
     * Whole megabytes, rounded up, to match the `storage_mb` plan limit.
     */
    public function megabytes(): int
    {
        return (int) ceil($this->bytes() / self::BYTES_PER_MB);
    }
}
