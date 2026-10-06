<?php

use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Models\LessonAttendance;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Attendance\App\Domain\Qr\QrTokens;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Models\TimetableEntry;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

require_once __DIR__.'/Support/school.php';

/*
 * Absensi (Fase 10) in a real browser: the roll call form, the scanner
 * page fed through its code field (the camera is not driven here) and a
 * teacher's lesson page. The rules behind them — late cut-off, one-time
 * codes, permissions, tenant isolation, notices — are covered by the
 * Attendance feature tests; a student's own QR page on the real server by
 * tests/E2E/student-qr.spec.ts.
 */

/**
 * A school that uses Absensi with one signed-in member, like
 * schoolMemberSignsIn() but with the module switched on before the first
 * page (the module flag is cached per school).
 *
 * @return array{0: mixed, 1: Tenant, 2: User}
 */
function attendanceMemberSignsIn(string $role = 'admin-sekolah'): array
{
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-absensi']);
    $user = UserFactory::new()->forTenant($tenant->id)->create(['email' => "{$role}@sekolah-absensi.test"]);

    inTenant($tenant, function () use ($tenant, $user, $role): void {
        app(ModuleFlagManager::class)->enable($tenant->id, 'attendance');
        $user->assignTenantRole($role);
    });

    onCentralHost();

    $page = visit('/login');

    $page->fill('school', $tenant->id)
        ->fill('login', "{$role}@sekolah-absensi.test")
        ->fill('password', 'password')
        ->press('Masuk')
        ->assertPathIs('/beranda');

    return [$page, $tenant, $user];
}

/**
 * Two classes of the active year; the second one holds the named students.
 *
 * @param  list<string>  $names
 * @return array{0: ClassGroup, 1: ClassGroup, 2: array<string, Student>}
 */
function attendanceClasses(Tenant $tenant, array $names): array
{
    return inTenant($tenant, function () use ($names): array {
        $year = AcademicYear::factory()->active()->create();
        $grade = Grade::factory()->create(['name' => 'X', 'sort_order' => 1]);
        $first = ClassGroup::factory()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'X 1']);
        $second = ClassGroup::factory()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'X 2']);

        $students = [];

        foreach ($names as $name) {
            $students[$name] = Student::factory()->create(['name' => $name, 'class_id' => $second->id]);
        }

        return [$first, $second, $students];
    });
}

it('lets an admin take the roll call of a class and see it in the recap', function () {
    [$page, $tenant] = attendanceMemberSignsIn();
    [, $class, $students] = attendanceClasses($tenant, ['Aditya Pratama', 'Bima Sakti']);

    $page->navigate("/absensi/input?kelas={$class->id}")
        ->assertDontSee('Tampilan contoh')
        ->assertSee('Aditya Pratama')
        ->assertSee('2 siswa belum diabsen')
        ->click('internal:role=group[name="Status Bima Sakti"i] >> internal:role=button[name="Sakit"i]')
        ->fill('input[aria-label="Keterangan Bima Sakti"]', 'Demam')
        ->press('Simpan absensi')
        ->assertSee('Absensi disimpan.')
        ->assertNoJavaScriptErrors();

    $rows = inTenant($tenant, fn () => DailyAttendance::query()->get()->keyBy('student_id'));

    expect($rows)->toHaveCount(2)
        ->and($rows[$students['Aditya Pratama']->id]->status)->toBe(AttendanceStatus::Present)
        ->and($rows[$students['Bima Sakti']->id]->status)->toBe(AttendanceStatus::Sick)
        ->and($rows[$students['Bima Sakti']->id]->note)->toBe('Demam');

    $page->navigate('/absensi')
        ->assertSee('Rekap Hari Ini')
        ->assertSee('Sudah diabsen')
        ->assertSee('dari 2 siswa')
        ->assertNoJavaScriptErrors();

    $page->navigate("/absensi/rekap?kelas={$class->id}")
        ->assertSee('Bima Sakti')
        ->assertSee('100%')
        ->assertNoJavaScriptErrors();
});

it('records a student at the gate from a one-time code and another by name', function () {
    // The gate only takes scans between gate_opens_at and gate_closes_at (school time).
    $this->travelTo('2026-10-07 03:00:00');

    [$page, $tenant] = attendanceMemberSignsIn('staf-tu');
    [, , $students] = attendanceClasses($tenant, ['Aditya Pratama', 'Bima Sakti']);

    $code = inTenant($tenant, fn () => app(QrTokens::class)->issue($students['Aditya Pratama']->id))['token'];

    $page->navigate('/absensi/pindai')
        ->assertSee('Belum ada yang dicatat.')
        // Staff record at the gate, not in lessons.
        ->assertDontSee('Jam pelajaran')
        ->fill('code', $code)
        ->press('Catat')
        ->assertSee('Aditya Pratama')
        ->assertSee('Tercatat');

    // The same code again: used up.
    $page->fill('code', $code)
        ->press('Catat')
        ->assertSee('Kode QR tidak dikenal atau sudah kedaluwarsa')
        ->assertSee('Ditolak');

    // No phone: found by name.
    $page->fill('q', 'bima')
        ->press('Cari')
        ->click('internal:role=button[name="Catat Bima Sakti"i]')
        ->assertSee('X 2 ·')
        ->assertNoJavaScriptErrors();

    $rows = inTenant($tenant, fn () => DailyAttendance::query()->get()->keyBy('student_id'));

    expect($rows)->toHaveCount(2)
        ->and($rows[$students['Aditya Pratama']->id]->checked_in_at)->not->toBeNull()
        ->and($rows[$students['Aditya Pratama']->id]->check_in_method->value)->toBe('qr')
        ->and($rows[$students['Bima Sakti']->id]->check_in_method->value)->toBe('manual');
});

it('opens a teachers lesson on the own class with the own subject', function () {
    [$page, $tenant, $user] = attendanceMemberSignsIn('guru');
    [, $taught, $students] = attendanceClasses($tenant, ['Aditya Pratama', 'Bima Sakti']);

    inTenant($tenant, function () use ($tenant, $taught, $user): void {
        $teacher = Teacher::factory()->create(['name' => 'Pak Budi']);
        $teacher->forceFill(['user_id' => $user->id])->save();
        $subject = Subject::factory()->create(['name' => 'Matematika']);

        TeachingAssignment::factory()->create([
            'class_id' => $taught->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        // One lesson covering the whole of today at the school, so it is
        // always the lesson running now.
        $slot = PeriodSlot::factory()->create([
            'day' => now($tenant->timezone)->dayOfWeekIso,
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
        ]);

        TimetableEntry::factory()->create([
            'period_slot_id' => $slot->id,
            'class_id' => $taught->id,
            'subject_id' => $subject->id,
        ]);
    });

    // Kelas Saya › Absensi Kelas opens the lesson that is running now.
    $page->navigate('/absensi/absen-kelas')
        ->assertSee('Matematika · Kelas X 2')
        ->assertSee('Belum ditandai 2')
        ->click('internal:role=group[name="Status Bima Sakti"i] >> internal:role=button[name="Alpa"i]')
        ->press('Tandai sisanya hadir')
        ->press('Simpan absensi')
        ->assertSee('Absensi kelas disimpan.')
        ->assertSee('Sudah pernah disimpan')
        ->assertNoJavaScriptErrors();

    $session = inTenant($tenant, fn () => LessonSession::query()->sole());
    $marks = inTenant($tenant, fn () => LessonAttendance::query()->get()
        ->mapWithKeys(fn (LessonAttendance $row): array => [$row->student_id => $row->status->value])->all());

    expect($session->class_id)->toBe($taught->id)
        ->and($session->subject_id)->not->toBeNull()
        ->and($marks)->toBe([$students['Aditya Pratama']->id => 'present', $students['Bima Sakti']->id => 'absent']);
})->skip(fn () => now('Asia/Jakarta')->dayOfWeekIso === 7, 'Hari Minggu tidak punya jam pelajaran.');
