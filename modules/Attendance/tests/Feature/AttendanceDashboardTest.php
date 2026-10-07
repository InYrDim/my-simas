<?php

namespace Modules\Attendance\Tests\Feature;

use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\DailyAttendance;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Attendance on the Beranda: each role gets its own blocks, through
 * Core's DashboardRegistry, and only while the module is on. Friday
 * 2026-10-02, 07:30 at the school (07:15–08:00 is the Friday slot).
 */
beforeEach(fn () => $this->travelTo('2026-10-02 00:30:00'));

/** The deferred widgets of the Beranda, as the page would receive them. */
function berandaWidgets($tenant, callable $assert): void
{
    get(school($tenant->slug, '/beranda'))->assertOk()->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $assert($page)));
}

it('shows the office the day of the whole school and the classes still waiting', function () {
    $tenant = attendanceTenant(slug: 'dash-kantor');
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    attendanceStudent($tenant, $class, 'Budi');

    attendanceSchool($tenant, fn () => DailyAttendance::factory()->status(AttendanceStatus::Late)->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02',
    ]));

    berandaWidgets($tenant, fn ($page) => $page
        ->where('widgets.figures.0.key', 'attendance.rate-today')
        ->where('widgets.figures.0.payload.value', '100%')
        ->where('widgets.figures.1.payload.value', 1)
        ->where('widgets.attention.0.key', 'attendance.classes-waiting')
        ->where('widgets.attention.0.payload.items.0.label', 'X 1')
        ->where('widgets.attention.0.payload.items.0.detail', '1 siswa belum tercatat')
        ->where('widgets.action.0.key', 'attendance.gate-action'));
});

it('tells the office when every class has sent its record', function () {
    $tenant = attendanceTenant(slug: 'dash-kantor-beres');
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');

    attendanceSchool($tenant, fn () => DailyAttendance::factory()->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02',
    ]));

    berandaWidgets($tenant, fn ($page) => $page
        ->where('widgets.attention.0.payload.items', [])
        ->where('widgets.attention.0.payload.empty', 'Semua kelas sudah mengirim absensi.'));
});

it('shows a teacher the lessons of the day and where each stands', function () {
    $tenant = attendanceTenant(role: 'guru', slug: 'dash-guru');
    attendanceOwnLesson($tenant);

    berandaWidgets($tenant, fn ($page) => $page
        ->where('widgets.main.0.key', 'attendance.lessons-today')
        ->where('widgets.main.0.payload.items.0.label', 'Matematika · X 1')
        ->where('widgets.main.0.payload.items.0.detail', 'Jam ke-1 · 07:15–08:00')
        ->where('widgets.main.0.payload.items.0.status', 'Berlangsung')
        ->missing('widgets.figures')
        ->where('widgets.action.0.key', 'attendance.class-action'));
});

it('shows a student their own day, spelled out, and their month', function () {
    $tenant = attendanceTenant(role: 'siswa', slug: 'dash-siswa');
    $class = attendanceClass($tenant);
    $me = attendanceStudent($tenant, $class, 'Aditya', ['user_id' => auth()->id()]);
    $other = attendanceStudent($tenant, $class, 'Siswa Lain');

    attendanceSchool($tenant, function () use ($me, $other, $class): void {
        DailyAttendance::factory()->checkedIn('2026-10-02 00:05:00')->create(['student_id' => $me->id, 'class_id' => $class->id, 'date' => '2026-10-02']);
        DailyAttendance::factory()->status(AttendanceStatus::Sick)->create(['student_id' => $me->id, 'class_id' => $class->id, 'date' => '2026-10-01']);
        DailyAttendance::factory()->status(AttendanceStatus::Absent)->create(['student_id' => $other->id, 'class_id' => $class->id, 'date' => '2026-10-01']);
    });

    berandaWidgets($tenant, fn ($page) => $page
        ->where('widgets.main.0.payload', ['state' => 'confirmed', 'word' => 'Hadir', 'detail' => 'Masuk 07:05'])
        ->where('widgets.main.1.payload.items', [
            ['label' => 'Hadir', 'value' => '1'],
            ['label' => 'Terlambat', 'value' => '0'],
            ['label' => 'Sakit', 'value' => '1'],
            ['label' => 'Izin', 'value' => '0'],
            ['label' => 'Alpa', 'value' => '0'],
        ])
        ->where('widgets.action.0.key', 'attendance.qr-action'));
});

it('marks a student with no record today as not yet recorded', function () {
    $tenant = attendanceTenant(role: 'siswa', slug: 'dash-siswa-kosong');
    attendanceStudent($tenant, attendanceClass($tenant), 'Aditya', ['user_id' => auth()->id()]);

    berandaWidgets($tenant, fn ($page) => $page
        ->where('widgets.main.0.payload', ['state' => 'pending', 'word' => 'Belum tercatat'])
        ->where('widgets.main.1.payload.items', []));
});

it('keeps the office blocks from a teacher and the teacher blocks from the office', function () {
    $tenant = attendanceTenant(role: 'staf-tu', slug: 'dash-staf');

    berandaWidgets($tenant, fn ($page) => $page
        ->has('widgets.figures')
        ->where('widgets.main', fn ($main) => collect($main)->pluck('key')->doesntContain('attendance.lessons-today')));
});

it('shows nothing of attendance in a school without the module', function () {
    $tenant = attendanceTenant(enabled: false, slug: 'dash-mati');

    berandaWidgets($tenant, fn ($page) => $page->where('widgets', []));
});
