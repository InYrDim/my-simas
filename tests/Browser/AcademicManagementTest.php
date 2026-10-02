<?php

use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\CalendarEvent;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;

require_once __DIR__.'/Support/school.php';

/**
 * Akademik (Fase 5) in a real browser: what the school admin clicks, with
 * the database checked behind every save. Server rules (validation,
 * permissions, tenant isolation) are covered by the Core feature tests;
 * these tests prove the pages work — selects, checkboxes, dialogs and the
 * forms that post from them.
 */
it('sets a homeroom teacher from the page and keeps it after a reload', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    [$class, $teacher] = inTenant($tenant, function (): array {
        $year = AcademicYear::factory()->active()->create();

        return [
            ClassGroup::factory()->create(['academic_year_id' => $year->id, 'name' => 'X 1']),
            Teacher::factory()->create(['name' => 'Pak Budi']),
        ];
    });

    $page->navigate('/akademik/wali-kelas')
        ->assertSee('Simpan penetapan')
        ->assertButtonDisabled('Simpan penetapan')
        ->click('internal:role=combobox[name="Wali Kelas X 1"i]')
        ->click('internal:role=option[name="Pak Budi"i]')
        ->press('Simpan penetapan')
        ->assertSee('Penetapan wali kelas disimpan.')
        ->assertNoJavaScriptErrors();

    expect(inTenant($tenant, fn () => $class->fresh()->homeroom_teacher_id))->toBe($teacher->id);

    $page->navigate('/master/kelas')
        ->assertSee('Pak Budi');
});

it('assigns a teacher and hours to a subject of the chosen class', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    [$first, $second, $subject, $teacher] = inTenant($tenant, function (): array {
        $year = AcademicYear::factory()->active()->create();

        return [
            ClassGroup::factory()->create(['academic_year_id' => $year->id, 'name' => 'X 1']),
            ClassGroup::factory()->create(['academic_year_id' => $year->id, 'name' => 'X 2']),
            Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']),
            Teacher::factory()->create(['name' => 'Bu Rina']),
        ];
    });

    $page->navigate('/akademik/pengampu')
        ->assertSee('Simpan pengampu X 1')
        ->click('internal:role=combobox[name="Kelas"i]')
        ->click('internal:role=option[name="Kelas X 2"i]')
        ->assertSee('Simpan pengampu X 2')
        ->click('internal:role=combobox[name="Guru Matematika"i]')
        ->click('internal:role=option[name="Bu Rina"i]')
        ->fill('internal:role=spinbutton[name="JP per minggu Matematika"i]', '4')
        ->press('Simpan pengampu X 2')
        ->assertSee('Pengampu kelas X 2 disimpan.')
        ->assertNoJavaScriptErrors();

    $row = inTenant($tenant, fn () => TeachingAssignment::query()->sole());

    expect($row->class_id)->toBe($second->id)
        ->and($row->subject_id)->toBe($subject->id)
        ->and($row->teacher_id)->toBe($teacher->id)
        ->and($row->hours_per_week)->toBe(4);

    // The class page shows it, the other class has none.
    $page->navigate("/master/kelas/{$second->id}")
        ->assertSee('Matematika')
        ->assertSee('Bu Rina');

    $page->navigate("/master/kelas/{$first->id}")
        ->assertSee('Belum ada pengampu untuk kelas ini.');
});

it('promotes one student and then graduates the rest from the placement page', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    [$x1, $xi1, $andi, $budi] = inTenant($tenant, function (): array {
        $year = AcademicYear::factory()->active()->create(['name' => '2025/2026', 'start_date' => '2025-07-14', 'end_date' => '2026-06-27']);
        $next = AcademicYear::factory()->create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-26']);
        $x1 = ClassGroup::factory()->create(['academic_year_id' => $year->id, 'name' => 'X 1']);

        return [
            $x1,
            ClassGroup::factory()->create(['academic_year_id' => $next->id, 'name' => 'XI 1']),
            Student::factory()->create(['name' => 'Andi', 'class_id' => $x1->id]),
            Student::factory()->create(['name' => 'Budi', 'class_id' => $x1->id]),
        ];
    });

    // --- Promote Andi: later year and its class are preselected
    $page->navigate('/akademik/penempatan')
        ->assertSee('Siswa X 1')
        ->assertButtonDisabled('Konfirmasi penempatan')
        ->click('internal:role=checkbox[name="Pilih Andi"i]')
        ->assertSee('1 siswa dari X 1 akan dinaikkan ke XI 1.')
        ->press('Konfirmasi penempatan')
        ->assertSee('1 siswa dinaikkan ke XI 1.')
        ->assertNoJavaScriptErrors();

    inTenant($tenant, function () use ($andi, $budi, $x1, $xi1): void {
        expect($andi->fresh()->class_id)->toBe($xi1->id)
            ->and($budi->fresh()->class_id)->toBe($x1->id);
    });

    // --- Graduate everyone left in X 1
    $page->assertDontSee('Pilih Andi')
        ->click('internal:role=combobox[name="Tindakan"i]')
        ->click('internal:role=option[name="Luluskan"i]')
        ->click('Pilih semua')
        ->assertSee('1 siswa dari X 1 akan diluluskan.')
        ->press('Konfirmasi penempatan')
        ->assertSee('1 siswa diluluskan.')
        ->assertNoJavaScriptErrors();

    inTenant($tenant, function () use ($budi): void {
        expect($budi->fresh()->status)->toBe('graduated')
            ->and($budi->fresh()->class_id)->toBeNull();
    });
});

it('adds a bell slot, copies the day to another and shows both', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    $page->navigate('/akademik/jam-pelajaran')
        ->assertSee('Belum ada jam untuk hari Senin.')
        ->click('internal:role=button[name="Tambah jam"i]')
        ->fill('start_time', '07:15')
        ->fill('end_time', '08:00')
        ->click('internal:role=button[name="Simpan"s]')
        ->assertSee('Jam Senin ditambahkan.')
        ->assertNoJavaScriptErrors();

    $slot = inTenant($tenant, fn () => PeriodSlot::query()->sole());

    expect($slot->day)->toBe(1)
        ->and($slot->start_time)->toBe('07:15:00')
        ->and($slot->end_time)->toBe('08:00:00')
        ->and($slot->type)->toBe('Pelajaran');

    // --- Copy Senin over Selasa through the checkbox dialog
    $page->click('internal:role=button[name="Salin ke hari lain"i]')
        ->click('internal:role=checkbox[name="Selasa"i]')
        ->click('internal:role=button[name="Salin"s]')
        ->assertSee('Jam pelajaran Senin disalin.')
        ->assertNoJavaScriptErrors();

    expect(inTenant($tenant, fn () => PeriodSlot::query()->where('day', 2)->pluck('start_time')->all()))->toBe(['07:15:00']);

    $page->click('internal:role=tab[name="Selasa"i]')
        ->assertSee('07:15')
        ->assertDontSee('Belum ada jam untuk hari Selasa.');
});

it('refuses an overlapping bell slot with a message beside the form', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    inTenant($tenant, fn () => PeriodSlot::factory()->create(['day' => 1, 'start_time' => '07:15:00', 'end_time' => '08:00:00']));

    $page->navigate('/akademik/jam-pelajaran')
        ->click('internal:role=button[name="Tambah jam"i]')
        ->fill('start_time', '07:30')
        ->fill('end_time', '08:30')
        ->click('internal:role=button[name="Simpan"s]')
        ->assertSee('Jam ini bertabrakan dengan jam lain di hari yang sama.');

    expect(inTenant($tenant, fn () => PeriodSlot::query()->count()))->toBe(1);
});

it('adds, edits and deletes a calendar event', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    // --- Add a ranged exam
    $page->navigate('/akademik/kalender')
        ->assertSee('Belum ada peristiwa di kalender.')
        ->click('internal:role=button[name="Tambah peristiwa"i]')
        ->fill('title', 'Penilaian Tengah Semester')
        ->click('internal:role=combobox[name="Kategori"i]')
        ->click('internal:role=option[name="Ujian"i]')
        ->fill('start_date', '2026-10-19')
        ->fill('end_date', '2026-10-23')
        ->click('internal:role=button[name="Simpan"s]')
        ->assertSee('Peristiwa Penilaian Tengah Semester ditambahkan.')
        ->assertNoJavaScriptErrors();

    $event = inTenant($tenant, fn () => CalendarEvent::query()->sole());

    expect($event->title)->toBe('Penilaian Tengah Semester')
        ->and($event->category)->toBe('exam')
        ->and($event->start_date->toDateString())->toBe('2026-10-19')
        ->and($event->end_date?->toDateString())->toBe('2026-10-23');

    // --- Rename it
    $page->click('internal:role=button[name="Ubah"s]')
        ->fill('title', 'PTS Ganjil')
        ->click('internal:role=button[name="Simpan"s]')
        ->assertSee('Peristiwa PTS Ganjil diperbarui.');

    expect(inTenant($tenant, fn () => $event->fresh()->title))->toBe('PTS Ganjil');

    // --- Delete it after confirming
    $page->click('internal:role=button[name="Hapus"s]')
        ->click('internal:role=button[name="Hapus"s]')
        ->assertSee('Peristiwa PTS Ganjil dihapus.')
        ->assertSee('Belum ada peristiwa di kalender.')
        ->assertNoJavaScriptErrors();

    expect(inTenant($tenant, fn () => CalendarEvent::query()->count()))->toBe(0);
});
