<?php

namespace Modules\Identity\App\Contracts\Exceptions;

use RuntimeException;

/**
 * The account cannot take this action: it does not exist in this school,
 * its role is unknown, or it is the school's last active admin.
 */
final class AccountActionRefusedException extends RuntimeException {}
