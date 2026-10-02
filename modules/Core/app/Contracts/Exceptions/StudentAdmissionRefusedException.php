<?php

namespace Modules\Core\App\Contracts\Exceptions;

use RuntimeException;

/**
 * Core refused to admit a student because a number is already in use in
 * the school. `$column` names the field the caller should show the
 * message on (`nis` or `nisn`).
 */
final class StudentAdmissionRefusedException extends RuntimeException
{
    public function __construct(
        public readonly string $column,
        string $message,
    ) {
        parent::__construct($message);
    }
}
