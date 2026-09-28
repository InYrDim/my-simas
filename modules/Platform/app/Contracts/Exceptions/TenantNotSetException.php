<?php

namespace Modules\Platform\App\Contracts\Exceptions;

use Modules\Platform\App\Domain\Exceptions\PlatformException;

/**
 * Thrown when code requires tenant context but none is set (central
 * request, queue job without tenant, CLI without tenant:run, ...).
 *
 * Inherits through Platform's internal base rather than extending
 * \RuntimeException directly, per the PlatformPublic layer rules.
 */
final class TenantNotSetException extends PlatformException {}
