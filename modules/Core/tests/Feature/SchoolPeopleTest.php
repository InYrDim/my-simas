<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Extracurricular;
use Modules\Core\App\Domain\Models\ExtracurricularMember;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\StudentClassHistory;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/**
 * Guru & Tendik, Siswa and Ekstrakurikuler.
 */
function studentPayload(array $overrides = []): array
{
    return [
        'name' => 'Aditya Nugraha', 'nis' => '240100', 'nisn' => '0081234500', 'gender' => 'L',
        'birth_date' => '2010-03-04', 'guardian_name' => 'Bpk. Nugraha', 'guardian_phone' => '0812-5550-1000',
        'class_id' => 'none',
        ...$overrides,
    ];
}

// ---- Guru & Tendik

it('creates, updates and deletes a teacher', function () {
    $tenant = schoolAs('teach-a');

    post(school($tenant->slug, '/master/guru'), [
        'name' => 'Budi Santoso, S.Pd.', 'nip' => '198012', 'nuptk' => '5543', 'employment' => 'PNS',
        'duty' => 'Guru Mapel', 'email' => 'budi@teach-a.test',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $teacher = inSchool($tenant, fn () => Teacher::query()->sole());

    put(school($tenant->slug, "/master/guru/{$teacher->id}"), [
        'name' => 'Budi S.', 'nip' => '198012', 'nuptk' => '', 'employment' => 'GTY', 'duty' => 'Guru Mapel', 'email' => '',
    ])->assertSessionHasNoErrors();

    $teacher = inSchool($tenant, fn () => $teacher->fresh());
    expect($teacher->name)->toBe('Budi S.')->and($teacher->nuptk)->toBeNull()->and($teacher->email)->toBeNull();

    delete(school($tenant->slug, "/master/guru/{$teacher->id}"))->assertRedirect('/master/guru');
    expect(inSchool($tenant, fn () => Teacher::query()->count()))->toBe(0);
});

it('validates teachers and keeps the NIP unique per school only', function () {
    $a = schoolAs('teach-b');
    inSchool($a, fn () => Teacher::factory()->create(['nip' => '111']));

    post(school($a->slug, '/master/guru'), ['name' => 'X', 'nip' => '111', 'employment' => 'Magang', 'duty' => 'Guru Mapel'])
        ->assertSessionHasErrors(['nip', 'employment']);

    $b = schoolAs('teach-c');
    post(school($b->slug, '/master/guru'), ['name' => 'X', 'nip' => '111', 'employment' => 'PNS', 'duty' => 'Guru Mapel'])
        ->assertSessionHasNoErrors();
});

it('searches and filters teachers server-side with paging', function () {
    $tenant = schoolAs('teach-d');
    inSchool($tenant, function () {
        Teacher::factory()->count(30)->create();
        Teacher::factory()->honorary()->create(['name' => 'Lilis Suryani']);
    });

    get(school($tenant->slug, '/master/guru'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Teachers/Index')
            ->has('teachers', 25)
            ->where('pagination.total', 31)
            ->where('pagination.lastPage', 2));

    get(school($tenant->slug, '/master/guru?page=2'))
        ->assertInertia(fn (Assert $page) => $page->has('teachers', 6)->where('pagination.from', 26));

    get(school($tenant->slug, '/master/guru?q=Lilis'))
        ->assertInertia(fn (Assert $page) => $page->has('teachers', 1)->where('filters.q', 'Lilis'));

    get(school($tenant->slug, '/master/guru?employment=Honorer'))
        ->assertInertia(fn (Assert $page) => $page->has('teachers', 1)->where('teachers.0.name', 'Lilis Suryani'));
});

it('keeps teachers of other schools out and returns 404 for them', function () {
    $other = TenantFactory::new()->create(['slug' => 'teach-other']);
    $foreign = inSchool($other, fn () => Teacher::factory()->create());

    $tenant = schoolAs('teach-e');
    inSchool($tenant, fn () => Teacher::factory()->create());

    get(school($tenant->slug, '/master/guru'))->assertInertia(fn (Assert $page) => $page->has('teachers', 1));
    get(school($tenant->slug, "/master/guru/{$foreign->id}"))->assertNotFound();
    put(school($tenant->slug, "/master/guru/{$foreign->id}"), ['name' => 'X', 'employment' => 'PNS', 'duty' => 'Guru Mapel'])->assertNotFound();
    delete(school($tenant->slug, "/master/guru/{$foreign->id}"))->assertNotFound();
});

it('shows a teacher with the classes they are homeroom teacher of', function () {
    $tenant = schoolAs('teach-f');
    $teacher = inSchool($tenant, fn () => Teacher::factory()->create());
    $class = classIn($tenant, ['name' => 'X 1']);
    inSchool($tenant, fn () => $class->forceFill(['homeroom_teacher_id' => $teacher->id])->save());

    get(school($tenant->slug, "/master/guru/{$teacher->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Teachers/Show')
            ->where('teacher.hasAccount', false)
            ->has('homeroomOf', 1)
            ->where('homeroomOf.0.name', 'X 1'));

    get(school($tenant->slug, '/master/kelas'))
        ->assertInertia(fn (Assert $page) => $page->where('classes.0.homeroom', $teacher->name));
});

it('refuses to delete a homeroom teacher or a coach', function () {
    $tenant = schoolAs('teach-g');
    $homeroom = inSchool($tenant, fn () => Teacher::factory()->create());
    $coach = inSchool($tenant, fn () => Teacher::factory()->create());
    $class = classIn($tenant);
    inSchool($tenant, function () use ($class, $homeroom, $coach): void {
        $class->forceFill(['homeroom_teacher_id' => $homeroom->id])->save();
        Extracurricular::factory()->create(['coach_teacher_id' => $coach->id]);
    });

    delete(school($tenant->slug, "/master/guru/{$homeroom->id}"))->assertSessionHasErrors('status');
    delete(school($tenant->slug, "/master/guru/{$coach->id}"))->assertSessionHasErrors('status');
});

it('forbids a teacher account from changing teachers', function () {
    $tenant = schoolAs('teach-h', 'guru');

    get(school($tenant->slug, '/master/guru'))->assertOk();
    post(school($tenant->slug, '/master/guru'), ['name' => 'X', 'employment' => 'PNS', 'duty' => 'Guru Mapel'])->assertForbidden();
});

// ---- Siswa

it('creates a student without a class', function () {
    $tenant = schoolAs('stud-a');

    post(school($tenant->slug, '/master/siswa'), studentPayload())->assertRedirect()->assertSessionHasNoErrors();

    $student = inSchool($tenant, fn () => Student::query()->sole());
    expect($student->status)->toBe('active')->and($student->class_id)->toBeNull()
        ->and($student->birth_date->toDateString())->toBe('2010-03-04');
});

it('places a student in a class and writes the class history', function () {
    $tenant = schoolAs('stud-b');
    $class = classIn($tenant, ['name' => 'X 1']);

    post(school($tenant->slug, '/master/siswa'), studentPayload(['class_id' => $class->id]))->assertSessionHasNoErrors();

    $student = inSchool($tenant, fn () => Student::query()->sole());
    $history = inSchool($tenant, fn () => StudentClassHistory::query()->sole());

    expect($student->class_id)->toBe($class->id)
        ->and($history->class_name)->toBe('X 1')
        ->and($history->academic_year_id)->toBe($class->academic_year_id)
        ->and($history->note)->toBe('Kelas aktif');

    get(school($tenant->slug, '/master/kelas'))
        ->assertInertia(fn (Assert $page) => $page->where('classes.0.students', 1));

    get(school($tenant->slug, "/master/kelas/{$class->id}"))
        ->assertInertia(fn (Assert $page) => $page->has('students', 1)->where('students.0.nis', '240100'));
});

it('records a move to another class of the same year', function () {
    $tenant = schoolAs('stud-c');
    $first = classIn($tenant, ['name' => 'X 1']);
    $second = classIn($tenant, ['name' => 'X 2']);
    post(school($tenant->slug, '/master/siswa'), studentPayload(['class_id' => $first->id]));
    $student = inSchool($tenant, fn () => Student::query()->sole());

    put(school($tenant->slug, "/master/siswa/{$student->id}"), studentPayload(['class_id' => $second->id, 'status' => 'active']))
        ->assertSessionHasNoErrors();

    $history = inSchool($tenant, fn () => StudentClassHistory::query()->sole());
    expect($history->class_name)->toBe('X 2')->and($history->note)->toBe('Pindah kelas');
});

it('drops the class of a student who leaves and marks the history', function () {
    $tenant = schoolAs('stud-d');
    $class = classIn($tenant);
    post(school($tenant->slug, '/master/siswa'), studentPayload(['class_id' => $class->id]));
    $student = inSchool($tenant, fn () => Student::query()->sole());

    put(school($tenant->slug, "/master/siswa/{$student->id}"), studentPayload(['class_id' => $class->id, 'status' => 'graduated']))
        ->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => $student->fresh()->class_id))->toBeNull()
        ->and(inSchool($tenant, fn () => StudentClassHistory::query()->sole()->note))->toBe('Lulus');
});

it('validates students and keeps NIS unique per school only', function () {
    $a = schoolAs('stud-e');
    inSchool($a, fn () => Student::factory()->create(['nis' => '240100', 'nisn' => '0081234500']));

    post(school($a->slug, '/master/siswa'), studentPayload(['gender' => 'X', 'birth_date' => '2999-01-01']))
        ->assertSessionHasErrors(['nis', 'nisn', 'gender', 'birth_date']);

    $b = schoolAs('stud-f');
    post(school($b->slug, '/master/siswa'), studentPayload())->assertSessionHasNoErrors();
});

it('refuses a student placed in another school\'s class', function () {
    $other = TenantFactory::new()->create(['slug' => 'stud-other']);
    $foreign = classIn($other);

    $tenant = schoolAs('stud-g');

    post(school($tenant->slug, '/master/siswa'), studentPayload(['class_id' => $foreign->id]))->assertSessionHasErrors('class_id');
});

it('searches students and filters them by class and status', function () {
    $tenant = schoolAs('stud-h');
    $class = classIn($tenant, ['name' => 'X 1']);
    inSchool($tenant, function () use ($class): void {
        Student::factory()->count(27)->create(['class_id' => $class->id]);
        Student::factory()->status('graduated')->create(['name' => 'Rizal Lulus', 'nis' => '999']);
    });

    get(school($tenant->slug, '/master/siswa'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Students/Index')
            ->has('students', 25)
            ->where('pagination.total', 28)
            ->has('classes', 1));

    get(school($tenant->slug, '/master/siswa?q=Rizal%20Lulus'))->assertInertia(fn (Assert $page) => $page->has('students', 1)->where('students.0.name', 'Rizal Lulus'));
    get(school($tenant->slug, '/master/siswa?status=graduated'))->assertInertia(fn (Assert $page) => $page->has('students', 1));
    get(school($tenant->slug, "/master/siswa?class={$class->id}&page=2"))->assertInertia(fn (Assert $page) => $page->has('students', 2));
});

it('shows a student with the class history and keeps other schools out', function () {
    $other = TenantFactory::new()->create(['slug' => 'stud-show-other']);
    $foreign = inSchool($other, fn () => Student::factory()->create());

    $tenant = schoolAs('stud-i');
    $class = classIn($tenant, ['name' => 'X 1']);
    post(school($tenant->slug, '/master/siswa'), studentPayload(['class_id' => $class->id]));
    $student = inSchool($tenant, fn () => Student::query()->sole());

    get(school($tenant->slug, "/master/siswa/{$student->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Students/Show')
            ->where('student.class', 'X 1')
            ->has('history', 1)
            ->where('history.0.class', 'X 1'));

    get(school($tenant->slug, "/master/siswa/{$foreign->id}"))->assertNotFound();
    delete(school($tenant->slug, "/master/siswa/{$foreign->id}"))->assertNotFound();
});

it('deletes a student with the history and memberships', function () {
    $tenant = schoolAs('stud-j');
    $class = classIn($tenant);
    post(school($tenant->slug, '/master/siswa'), studentPayload(['class_id' => $class->id]));
    $student = inSchool($tenant, fn () => Student::query()->sole());
    $activity = inSchool($tenant, fn () => Extracurricular::factory()->create());
    inSchool($tenant, fn () => $activity->memberships()->create(['student_id' => $student->id]));

    delete(school($tenant->slug, "/master/siswa/{$student->id}"))->assertRedirect('/master/siswa');

    expect(inSchool($tenant, fn () => Student::query()->count()))->toBe(0)
        ->and(inSchool($tenant, fn () => StudentClassHistory::query()->count()))->toBe(0)
        ->and(inSchool($tenant, fn () => ExtracurricularMember::query()->count()))->toBe(0);
});

it('refuses to delete a class that still has students', function () {
    $tenant = schoolAs('stud-k');
    $class = classIn($tenant);
    post(school($tenant->slug, '/master/siswa'), studentPayload(['class_id' => $class->id]));

    delete(school($tenant->slug, "/master/kelas/{$class->id}"))->assertSessionHasErrors('status');
    expect(inSchool($tenant, fn () => ClassGroup::query()->count()))->toBe(1);
});

it('forbids a teacher account from changing students', function () {
    $tenant = schoolAs('stud-l', 'guru');

    get(school($tenant->slug, '/master/siswa'))->assertOk();
    post(school($tenant->slug, '/master/siswa'), studentPayload())->assertForbidden();
});

// ---- Ekstrakurikuler

it('manages an extracurricular with its coach', function () {
    $tenant = schoolAs('xtra-a');
    $coach = inSchool($tenant, fn () => Teacher::factory()->create());

    post(school($tenant->slug, '/master/ekstrakurikuler'), [
        'name' => 'Pramuka', 'coach_teacher_id' => $coach->id, 'schedule' => 'Jumat 14.30–16.00', 'kind' => 'Wajib',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $activity = inSchool($tenant, fn () => Extracurricular::query()->sole());

    put(school($tenant->slug, "/master/ekstrakurikuler/{$activity->id}"), [
        'name' => 'Pramuka', 'coach_teacher_id' => 'none', 'schedule' => '', 'kind' => 'Pilihan',
    ])->assertSessionHasNoErrors();
    expect(inSchool($tenant, fn () => $activity->fresh()->coach_teacher_id))->toBeNull();

    get(school($tenant->slug, '/master/ekstrakurikuler'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Extracurriculars/Index')
            ->where('extracurriculars.0.kind', 'Pilihan')
            ->where('extracurriculars.0.members', 0)
            ->has('teachers', 1));

    delete(school($tenant->slug, "/master/ekstrakurikuler/{$activity->id}"))->assertRedirect('/master/ekstrakurikuler');
    expect(inSchool($tenant, fn () => Extracurricular::query()->count()))->toBe(0);
});

it('adds and removes members by NIS', function () {
    $tenant = schoolAs('xtra-b');
    $activity = inSchool($tenant, fn () => Extracurricular::factory()->create());
    $student = inSchool($tenant, fn () => Student::factory()->create(['nis' => '2401']));

    post(school($tenant->slug, "/master/ekstrakurikuler/{$activity->id}/anggota"), ['nis' => '2401'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $member = inSchool($tenant, fn () => ExtracurricularMember::query()->sole());
    expect($member->tenant_id)->toBe($tenant->id)->and($member->student_id)->toBe($student->id);

    get(school($tenant->slug, "/master/ekstrakurikuler/{$activity->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Extracurriculars/Show')
            ->where('extracurricular.members', 1)
            ->where('extracurricular.memberList.0.nis', '2401'));

    delete(school($tenant->slug, "/master/ekstrakurikuler/{$activity->id}/anggota/{$student->id}"))->assertRedirect();
    expect(inSchool($tenant, fn () => ExtracurricularMember::query()->count()))->toBe(0);
});

it('refuses an unknown, inactive or duplicate member', function () {
    $tenant = schoolAs('xtra-c');
    $activity = inSchool($tenant, fn () => Extracurricular::factory()->create());
    inSchool($tenant, fn () => Student::factory()->status('left')->create(['nis' => '777']));
    inSchool($tenant, function () use ($activity): void {
        $student = Student::factory()->create(['nis' => '888']);
        $activity->memberships()->create(['student_id' => $student->id]);
    });

    post(school($tenant->slug, "/master/ekstrakurikuler/{$activity->id}/anggota"), ['nis' => '000'])->assertSessionHasErrors('nis');
    post(school($tenant->slug, "/master/ekstrakurikuler/{$activity->id}/anggota"), ['nis' => '777'])->assertSessionHasErrors('nis');
    post(school($tenant->slug, "/master/ekstrakurikuler/{$activity->id}/anggota"), ['nis' => '888'])->assertSessionHasErrors('nis');
});

it('cannot add a student of another school as a member', function () {
    $other = TenantFactory::new()->create(['slug' => 'xtra-other']);
    inSchool($other, fn () => Student::factory()->create(['nis' => '5555']));

    $tenant = schoolAs('xtra-d');
    $activity = inSchool($tenant, fn () => Extracurricular::factory()->create());

    post(school($tenant->slug, "/master/ekstrakurikuler/{$activity->id}/anggota"), ['nis' => '5555'])->assertSessionHasErrors('nis');
});

it('keeps another school\'s extracurricular out', function () {
    $other = TenantFactory::new()->create(['slug' => 'xtra-show-other']);
    $foreign = inSchool($other, fn () => Extracurricular::factory()->create());

    $tenant = schoolAs('xtra-e');

    get(school($tenant->slug, "/master/ekstrakurikuler/{$foreign->id}"))->assertNotFound();
    post(school($tenant->slug, "/master/ekstrakurikuler/{$foreign->id}/anggota"), ['nis' => '1'])->assertNotFound();
});

it('forbids a teacher account from changing extracurriculars', function () {
    $tenant = schoolAs('xtra-f', 'guru');
    $activity = inSchool($tenant, fn () => Extracurricular::factory()->create());

    get(school($tenant->slug, '/master/ekstrakurikuler'))->assertOk();
    post(school($tenant->slug, '/master/ekstrakurikuler'), ['name' => 'X', 'kind' => 'Wajib'])->assertForbidden();
    post(school($tenant->slug, "/master/ekstrakurikuler/{$activity->id}/anggota"), ['nis' => '1'])->assertForbidden();
});

it('offers only the classes of the active academic year to place students in', function () {
    $tenant = schoolAs('xtra-g');
    inSchool($tenant, fn () => AcademicYear::factory()->create());
    $draftClass = classIn($tenant, ['name' => 'Draft 1']);
    expect($draftClass->exists)->toBeTrue();

    get(school($tenant->slug, '/master/siswa'))->assertInertia(fn (Assert $page) => $page->has('classes', 0));
});
