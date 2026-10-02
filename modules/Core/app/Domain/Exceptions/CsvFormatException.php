<?php

namespace Modules\Core\App\Domain\Exceptions;

use RuntimeException;

/**
 * An uploaded CSV that cannot be read as a whole (empty, missing columns,
 * too long). The message is shown to the user next to the file field.
 */
final class CsvFormatException extends RuntimeException {}
