<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Actions\SaveDailyAttendance;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Queries\ClassChoices;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Attendance\App\Http\Concerns\ReadsAttendanceFilters;
use Modules\Attendance\App\Http\Requests\DailyAttendanceRequest;
use Modules\Core\App\Contracts\StudentDirectory;

/**
 * Input Absensi: the day's status of one class, entered by hand.
 */
final class DailyInputController
{
    use KnowsSignedInUser, ReadsAttendanceFilters;

    public function index(Request $request, ClassChoices $choices, StudentDirectory $students, SchoolClock $clock): Response
    {
        $date = $this->requestedDate($request, $clock);
        $classes = $choices->for($this->signedInUserId());
        $classId = $choices->pick($classes, $this->requestedClass($request));

        $members = $classId === null ? [] : $students->ofClass($classId);

        $records = DailyAttendance::query()
            ->where('date', $date)
            ->whereIn('student_id', array_column($members, 'id'))
            ->get()
            ->keyBy('student_id');

        return Inertia::render('Attendance/Input', [
            'date' => $this->dateProp($date, $clock),
            'today' => $clock->today(),
            'classes' => $classes,
            'classId' => $classId === null ? '' : (string) $classId,
            'students' => array_map(function ($student) use ($records, $clock): array {
                /** @var DailyAttendance|null $record */
                $record = $records->get($student->id);

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'nis' => $student->nis,
                    'status' => $record?->status->value,
                    'note' => $record?->note,
                    'checkedIn' => $clock->time($record?->checked_in_at),
                    'checkedOut' => $clock->time($record?->checked_out_at),
                ];
            }, $members),
        ]);
    }

    public function update(DailyAttendanceRequest $request, SaveDailyAttendance $save): RedirectResponse
    {
        $save->handle(
            (int) $request->validated('class_id'),
            (string) $request->validated('date'),
            $request->marks(),
            $this->signedInUserId(),
        );

        return back()->with('status', 'Absensi disimpan.');
    }
}
