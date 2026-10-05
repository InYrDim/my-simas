<?php

namespace Modules\Attendance\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * "Saya" › Absensi Saya: kept on the teacher's sidebar while the pages
 * of the old attendance workspace are rebuilt; shows only "Segera
 * hadir".
 */
final class SoonController
{
    public function __invoke(): Response
    {
        return Inertia::render('Attendance/Soon');
    }
}
