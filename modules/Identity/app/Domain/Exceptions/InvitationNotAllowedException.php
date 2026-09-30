<?php

namespace Modules\Identity\App\Domain\Exceptions;

/**
 * Domain rule violation while inviting: the target email belongs to an
 * ACTIVE account (has a password). Re-inviting an active user would
 * let an admin trigger a password-mail — send-reset is the only
 * password-mail path for active accounts.
 */
final class InvitationNotAllowedException extends \RuntimeException {}
