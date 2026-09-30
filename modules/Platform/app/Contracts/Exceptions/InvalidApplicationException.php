<?php

namespace Modules\Platform\App\Contracts\Exceptions;

/**
 * An application payload violates the domain rules (reserved/taken/
 * duplicated slug, duplicate pending applicant email, invalid timezone,
 * missing required fields). Message lists every violation found.
 */
final class InvalidApplicationException extends \InvalidArgumentException {}
