<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Major;
use Modules\Core\App\Domain\Models\Room;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/**
 * Tingkat & Jurusan, Kelas, Mata Pelajaran and Ruangan.
 */
// ---- Tingkat

it('seeds the grades of the school\'s jenjang on first visit', function () {
    $tenant = schoolAs('grade-a');

    get(school($tenant->slug, '/master/tingkat-jurusan'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Grades/Index')
            ->where('grades', fn ($grades) => collect($grades)->pluck('name')->all() === ['X', 'XI', 'XII'])
            ->has('majors', 0));
});

it('re-seeds the grades when the jenjang changes and no class exists yet', function () {
    $tenant = schoolAs('grade-b');
    get(school($tenant->slug, '/master/tingkat-jurusan'));

    put(school($tenant->slug, '/master/sekolah'), ['level' => 'smp'])->assertRedirect();
    expect(inSchool($tenant, fn () => Grade::query()->orderBy('sort_order')->pluck('name')->all()))->toBe(['7', '8', '9']);

    put(school($tenant->slug, '/master/sekolah'), ['level' => 'sd'])->assertRedirect();
    expect(inSchool($tenant, fn () => Grade::query()->count()))->toBe(6);
});

it('refuses a jenjang change once a class hangs on the grades', function () {
    $tenant = schoolAs('grade-c');
    get(school($tenant->slug, '/master/tingkat-jurusan'));
    classIn($tenant);

    put(school($tenant->slug, '/master/sekolah'), ['level' => 'sd'])->assertSessionHasErrors('level');
    put(school($tenant->slug, '/master/sekolah'), ['level' => 'smk'])->assertSessionHasErrors('level');

    expect(inSchool($tenant, fn () => SchoolProfile::query()->sole()->level->value))->toBe('sma')
        ->and(inSchool($tenant, fn () => Grade::query()->orderBy('sort_order')->pluck('name')->all()))->toBe(['X', 'XI', 'XII']);
});

it('counts the classes of the active academic year per grade', function () {
    $tenant = schoolAs('grade-d');
    get(school($tenant->slug, '/master/tingkat-jurusan'));
    classIn($tenant);
    classIn($tenant);

    get(school($tenant->slug, '/master/tingkat-jurusan'))
        ->assertInertia(fn (Assert $page) => $page->where('grades.0.classes', 2)->where('grades.1.classes', 0));
});

// ---- Jurusan

it('creates, updates and deletes a major with its concentrations', function () {
    $tenant = schoolAs('major-a');

    post(school($tenant->slug, '/master/jurusan'), [
        'code' => 'RPL', 'name' => 'Rekayasa Perangkat Lunak', 'kind' => 'Kompetensi Keahlian',
        'concentrations' => 'Pemrograman Web, Pemrograman Mobile, ',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $major = inSchool($tenant, fn () => Major::query()->sole());
    expect($major->concentrations)->toBe(['Pemrograman Web', 'Pemrograman Mobile']);

    put(school($tenant->slug, "/master/jurusan/{$major->id}"), [
        'code' => 'RPL', 'name' => 'RPL Baru', 'kind' => 'Kompetensi Keahlian', 'concentrations' => '',
    ])->assertSessionHasNoErrors();
    expect(inSchool($tenant, fn () => $major->fresh()->name))->toBe('RPL Baru');

    delete(school($tenant->slug, "/master/jurusan/{$major->id}"))->assertRedirect();
    expect(inSchool($tenant, fn () => Major::query()->count()))->toBe(0);
});

it('rejects a duplicate major code but allows it in another school', function () {
    $a = schoolAs('major-b');
    post(school($a->slug, '/master/jurusan'), ['code' => 'IPA', 'name' => 'IPA', 'kind' => 'Peminatan'])->assertSessionHasNoErrors();
    post(school($a->slug, '/master/jurusan'), ['code' => 'IPA', 'name' => 'IPA 2', 'kind' => 'Peminatan'])->assertSessionHasErrors('code');

    $b = schoolAs('major-c');
    post(school($b->slug, '/master/jurusan'), ['code' => 'IPA', 'name' => 'IPA', 'kind' => 'Peminatan'])->assertSessionHasNoErrors();
});

it('refuses to delete a major that a class still uses', function () {
    $tenant = schoolAs('major-d');
    $major = inSchool($tenant, fn () => Major::factory()->create());
    classIn($tenant, ['major_id' => $major->id]);

    delete(school($tenant->slug, "/master/jurusan/{$major->id}"))->assertSessionHasErrors('status');
    expect(inSchool($tenant, fn () => Major::query()->count()))->toBe(1);
});

it('forbids a teacher from editing majors', function () {
    $tenant = schoolAs('major-e', 'guru');

    post(school($tenant->slug, '/master/jurusan'), ['code' => 'IPA', 'name' => 'IPA', 'kind' => 'Peminatan'])->assertForbidden();
});

// ---- Kelas

it('creates a class with an optional room and no major', function () {
    $tenant = schoolAs('class-a');
    $year = inSchool($tenant, fn () => AcademicYear::factory()->active()->create());
    get(school($tenant->slug, '/master/tingkat-jurusan'));
    $grade = inSchool($tenant, fn () => Grade::query()->firstOrFail());

    post(school($tenant->slug, '/master/kelas'), [
        'name' => 'X IPA 1', 'academic_year_id' => $year->id, 'grade_id' => $grade->id,
        'major_id' => 'none', 'room_id' => 'none',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $class = inSchool($tenant, fn () => ClassGroup::query()->sole());
    expect($class->major_id)->toBeNull()->and($class->room_id)->toBeNull();
});

it('requires a major once the school has defined majors', function () {
    $tenant = schoolAs('class-b');
    $year = inSchool($tenant, fn () => AcademicYear::factory()->active()->create());
    get(school($tenant->slug, '/master/tingkat-jurusan'));
    $grade = inSchool($tenant, fn () => Grade::query()->firstOrFail());
    $major = inSchool($tenant, fn () => Major::factory()->create());

    post(school($tenant->slug, '/master/kelas'), [
        'name' => 'X 1', 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'major_id' => 'none',
    ])->assertSessionHasErrors('major_id');

    post(school($tenant->slug, '/master/kelas'), [
        'name' => 'X 1', 'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'major_id' => $major->id,
    ])->assertSessionHasNoErrors();
});

it('keeps a class name unique per academic year', function () {
    $tenant = schoolAs('class-c');
    $class = classIn($tenant, ['name' => 'X 1']);
    $otherYear = inSchool($tenant, fn () => AcademicYear::factory()->create());

    post(school($tenant->slug, '/master/kelas'), [
        'name' => 'X 1', 'academic_year_id' => $class->academic_year_id, 'grade_id' => $class->grade_id,
    ])->assertSessionHasErrors('name');

    post(school($tenant->slug, '/master/kelas'), [
        'name' => 'X 1', 'academic_year_id' => $otherYear->id, 'grade_id' => $class->grade_id,
    ])->assertSessionHasNoErrors();
});

it('refuses a class that points at another school\'s records', function () {
    $other = TenantFactory::new()->create(['slug' => 'class-other']);
    $foreignRoom = inSchool($other, fn () => Room::factory()->create());
    $foreignGrade = inSchool($other, fn () => Grade::factory()->create());

    $tenant = schoolAs('class-d');
    $year = inSchool($tenant, fn () => AcademicYear::factory()->active()->create());

    post(school($tenant->slug, '/master/kelas'), [
        'name' => 'X 1', 'academic_year_id' => $year->id, 'grade_id' => $foreignGrade->id, 'room_id' => $foreignRoom->id,
    ])->assertSessionHasErrors(['grade_id', 'room_id']);
});

it('lists classes with their grade, room and year', function () {
    $tenant = schoolAs('class-e');
    $room = inSchool($tenant, fn () => Room::factory()->create(['name' => 'Ruang 101']));
    classIn($tenant, ['name' => 'X 1', 'room_id' => $room->id]);

    get(school($tenant->slug, '/master/kelas'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Classes/Index')
            ->has('classes', 1)
            ->where('classes.0.name', 'X 1')
            ->where('classes.0.room', 'Ruang 101')
            ->where('classes.0.homeroom', null)
            ->has('rooms', 1)
            ->has('years', 1));
});

it('shows one class and refuses another school\'s class', function () {
    $other = TenantFactory::new()->create(['slug' => 'class-show-other']);
    $foreign = classIn($other);

    $tenant = schoolAs('class-f');
    $class = classIn($tenant, ['name' => 'X 2']);

    get(school($tenant->slug, "/master/kelas/{$class->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Classes/Show')
            ->where('class.name', 'X 2')
            ->has('students', 0));

    get(school($tenant->slug, "/master/kelas/{$foreign->id}"))->assertNotFound();
    put(school($tenant->slug, "/master/kelas/{$foreign->id}"), ['name' => 'Z'])->assertNotFound();
    delete(school($tenant->slug, "/master/kelas/{$foreign->id}"))->assertNotFound();
});

it('updates and deletes a class', function () {
    $tenant = schoolAs('class-g');
    $class = classIn($tenant, ['name' => 'X 3']);

    put(school($tenant->slug, "/master/kelas/{$class->id}"), [
        'name' => 'X 3 Baru', 'academic_year_id' => $class->academic_year_id, 'grade_id' => $class->grade_id,
    ])->assertSessionHasNoErrors();
    expect(inSchool($tenant, fn () => $class->fresh()->name))->toBe('X 3 Baru');

    delete(school($tenant->slug, "/master/kelas/{$class->id}"))->assertRedirect();
    expect(inSchool($tenant, fn () => ClassGroup::query()->count()))->toBe(0);
});

// ---- Mata Pelajaran

it('manages subjects and labels them with the school\'s grade range', function () {
    $tenant = schoolAs('subj-a');
    get(school($tenant->slug, '/master/tingkat-jurusan'));

    post(school($tenant->slug, '/master/mata-pelajaran'), ['code' => 'MTK', 'name' => 'Matematika', 'group' => 'Umum', 'kkm' => 70])
        ->assertRedirect()->assertSessionHasNoErrors();

    get(school($tenant->slug, '/master/mata-pelajaran'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Subjects/Index')
            ->where('subjects.0.code', 'MTK')
            ->where('subjects.0.grades', 'Kelas X–XII')
            ->where('subjects.0.kkm', 70));

    $subject = inSchool($tenant, fn () => Subject::query()->sole());
    put(school($tenant->slug, "/master/mata-pelajaran/{$subject->id}"), ['code' => 'MTK', 'name' => 'Matematika Wajib', 'group' => 'Umum', 'kkm' => 72])
        ->assertSessionHasNoErrors();
    expect(inSchool($tenant, fn () => $subject->fresh()->name))->toBe('Matematika Wajib');

    delete(school($tenant->slug, "/master/mata-pelajaran/{$subject->id}"))->assertRedirect();
    expect(inSchool($tenant, fn () => Subject::query()->count()))->toBe(0);
});

it('validates subjects', function () {
    $tenant = schoolAs('subj-b');
    inSchool($tenant, fn () => Subject::factory()->create(['code' => 'BIN']));

    post(school($tenant->slug, '/master/mata-pelajaran'), ['code' => 'BIN', 'name' => 'Bahasa', 'group' => 'Lainnya', 'kkm' => 120])
        ->assertSessionHasErrors(['code', 'group', 'kkm']);
});

it('forbids a teacher from editing subjects but lets them read', function () {
    $tenant = schoolAs('subj-c', 'guru');

    get(school($tenant->slug, '/master/mata-pelajaran'))->assertOk();
    post(school($tenant->slug, '/master/mata-pelajaran'), ['code' => 'X', 'name' => 'X', 'group' => 'Umum', 'kkm' => 70])->assertForbidden();
});

// ---- Ruangan

it('manages rooms', function () {
    $tenant = schoolAs('room-a');

    post(school($tenant->slug, '/master/ruangan'), ['code' => 'LAB-1', 'name' => 'Lab Komputer', 'type' => 'Laboratorium', 'capacity' => 40, 'status' => 'active'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $room = inSchool($tenant, fn () => Room::query()->sole());
    put(school($tenant->slug, "/master/ruangan/{$room->id}"), ['code' => 'LAB-1', 'name' => 'Lab Komputer', 'type' => 'Laboratorium', 'capacity' => 38, 'status' => 'maintenance'])
        ->assertSessionHasNoErrors();

    get(school($tenant->slug, '/master/ruangan'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Rooms/Index')
            ->where('rooms.0.capacity', 38)
            ->where('rooms.0.status', 'maintenance'));

    delete(school($tenant->slug, "/master/ruangan/{$room->id}"))->assertRedirect();
    expect(inSchool($tenant, fn () => Room::query()->count()))->toBe(0);
});

it('refuses to delete a room that a class uses', function () {
    $tenant = schoolAs('room-b');
    $room = inSchool($tenant, fn () => Room::factory()->create());
    classIn($tenant, ['room_id' => $room->id]);

    delete(school($tenant->slug, "/master/ruangan/{$room->id}"))->assertSessionHasErrors('status');
});

it('validates rooms and keeps codes unique per school', function () {
    $tenant = schoolAs('room-c');
    inSchool($tenant, fn () => Room::factory()->create(['code' => 'R-1']));

    post(school($tenant->slug, '/master/ruangan'), ['code' => 'R-1', 'name' => 'X', 'type' => 'Gudang', 'capacity' => 0])
        ->assertSessionHasErrors(['code', 'type', 'capacity']);
});
