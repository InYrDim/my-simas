<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\NoticeKind;

/**
 * Registry of the kinds of WhatsApp notice schools can switch on. Each
 * module registers its own kinds from its own service provider — Core
 * never imports a feature module.
 *
 * A kind is offered only while its module is active for the current
 * tenant. Every kind starts switched off: the school turns it on.
 */
interface NoticeRegistry
{
    /**
     * Register a kind for the given module key. Called from the owning
     * module's service provider during boot. Registering the same key
     * again replaces it.
     */
    public function register(string $module, NoticeKind $kind): void;
}
