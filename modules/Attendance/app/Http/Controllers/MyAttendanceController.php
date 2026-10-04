<?php

namespace Modules\Attendance\App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Attendance\App\Http\Concerns\ReadsAttendanceFilters;
use Modules\Core\App\Contracts\StudentDirectory;

/**
 * Absensi Saya: a student's own daily attendance over one month. Nothing
 * here takes a student id: the student is the one behind the signed-in
 * account, so no one can read another student's record.
 */
final class MyAttendanceController
{
    use ReadsAttendanceFilters;

    public function __invoke(Request $request, StudentDirectory $students, SchoolClock $clock): Response
    {
        $student = $students->findByUserId((int) Auth::id());

        abort_if($student === null, 403);

        $month = $this->requestedMonth($request, $clock);
        $current = substr($clock->today(), 0, 7);

        $records = DailyAttendance::query()
            ->where('student_id', $student->id)
            ->where('date', '>=', "{$month}-01")
            ->where('date', '<=', "{$month}-31")
            ->orderByDesc('date')
            ->get();

        $totals = array_fill_keys(AttendanceStatus::values(), 0);

        foreach ($records as $record) {
            $totals[$record->status->value]++;
        }

        $first = CarbonImmutable::parse("{$month}-01");

        return Inertia::render('Attendance/MyAttendance', [
            'student' => ['name' => $student->name, 'nis' => $student->nis, 'class' => $student->className],
            'month' => [
                'iso' => $month,
                'label' => $clock->monthLabel($month),
                'previous' => $first->subMonth()->format('Y-m'),
                'next' => $month < $current ? $first->addMonth()->format('Y-m') : null,
            ],
            'totals' => array_map(
                fn (AttendanceStatus $status): array => ['status' => $status->value, 'label' => $status->label(), 'count' => $totals[$status->value]],
                AttendanceStatus::cases(),
            ),
            'days' => $records->map(fn (DailyAttendance $record): array => [
                'date' => $record->date,
                'label' => $clock->dateLabel($record->date),
                'status' => $record->status->value,
                'statusLabel' => $record->status->label(),
                'checkedIn' => $clock->time($record->checked_in_at),
                'checkedOut' => $clock->time($record->checked_out_at),
                'note' => $record->note,
            ])->values()->all(),
        ]);
    }
}
