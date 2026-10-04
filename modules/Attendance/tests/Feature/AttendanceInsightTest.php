<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Student;
use Modules\Platform\App\Contracts\TenantRoles;
use Modules\Platform\App\Domain\Models\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Attendance on Statistik & Laporan: two reports and the attendance
 * figures, registered through Core's contracts.
 * Today is Friday 2 October 2026 at the school; the academic year runs
 * from 13 July 2026.
 */
beforeEach(function () {
    $this->travelTo('2026-10-02 01:00:00');
});

/**
 * A school with the active academic year 2026/2027 and one class.
 *
 * @return array{0: Tenant, 1: ClassGroup}
 */
function insightSchool(string $slug = 'laporan-absensi', string $role = 'admin-sekolah', bool $enabled = true): array
{
    $tenant = attendanceTenant($enabled, $role, $slug);

    $class = attendanceSchool($tenant, function (): ClassGroup {
        $year = AcademicYear::factory()->active()->create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-26']);

        return ClassGroup::factory()->create([
            'academic_year_id' => $year->id,
            'grade_id' => Grade::factory()->create(['name' => 'X', 'sort_order' => 1])->id,
            'name' => 'X 1',
        ]);
    });

    return [$tenant, $class];
}

/**
 * @param  array<string, AttendanceStatus>  $days
 */
function insightDays(Tenant $tenant, ClassGroup $class, Student $student, array $days): void
{
    attendanceSchool($tenant, function () use ($class, $student, $days): void {
        foreach ($days as $date => $status) {
            DailyAttendance::factory()->status($status)->create([
                'student_id' => $student->id, 'class_id' => $class->id, 'date' => $date,
            ]);
        }
    });
}

/**
 * The rows of a downloaded report, without the `sep=` line.
 *
 * @param  TestResponse<StreamedResponse>  $response
 * @return list<list<string|null>>
 */
function reportRows(TestResponse $response): array
{
    $lines = explode("\r\n", trim($response->streamedContent()));

    expect(array_shift($lines))->toBe('sep=;');

    return array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), $lines);
}

it('takes the place of the announced attendance reports', function () {
    [$tenant] = insightSchool();

    get(school($tenant->slug, '/statistik-laporan/laporan'))->assertInertia(fn (Assert $page) => $page
        ->where('groups', fn ($groups) => collect($groups)->firstWhere('title', 'Kehadiran')['reports'] === [
            ['key' => 'attendance-monthly', 'name' => 'Rekap Kehadiran Bulanan', 'description' => 'Hadir, terlambat, sakit, izin, dan alpa setiap siswa per bulan.', 'available' => true],
            ['key' => 'attendance-class', 'name' => 'Kehadiran per Kelas', 'description' => 'Persentase kehadiran tiap kelas dalam satu tahun ajaran.', 'available' => true],
        ])
    );
});

it('downloads each students months of the academic year', function () {
    [$tenant, $class] = insightSchool();
    $adit = attendanceStudent($tenant, $class, 'Adit', ['nis' => '5001']);
    $bima = attendanceStudent($tenant, $class, 'Bima', ['nis' => '5002']);

    insightDays($tenant, $class, $adit, [
        '2026-09-01' => AttendanceStatus::Present,
        '2026-09-02' => AttendanceStatus::Late,
        '2026-09-03' => AttendanceStatus::Absent,
        '2026-10-01' => AttendanceStatus::Sick,
        // Before the academic year: not in this report.
        '2026-07-01' => AttendanceStatus::Absent,
    ]);
    insightDays($tenant, $class, $bima, ['2026-09-01' => AttendanceStatus::Permit]);

    $response = get(school($tenant->slug, '/statistik-laporan/laporan/attendance-monthly/unduh'))
        ->assertOk()
        ->assertDownload('attendance-monthly-2026-2027.csv');

    expect(reportRows($response))->toBe([
        ['Bulan', 'Kelas', 'NIS', 'Nama', 'Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa', 'Kehadiran (%)'],
        ['September 2026', 'X 1', '5001', 'Adit', '1', '1', '0', '0', '1', '67'],
        ['September 2026', 'X 1', '5002', 'Bima', '0', '0', '0', '1', '0', '0'],
        ['Oktober 2026', 'X 1', '5001', 'Adit', '0', '0', '1', '0', '0', '0'],
    ]);
});

it('downloads the attendance of every class of the year', function () {
    [$tenant, $class] = insightSchool();
    $quiet = attendanceClass($tenant, 'X 2');
    $adit = attendanceStudent($tenant, $class, 'Adit');

    insightDays($tenant, $class, $adit, [
        '2026-09-01' => AttendanceStatus::Present,
        '2026-09-02' => AttendanceStatus::Present,
        '2026-09-03' => AttendanceStatus::Late,
        '2026-09-04' => AttendanceStatus::Absent,
    ]);

    $response = get(school($tenant->slug, '/statistik-laporan/laporan/attendance-class/unduh'))->assertOk();

    expect($quiet->name)->toBe('X 2')
        ->and(reportRows($response))->toBe([
            ['Kelas', 'Wali Kelas', 'Catatan', 'Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa', 'Kehadiran (%)'],
            ['X 1', '', '4', '2', '1', '0', '0', '1', '75'],
            ['X 2', '', '0', '0', '0', '0', '0', '0', ''],
        ]);
});

it('prints a report too', function () {
    [$tenant, $class] = insightSchool();
    insightDays($tenant, $class, attendanceStudent($tenant, $class, 'Adit', ['nis' => '5001']), ['2026-09-01' => AttendanceStatus::Present]);

    get(school($tenant->slug, '/statistik-laporan/laporan/attendance-monthly/cetak'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Insight/ReportPrint')
        ->where('title', 'Rekap Kehadiran Bulanan')
        ->where('rows', [['September 2026', 'X 1', '5001', 'Adit', 1, 0, 0, 0, 0, 100]])
    );
});

it('shows the attendance rate of the year and the last six months', function () {
    [$tenant, $class] = insightSchool();
    $adit = attendanceStudent($tenant, $class, 'Adit');

    insightDays($tenant, $class, $adit, [
        '2026-08-03' => AttendanceStatus::Present,
        '2026-08-04' => AttendanceStatus::Absent,
        '2026-09-01' => AttendanceStatus::Late,
        '2026-10-01' => AttendanceStatus::Present,
        // Before the academic year: in the trend, not in the year's rate.
        '2026-06-01' => AttendanceStatus::Absent,
    ]);

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('figures', fn ($figures) => collect($figures)->firstWhere('key', 'attendance-rate') === [
            'key' => 'attendance-rate', 'label' => 'Rata-rata kehadiran', 'value' => '75%', 'hint' => '2026/2027', 'available' => true,
        ])
        ->where('panels', function ($panels) {
            $trend = collect($panels)->firstWhere('key', 'attendance-trend');

            return $trend['available'] === true
                && $trend['kind'] === 'bars'
                && $trend['points'] === [
                    ['label' => 'Mei', 'value' => 0],
                    ['label' => 'Jun', 'value' => 0],
                    ['label' => 'Jul', 'value' => 0],
                    ['label' => 'Agt', 'value' => 50],
                    ['label' => 'Sep', 'value' => 100],
                    ['label' => 'Okt', 'value' => 100],
                ];
        })
    );
});

it('shows a dash while nothing is recorded', function () {
    [$tenant] = insightSchool();

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('figures', fn ($figures) => collect($figures)->firstWhere('key', 'attendance-rate')['value'] === '—'
            && collect($figures)->firstWhere('key', 'attendance-rate')['available'] === true)
    );
});

it('keeps the reports for holders of attendance.view', function () {
    [$tenant] = insightSchool('laporan-absensi-izin');
    attendanceMember($tenant, 'staf-tu');

    get(school($tenant->slug, '/statistik-laporan/laporan/attendance-monthly/unduh'))->assertOk();

    // A role that may open the catalogue but not attendance.
    attendanceSchool($tenant, fn () => app(TenantRoles::class)->ensure($tenant->id, 'pustakawan', ['core.master.view']));
    attendanceMember($tenant, 'pustakawan');

    get(school($tenant->slug, '/statistik-laporan/laporan/attendance-monthly/unduh'))->assertForbidden();
    get(school($tenant->slug, '/statistik-laporan/laporan'))->assertInertia(fn (Assert $page) => $page
        ->where('groups', fn ($groups) => collect($groups)->firstWhere('title', 'Kehadiran') === null)
    );
});

it('offers neither reports nor figures to a school without the module', function () {
    [$tenant] = insightSchool('laporan-absensi-mati', enabled: false);

    get(school($tenant->slug, '/statistik-laporan/laporan/attendance-monthly/unduh'))->assertNotFound();
    get(school($tenant->slug, '/statistik-laporan/laporan'))->assertInertia(fn (Assert $page) => $page
        ->where('groups', fn ($groups) => collect($groups)->firstWhere('title', 'Kehadiran') === null)
    );
    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('figures', fn ($figures) => collect($figures)->firstWhere('key', 'attendance-rate')['available'] === false)
    );
});

it('never reports another school', function () {
    [$other, $otherClass] = insightSchool('laporan-absensi-lain');
    insightDays($other, $otherClass, attendanceStudent($other, $otherClass, 'Asing'), ['2026-09-01' => AttendanceStatus::Absent]);

    [$tenant] = insightSchool('laporan-absensi-sendiri');

    expect(reportRows(get(school($tenant->slug, '/statistik-laporan/laporan/attendance-monthly/unduh'))))->toHaveCount(1)
        ->and(reportRows(get(school($tenant->slug, '/statistik-laporan/laporan/attendance-class/unduh')))[1])->toBe(['X 1', '', '0', '0', '0', '0', '0', '0', '']);

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('figures', fn ($figures) => collect($figures)->firstWhere('key', 'attendance-rate')['value'] === '—')
    );
});
