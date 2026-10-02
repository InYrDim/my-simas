<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Contracts\DTOs\NewStudent;
use Modules\Core\App\Contracts\DTOs\StudentRecord;
use Modules\Core\App\Contracts\StudentAdmission;
use Modules\Core\App\Contracts\StudentDirectory;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\Tests\Feature\Support\RecordingStudentAdmission;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/RecordingStudentAdmission.php';

/*
 * Daftar ulang: an accepted applicant comes to re-register, and the
 * committee records it — the applicant becomes a student of the school,
 * through Core's contract, without a class.
 */

/**
 * A school whose results are announced, with one accepted applicant.
 *
 * @return array{0: Tenant, 1: AdmissionPeriod, 2: Applicant}
 */
function enrollmentSchool(string $slug, string $role = 'admin-sekolah', Decision $decision = Decision::Accepted, bool $announced = true): array
{
    $tenant = ppdbTenant(role: $role, slug: $slug);
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbSchool($tenant, fn () => $path->update(['quota' => 5]));

    if ($announced) {
        ppdbSchool($tenant, fn () => $period->update(['results_published_at' => '2027-07-01 08:00:00']));
    }

    $applicant = ppdbApplicant($tenant, $period, $wave, $path, [
        'name' => 'Nadia Putri Anggraini',
        'number' => 'PPDB-27-0001',
        'nisn' => '0071234567',
        'birth_date' => '2012-05-04',
        'status' => ApplicantStatus::Verified,
        'decision' => $decision,
        'score' => '88.40',
    ]);

    return [$tenant, $period, $applicant];
}

/**
 * The students of the school named like this, as Core's contract sees them.
 *
 * @return list<StudentRecord>
 */
function schoolStudents(Tenant $tenant, string $term): array
{
    return ppdbSchool($tenant, fn () => app(StudentDirectory::class)->search($term, 50));
}

it('makes the accepted applicant a student without a class and remembers who it is', function () {
    [$tenant, , $applicant] = enrollmentSchool('ulang-berhasil');

    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => ' 20270001 '])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    $saved = ppdbSchool($tenant, fn () => $applicant->fresh());
    $students = schoolStudents($tenant, '20270001');

    expect($students)->toHaveCount(1)
        ->and($students[0]->name)->toBe('Nadia Putri Anggraini')
        ->and($students[0]->nis)->toBe('20270001')
        ->and($students[0]->classId)->toBeNull()
        ->and($students[0]->active)->toBeTrue()
        ->and($saved->student_id)->toBe($students[0]->id)
        ->and($saved->isEnrolled())->toBeTrue()
        // Nothing else about the applicant changes.
        ->and($saved->decision)->toBe(Decision::Accepted)
        ->and($saved->number)->toBe('PPDB-27-0001');

    get(school($tenant->slug, '/ppdb'))->assertInertia(fn (Assert $page) => $page
        ->where('funnel', fn ($funnel) => collect($funnel)->pluck('count', 'key')->get('registered') === 1)
    );
});

it('hands Core the applicant\'s data as it is', function () {
    [$tenant, , $applicant] = enrollmentSchool('ulang-data');
    $this->app->instance(StudentAdmission::class, $spy = new RecordingStudentAdmission);

    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => '20270002'])->assertSessionHasNoErrors();

    expect($spy->admitted)->toHaveCount(1)
        ->and($spy->admitted[0])->toEqual(new NewStudent(
            name: 'Nadia Putri Anggraini',
            nis: '20270002',
            gender: 'P',
            nisn: '0071234567',
            birthDate: '2012-05-04',
            guardianName: 'Budi Santoso',
            guardianPhone: '081234567890',
        ))
        ->and(ppdbSchool($tenant, fn () => $applicant->fresh()->student_id))->toBe(9001);
});

it('refuses re-registration before the results are announced', function () {
    [$tenant, , $applicant] = enrollmentSchool('ulang-belum-umum', announced: false);

    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => '20270003'])->assertSessionHasErrors('enroll');

    expect(ppdbSchool($tenant, fn () => $applicant->fresh()->isEnrolled()))->toBeFalse()
        ->and(schoolStudents($tenant, '20270003'))->toBe([]);
});

it('refuses re-registration for an applicant who was not accepted', function (Decision $decision) {
    [$tenant, , $applicant] = enrollmentSchool("ulang-{$decision->value}", decision: $decision);

    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => '20270004'])->assertSessionHasErrors('enroll');

    expect(ppdbSchool($tenant, fn () => $applicant->fresh()->isEnrolled()))->toBeFalse()
        ->and(schoolStudents($tenant, '20270004'))->toBe([]);
})->with([Decision::Pending, Decision::Waitlist, Decision::Rejected]);

it('makes one student however often it is recorded', function () {
    [$tenant, , $applicant] = enrollmentSchool('ulang-dua-kali');
    $url = school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang");

    post($url, ['nis' => '20270005'])->assertSessionHasNoErrors();
    post($url, ['nis' => '20270006'])->assertSessionHasErrors('enroll');

    expect(schoolStudents($tenant, '20270005'))->toHaveCount(1)
        ->and(schoolStudents($tenant, '20270006'))->toBe([]);
});

it('refuses an NIS the school already uses', function () {
    [$tenant, , $applicant] = enrollmentSchool('ulang-nis-bentrok');
    ppdbSchool($tenant, fn () => app(StudentAdmission::class)->admit(new NewStudent('Sudah Ada', '20270008', 'L')));

    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => '20270008'])->assertSessionHasErrors('nis');

    expect(ppdbSchool($tenant, fn () => $applicant->fresh()->isEnrolled()))->toBeFalse()
        ->and(schoolStudents($tenant, 'Nadia'))->toBe([])
        ->and(schoolStudents($tenant, '20270008'))->toHaveCount(1);
});

it('refuses an applicant whose NISN is already a student\'s', function () {
    [$tenant, , $applicant] = enrollmentSchool('ulang-nisn-bentrok');
    ppdbSchool($tenant, fn () => app(StudentAdmission::class)->admit(new NewStudent('Sudah Ada', '20270009', 'L', nisn: '0071234567')));

    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => '20270010'])->assertSessionHasErrors('nis');

    expect(ppdbSchool($tenant, fn () => $applicant->fresh()->isEnrolled()))->toBeFalse()
        ->and(schoolStudents($tenant, '20270010'))->toBe([]);
});

it('needs an NIS', function (string $nis) {
    [$tenant, , $applicant] = enrollmentSchool('ulang-tanpa-nis');

    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => $nis])->assertSessionHasErrors('nis');

    expect(ppdbSchool($tenant, fn () => $applicant->fresh()->isEnrolled()))->toBeFalse();
})->with(['', '   ']);

it('lets another school use the same NIS', function () {
    [$tenant, , $applicant] = enrollmentSchool('ulang-sekolah-a');
    $other = ppdbTenant(slug: 'ulang-sekolah-b');
    ppdbSchool($other, fn () => app(StudentAdmission::class)->admit(new NewStudent('Siswa B', '20270011', 'L')));
    ppdbMember($tenant, 'admin-sekolah');

    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => '20270011'])->assertSessionHasNoErrors();

    expect(schoolStudents($tenant, '20270011'))->toHaveCount(1)
        ->and(schoolStudents($other, '20270011'))->toHaveCount(1);
});

it('lets the committee staff record it and keeps teachers and students out', function (string $role, int $status) {
    [$tenant, , $applicant] = enrollmentSchool("ulang-izin-{$role}", role: $role);

    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => '20270012'])->assertStatus($status);

    expect(ppdbSchool($tenant, fn () => $applicant->fresh()->isEnrolled()))->toBe($status === 302);
})->with([
    'admin' => ['admin-sekolah', 302],
    'staf' => ['staf-tu', 302],
    'guru' => ['guru', 403],
    'siswa' => ['siswa', 403],
]);

it('keeps another school\'s applicant out of reach', function () {
    [$tenant] = enrollmentSchool('ulang-akses-a');
    [$other, , $foreign] = enrollmentSchool('ulang-akses-b');
    ppdbMember($tenant, 'admin-sekolah');

    post(school($tenant->slug, "/ppdb/pendaftar/{$foreign->id}/daftar-ulang"), ['nis' => '20270013'])->assertNotFound();

    expect(ppdbSchool($other, fn () => $foreign->fresh()->isEnrolled()))->toBeFalse()
        ->and(schoolStudents($other, '20270013'))->toBe([]);
});

it('offers re-registration only to an accepted applicant of announced results, and only once', function () {
    [$tenant, $period, $applicant] = enrollmentSchool('ulang-tawaran');
    $show = fn () => get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"));

    $show()->assertInertia(fn (Assert $page) => $page
        ->where('can.enroll', true)
        ->where('applicant.enrolled', false)
        ->where('applicant.score', '88.40')
        ->where('applicant.decisionLabel', 'Diterima')
    );

    ppdbSchool($tenant, fn () => $period->update(['results_published_at' => null]));
    $show()->assertInertia(fn (Assert $page) => $page->where('can.enroll', false));

    ppdbSchool($tenant, fn () => $period->update(['results_published_at' => '2027-07-01 08:00:00']));
    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => '20270014']);

    $show()->assertInertia(fn (Assert $page) => $page
        ->where('can.enroll', false)
        ->where('applicant.enrolled', true)
        ->where('applicant.enrolledOn', fn (string $label) => $label !== '')
    );
});

it('tells an applicant who has re-registered on their own page', function () {
    [$tenant, , $applicant] = enrollmentSchool('ulang-akun');
    $account = ppdbAccount($tenant);
    ppdbSchool($tenant, fn () => $applicant->forceFill(['account_id' => $account->id])->save());
    ppdbMember($tenant, 'admin-sekolah');

    post(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/daftar-ulang"), ['nis' => '20270015'])->assertSessionHasNoErrors();

    \Pest\Laravel\actingAs($account, 'ppdb');
    get('http://localhost/calon-siswa')->assertInertia(fn (Assert $page) => $page
        ->where('portal.application.enrolled', true)
        ->where('portal.application.decision', 'accepted')
    );
});
