<?php

namespace Modules\Attendance\Tests\Feature;

use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\Database\Seeders\AttendanceDemoSeeder;

require_once __DIR__.'/Support/helpers.php';

it('fills a school that uses Absensi with past school days, once', function () {
    // Friday 2 October 2026 at the school.
    $this->travelTo('2026-10-02 01:00:00');

    $tenant = attendanceTenant(slug: 'demo-absensi');
    $class = attendanceClass($tenant);
    attendanceStudent($tenant, $class, 'Adit');
    attendanceStudent($tenant, $class, 'Bima');

    $without = attendanceTenant(enabled: false, slug: 'demo-tanpa-absensi');
    attendanceStudent($without, attendanceClass($without), 'Citra');

    $this->seed(AttendanceDemoSeeder::class);
    $this->seed(AttendanceDemoSeeder::class);

    $rows = attendanceSchool($tenant, fn () => DailyAttendance::query()->get());

    // 20 school days for each of the two students, none today or on a Sunday.
    expect($rows)->toHaveCount(40)
        ->and($rows->max('date'))->toBe('2026-10-01')
        ->and($rows->pluck('date')->contains('2026-09-27'))->toBeFalse()
        ->and($rows->every(fn (DailyAttendance $row): bool => $row->class_id === $class->id))->toBeTrue()
        ->and($rows->every(fn (DailyAttendance $row): bool => $row->status->countsAsPresent() === ($row->checked_in_at !== null)))->toBeTrue()
        ->and(attendanceSchool($without, fn () => DailyAttendance::query()->count()))->toBe(0);
});
