<?php

namespace Modules\Platform\App\Domain\Exceptions;

/**
 * Internal base for Platform's public exceptions. Contracts classes may
 * not depend on arbitrary vendor symbols (PlatformPublic layer rules),
 * so public exceptions inherit through this Platform-internal base
 * instead of extending \RuntimeException directly.
 */
class PlatformException extends \RuntimeException {}
