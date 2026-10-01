<?php

namespace Modules\Attendance\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Infrastructure\Mock\AttendanceMockData;

/**
 * Absensi mockup pages: today's overview, per-class input, monthly recap.
 *
 * Mock phase: every page reads static sample data from AttendanceMockData
 * and nothing is persisted. The DB phase replaces the mock behind this
 * controller; the page props stay the same.
 */
final class AttendanceController
{
    public function overview(): Response
    {
        $data = new AttendanceMockData;

        return Inertia::render('Attendance/Overview', [
            'date' => $data->date(),
            'totals' => $data->totals(),
            'classes' => $data->classes(),
        ]);
    }

    public function input(): Response
    {
        $data = new AttendanceMockData;

        return Inertia::render('Attendance/Input', [
            'date' => $data->date(),
            'classes' => $data->classOptions(),
            'students' => $data->students(),
        ]);
    }

    public function monthly(): Response
    {
        $data = new AttendanceMockData;

        return Inertia::render('Attendance/Monthly', [
            'month' => $data->month(),
            'classes' => $data->classOptions(),
            'rows' => $data->monthlyRows(),
        ]);
    }
}
