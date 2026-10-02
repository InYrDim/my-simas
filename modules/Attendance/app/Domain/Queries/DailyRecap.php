<?php

namespace Modules\Attendance\App\Domain\Queries;

use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Core\App\Contracts\ClassDirectory;

/**
 * One day of the whole school: per class of the active academic year, how
 * many students have which status and how many have no record yet.
 */
final class DailyRecap
{
    public function __construct(
        private readonly ClassDirectory $classes,
    ) {}

    /**
     * @param  string  $date  Y-m-d
     * @return array{
     *     totals: array{present: int, late: int, sick: int, permit: int, absent: int, pending: int, total: int},
     *     classes: list<array{id: int, name: string, homeroom: ?string, present: int, late: int, sick: int, permit: int, absent: int, pending: int, total: int, submitted: bool}>
     * }
     */
    public function forDate(string $date): array
    {
        $classes = $this->classes->ofActiveYear();

        /** @var array<int, AttendanceTally> $tallies */
        $tallies = [];

        DailyAttendance::query()
            ->where('date', $date)
            ->whereIn('class_id', array_column($classes, 'id'))
            ->get(['class_id', 'status'])
            ->each(function (DailyAttendance $row) use (&$tallies): void {
                ($tallies[$row->class_id] ??= new AttendanceTally)->add($row->status);
            });

        $whole = new AttendanceTally;
        $rows = [];
        $pending = 0;
        $students = 0;

        foreach ($classes as $class) {
            $tally = $tallies[$class->id] ?? new AttendanceTally;
            $waiting = max(0, $class->studentCount - $tally->total());

            $whole->merge($tally);
            $pending += $waiting;
            $students += $class->studentCount;

            $rows[] = [
                'id' => $class->id,
                'name' => $class->name,
                'homeroom' => $class->homeroomName,
                ...$tally->toArray(),
                'pending' => $waiting,
                'total' => $class->studentCount,
                'submitted' => $class->studentCount > 0 && $waiting === 0,
            ];
        }

        return [
            'totals' => [...$whole->toArray(), 'pending' => $pending, 'total' => $students],
            'classes' => $rows,
        ];
    }
}
