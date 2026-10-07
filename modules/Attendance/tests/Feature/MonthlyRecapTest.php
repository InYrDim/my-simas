<?php

namespace Modules\Attendance\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Models\LessonAttendance;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Rekap Bulanan: each student of a class over one month.
 * Today is Friday 2 October 2026 at the school.
 */
beforeEach(function () {
    $this->travelTo('2026-10-02 01:00:00');
});

/**
 * Daily records of one student: status per `Y-m-d` day.
 *
 * @param  array<string, AttendanceStatus>  $days
 */
function recordDays(Tenant $tenant, ClassGroup $class, Student $student, array $days): void
{
    attendanceSchool($tenant, function () use ($class, $student, $days): void {
        foreach ($days as $date => $status) {
            DailyAttendance::factory()->status($status)->create([
                'student_id' => $student->id, 'class_id' => $class->id, 'date' => $date,
            ]);
        }
    });
}

it('counts each students month and the share at school', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    $adit = attendanceStudent($tenant, $class, 'Adit', ['nis' => '5001']);
    $bima = attendanceStudent($tenant, $class, 'Bima', ['nis' => '5002']);
    attendanceStudent($tenant, $class, 'Citra', ['nis' => '5003']);

    recordDays($tenant, $class, $adit, [
        '2026-09-01' => AttendanceStatus::Present,
        '2026-09-02' => AttendanceStatus::Late,
        '2026-09-03' => AttendanceStatus::Sick,
        '2026-09-30' => AttendanceStatus::Absent,
        // Other months stay out.
        '2026-08-31' => AttendanceStatus::Absent,
        '2026-10-01' => AttendanceStatus::Absent,
    ]);
    recordDays($tenant, $class, $bima, ['2026-09-10' => AttendanceStatus::Permit]);

    get(school($tenant->slug, "/absensi/rekap?kelas={$class->id}&bulan=2026-09"))->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/Monthly')
        ->where('month', ['iso' => '2026-09', 'label' => 'September 2026'])
        ->where('currentMonth', '2026-10')
        ->where('classId', (string) $class->id)
        ->where('rows.0', ['id' => $adit->id, 'name' => 'Adit', 'nis' => '5001', 'present' => 1, 'late' => 1, 'sick' => 1, 'permit' => 0, 'absent' => 1, 'days' => 4, 'percent' => 50])
        ->where('rows.1.permit', 1)
        ->where('rows.1.percent', 0)
        // Nothing recorded: no rate rather than 0%.
        ->where('rows.2.name', 'Citra')
        ->where('rows.2.days', 0)
        ->where('rows.2.percent', null)
    );
});

it('opens on the current month and the first class', function () {
    $tenant = attendanceTenant();
    attendanceClass($tenant, 'X 2');
    $first = attendanceClass($tenant, 'X 1');

    get(school($tenant->slug, '/absensi/rekap'))->assertInertia(fn (Assert $page) => $page
        ->where('month.iso', '2026-10')
        ->where('classId', (string) $first->id)
        ->where('rows', [])
    );
});

it('falls back to the current month for a month that is not a real past month', function (string $query) {
    $tenant = attendanceTenant();
    attendanceClass($tenant);

    get(school($tenant->slug, "/absensi/rekap?bulan={$query}"))->assertInertia(fn (Assert $page) => $page
        ->where('month.iso', '2026-10')
    );
})->with(['2026-11', '2026-13', 'september']);

it('opens for a school without classes', function () {
    $tenant = attendanceTenant();

    get(school($tenant->slug, '/absensi/rekap'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('classes', [])
        ->where('classId', '')
        ->where('rows', [])
    );
});

it('never counts another school', function () {
    $other = attendanceTenant(slug: 'bulanan-lain');
    $otherClass = attendanceClass($other);
    $foreign = attendanceStudent($other, $otherClass, 'Asing');
    recordDays($other, $otherClass, $foreign, ['2026-10-01' => AttendanceStatus::Absent]);

    $tenant = attendanceTenant(slug: 'bulanan-sendiri');
    $class = attendanceClass($tenant);
    attendanceStudent($tenant, $class, 'Adit');

    // Asking for the other school's class opens this school's own.
    get(school($tenant->slug, "/absensi/rekap?kelas={$otherClass->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('classId', (string) $class->id)
        ->has('rows', 1)
        ->where('rows.0.name', 'Adit')
        ->where('rows.0.absent', 0)
    );
});

it('counts lesson records per tab: lessons only, gate only, or both', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    $adit = attendanceStudent($tenant, $class, 'Adit', ['nis' => '5001']);

    attendanceSchool($tenant, function () use ($class, $adit): void {
        foreach (['2026-09-01' => AttendanceStatus::Present, '2026-10-01' => AttendanceStatus::Absent] as $date => $status) {
            $session = LessonSession::factory()->create(['class_id' => $class->id, 'period_slot_id' => 1, 'date' => $date]);
            LessonAttendance::query()->create([
                'lesson_session_id' => $session->id, 'student_id' => $adit->id,
                'status' => $status, 'method' => RecordMethod::Manual,
            ]);
        }
    });
    recordDays($tenant, $class, $adit, ['2026-09-02' => AttendanceStatus::Absent]);

    get(school($tenant->slug, "/absensi/rekap?kelas={$class->id}&bulan=2026-09&jenis=pelajaran"))->assertInertia(fn (Assert $page) => $page
        ->where('source', 'pelajaran')
        ->where('rows.0.present', 1)
        ->where('rows.0.absent', 0)
        ->where('rows.0.percent', 100)
    );

    get(school($tenant->slug, "/absensi/rekap?kelas={$class->id}&bulan=2026-09&jenis=gerbang"))->assertInertia(fn (Assert $page) => $page
        ->where('source', 'gerbang')
        ->where('rows.0.present', 0)
        ->where('rows.0.absent', 1)
    );

    // No tab chosen: gate and lesson records added together.
    get(school($tenant->slug, "/absensi/rekap?kelas={$class->id}&bulan=2026-09"))->assertInertia(fn (Assert $page) => $page
        ->where('source', 'semua')
        ->where('rows.0.present', 1)
        ->where('rows.0.absent', 1)
        ->where('rows.0.days', 2)
        ->where('rows.0.percent', 50)
    );
});
