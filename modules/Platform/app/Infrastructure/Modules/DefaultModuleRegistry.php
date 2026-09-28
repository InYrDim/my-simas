<?php

namespace Modules\Platform\App\Infrastructure\Modules;

use Modules\Platform\App\Contracts\ModuleRegistry;

/**
 * In-memory registry of module keys, populated by each module's service
 * provider at boot. Per-process state — the per-tenant flags live in
 * the tenant_modules table, this only knows WHAT can be flagged.
 */
final class DefaultModuleRegistry implements ModuleRegistry
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $modules = [];

    /**
     * @var array<string, true>
     */
    private array $alwaysActive = [];

    public function register(string $key, array $meta = []): void
    {
        // Idempotent: providers may boot more than once per process
        // (Octane, re-registration in tests). First registration wins.
        $this->modules[$key] ??= $meta;
    }

    public function exists(string $key): bool
    {
        return isset($this->modules[$key]);
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function markAlwaysActive(string $key): void
    {
        $this->alwaysActive[$key] = true;
    }

    public function isAlwaysActive(string $key): bool
    {
        return isset($this->alwaysActive[$key]);
    }
}
