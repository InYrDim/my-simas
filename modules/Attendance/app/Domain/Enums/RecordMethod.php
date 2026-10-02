<?php

namespace Modules\Attendance\App\Domain\Enums;

/**
 * How a record came to be: the student's QR was scanned, or someone
 * entered it by hand.
 */
enum RecordMethod: string
{
    case Qr = 'qr';
    case Manual = 'manual';
}
