<?php

namespace Modules\Platform\App\Contracts\Exceptions;

use Modules\Platform\App\Domain\Exceptions\PlatformException;

/**
 * Thrown when a module key that was never registered is enabled,
 * disabled, or flagged. Failing loudly here keeps typo'd module keys
 * from silently enabling nothing.
 */
final class UnknownModuleException extends PlatformException {}
