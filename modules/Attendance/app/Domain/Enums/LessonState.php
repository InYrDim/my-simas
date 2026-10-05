<?php

namespace Modules\Attendance\App\Domain\Enums;

/**
 * Where one lesson stands on the school's clock: still to come, in its
 * hour, or over. Only a lesson in its own hour may be filled on Absensi
 * Kelas; a finished one is edited from Riwayat Absensi.
 */
enum LessonState: string
{
    case Upcoming = 'upcoming';
    case Running = 'running';
    case Finished = 'finished';
}
