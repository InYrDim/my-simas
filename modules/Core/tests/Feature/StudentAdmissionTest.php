<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Contracts\DTOs\NewStudent;
use Modules\Core\App\Contracts\Exceptions\StudentAdmissionRefusedException;
use Modules\Core\App\Contracts\StudentAdmission;
use Modules\Core\App\Domain\Models\Student;

require_once __DIR__.'/Support/helpers.php';

/*
 * The contract a feature module (PPDB) uses to admit a new student: the
 * student starts active, without a class and without a login account.
 */

it('admits a student who is active, without a class and without an account', function () {
    $tenant = schoolAs('terima-siswa');

    $id = inSchool($tenant, fn () => app(StudentAdmission::class)->admit(new NewStudent(
        name: 'Nadia Putri',
        nis: '7001',
        gender: 'P',
        nisn: '0071234567',
        birthDate: '2012-05-04',
        guardianName: 'Budi Santoso',
        guardianPhone: '081234567890',
    )));

    $student = inSchool($tenant, fn () => Student::query()->findOrFail($id));

    expect($student->name)->toBe('Nadia Putri')
        ->and($student->nis)->toBe('7001')
        ->and($student->nisn)->toBe('0071234567')
        ->and($student->gender)->toBe('P')
        ->and($student->birth_date->format('Y-m-d'))->toBe('2012-05-04')
        ->and($student->guardian_name)->toBe('Budi Santoso')
        ->and($student->guardian_phone)->toBe('081234567890')
        ->and($student->status)->toBe('active')
        ->and($student->class_id)->toBeNull()
        ->and($student->user_id)->toBeNull()
        ->and($student->tenant_id)->toBe($tenant->id);
});

it('admits a student with only the required fields', function () {
    $tenant = schoolAs('terima-minimal');

    $id = inSchool($tenant, fn () => app(StudentAdmission::class)->admit(new NewStudent('Rafi', '7002', 'L')));

    $student = inSchool($tenant, fn () => Student::query()->findOrFail($id));

    expect($student->nisn)->toBeNull()
        ->and($student->birth_date)->toBeNull()
        ->and($student->guardian_name)->toBeNull();
});

it('refuses an NIS that the school already uses and stores nothing', function () {
    $tenant = schoolAs('terima-nis-ganda');
    inSchool($tenant, fn () => Student::factory()->create(['nis' => '7003']));

    $attempt = fn () => inSchool($tenant, fn () => app(StudentAdmission::class)->admit(new NewStudent('Baru', '7003', 'L')));

    expect($attempt)->toThrow(fn (StudentAdmissionRefusedException $exception) => $exception->column === 'nis');
    expect(inSchool($tenant, fn () => Student::query()->count()))->toBe(1);
});

it('refuses an NISN that the school already uses', function () {
    $tenant = schoolAs('terima-nisn-ganda');
    inSchool($tenant, fn () => Student::factory()->create(['nis' => '7004', 'nisn' => '0079999999']));

    $attempt = fn () => inSchool($tenant, fn () => app(StudentAdmission::class)->admit(new NewStudent('Baru', '7005', 'P', nisn: '0079999999')));

    expect($attempt)->toThrow(fn (StudentAdmissionRefusedException $exception) => $exception->column === 'nisn');
    expect(inSchool($tenant, fn () => Student::query()->count()))->toBe(1);
});

it('lets two schools use the same NIS', function () {
    $first = schoolAs('terima-sekolah-a');
    $second = schoolAs('terima-sekolah-b');
    inSchool($first, fn () => Student::factory()->create(['nis' => '7006']));

    $id = inSchool($second, fn () => app(StudentAdmission::class)->admit(new NewStudent('Siswa B', '7006', 'L')));

    expect(inSchool($second, fn () => Student::query()->findOrFail($id)->tenant_id))->toBe($second->id)
        ->and(inSchool($first, fn () => Student::query()->count()))->toBe(1);
});
