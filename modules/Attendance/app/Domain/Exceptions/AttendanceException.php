<?php

namespace Modules\Attendance\App\Domain\Exceptions;

use RuntimeException;

/**
 * A record that cannot be made, with the reason as a sentence for the
 * person at the scanner ("Sudah tercatat masuk pukul 06.52.").
 */
final class AttendanceException extends RuntimeException {}
