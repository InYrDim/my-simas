<?php

namespace Modules\Core\App\Contracts;

/**
 * Registry of the actions other modules add to the Siswa list. Each module
 * registers its own provider from its own service provider.
 */
interface StudentActionRegistry
{
    /**
     * Idempotent per class.
     *
     * @param  class-string<StudentActionProvider>  $provider
     */
    public function register(string $module, string $provider): void;
}
