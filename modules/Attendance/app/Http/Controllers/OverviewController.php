<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Queries\DailyRecap;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Attendance\App\Http\Concerns\ReadsAttendanceFilters;

/**
 * Rekap Hari Ini: who is at school on one day and which classes still
 * have students without a record.
 */
final class OverviewController
{
    use ReadsAttendanceFilters;

    public function __invoke(Request $request, DailyRecap $recap, SchoolClock $clock): Response
    {
        $date = $this->requestedDate($request, $clock);

        return Inertia::render('Attendance/Overview', [
            'date' => $this->dateProp($date, $clock),
            'today' => $clock->today(),
            ...$recap->forDate($date),
            'can' => ['record' => Gate::allows('attendance.daily.record')],
        ]);
    }
}
