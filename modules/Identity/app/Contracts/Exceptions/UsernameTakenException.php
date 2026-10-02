<?php

namespace Modules\Identity\App\Contracts\Exceptions;

use RuntimeException;

/**
 * Another account of the same school already signs in with this username
 * or email.
 */
final class UsernameTakenException extends RuntimeException {}
