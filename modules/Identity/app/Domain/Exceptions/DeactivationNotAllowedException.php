<?php

namespace Modules\Identity\App\Domain\Exceptions;

/**
 * Domain rule violation while deactivating a user (self-deactivation
 * or removing the tenant's last active school admin).
 */
final class DeactivationNotAllowedException extends \RuntimeException {}
