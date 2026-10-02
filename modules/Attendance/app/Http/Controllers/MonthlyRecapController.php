<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Queries\ClassChoices;
use Modules\Attendance\App\Domain\Queries\MonthlyRecap;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Attendance\App\Http\Concerns\ReadsAttendanceFilters;

/**
 * Rekap Bulanan: each student of one class over one month.
 */
final class MonthlyRecapController
{
    use KnowsSignedInUser, ReadsAttendanceFilters;

    public function __invoke(Request $request, ClassChoices $choices, MonthlyRecap $recap, SchoolClock $clock): Response
    {
        $month = $this->requestedMonth($request, $clock);
        $classes = $choices->for($this->signedInUserId());
        $classId = $choices->pick($classes, $this->requestedClass($request));

        return Inertia::render('Attendance/Monthly', [
            'month' => [
                'iso' => $month,
                'label' => $clock->monthLabel($month),
            ],
            'currentMonth' => substr($clock->today(), 0, 7),
            'classes' => $classes,
            'classId' => $classId === null ? '' : (string) $classId,
            'rows' => $classId === null ? [] : $recap->forClass($classId, $month),
        ]);
    }
}
