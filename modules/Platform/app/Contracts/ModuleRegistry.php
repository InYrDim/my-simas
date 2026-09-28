<?php

namespace Modules\Platform\App\Contracts;

/**
 * Registry of module keys that can be enabled per tenant. Each module
 * registers its own key from its own service provider — Platform never
 * hardcodes business module names. The only Platform-owned convention
 * is that a module MAY flag itself always-active (e.g. core), which
 * skips the per-tenant flag lookup entirely.
 */
interface ModuleRegistry
{
    /**
     * Register a module key. Called from the owning module's service
     * provider during boot. Registering the same key twice is a no-op
     * (providers can be re-booted in long-running processes).
     *
     * @param  array<string, string>  $meta  Optional display metadata (e.g. ['label' => 'Core']).
     */
    public function register(string $key, array $meta = []): void;

    /**
     * Whether the module key has been registered.
     */
    public function exists(string $key): bool;

    /**
     * All registered module keys with their metadata, keyed by module key.
     *
     * @return array<string, array<string, string>>
     */
    public function all(): array;

    /**
     * Mark a module as always active: the per-tenant flag lookup is
     * skipped and module:{key} always passes for this key.
     */
    public function markAlwaysActive(string $key): void;

    /**
     * Whether the module is always active (independent of tenant flags).
     */
    public function isAlwaysActive(string $key): bool;
}
