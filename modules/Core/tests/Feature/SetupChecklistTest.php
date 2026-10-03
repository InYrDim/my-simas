<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Domain\Actions\SaveSchoolProfile;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Major;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Queries\SetupChecklist;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

require_once __DIR__.'/Support/helpers.php';

/*
 * The setup checklist: what a school still has to fill in, in the order
 * its data depends on itself. Everything is computed from the school's
 * own data.
 */

function setupTenant(string $slug = 'sekolah-setup'): Tenant
{
    return TenantFactory::new()->create(['slug' => $slug]);
}

/**
 * @return array{done: int, total: int, unplaced: int, steps: list<array{key: string, label: string, hint: string|null, state: string, optional: bool}>}|null
 */
function setupChecklistOf(Tenant $tenant): ?array
{
    return inSchool($tenant, fn () => app(SetupChecklist::class)->forCurrentSchool());
}

/** @return array{key: string, label: string, hint: string|null, state: string, optional: bool} */
function setupStep(?array $checklist, string $key): array
{
    foreach ($checklist['steps'] ?? [] as $step) {
        if ($step['key'] === $key) {
            return $step;
        }
    }

    throw new \LogicException("No setup step [{$key}].");
}

function setupSaveProfile(Tenant $tenant, string $level = 'sma'): void
{
    inSchool($tenant, fn () => app(SaveSchoolProfile::class)->handle(['level' => $level]));
}

it('starts a new school at the profile, the rest waiting or open', function () {
    $checklist = setupChecklistOf(setupTenant());

    // SMA is the default level, so majors are required: seven required steps.
    expect($checklist['done'])->toBe(0)
        ->and($checklist['total'])->toBe(7)
        ->and(setupStep($checklist, 'profile')['state'])->toBe('next')
        ->and(setupStep($checklist, 'year')['state'])->toBe('todo')
        ->and(setupStep($checklist, 'subjects')['state'])->toBe('todo')
        ->and(setupStep($checklist, 'teachers')['state'])->toBe('todo')
        ->and(setupStep($checklist, 'classes')['state'])->toBe('blocked')
        ->and(setupStep($checklist, 'classes')['hint'])->toBe('Menunggu: profil sekolah, tahun ajaran aktif, jurusan.')
        ->and(setupStep($checklist, 'students')['state'])->toBe('blocked')
        ->and(setupStep($checklist, 'students')['hint'])->toBe('Menunggu: kelas.')
        ->and(setupStep($checklist, 'rooms')['state'])->toBe('optional')
        ->and(setupStep($checklist, 'extracurriculars')['state'])->toBe('optional');
});

it('does not create a profile row just by reading the checklist', function () {
    $tenant = setupTenant();

    setupChecklistOf($tenant);

    expect(inSchool($tenant, fn () => SchoolProfile::query()->count()))->toBe(0);
});

it('counts the profile as done once its grades exist and moves on to the year', function () {
    $tenant = setupTenant();
    setupSaveProfile($tenant);

    $checklist = setupChecklistOf($tenant);

    expect(setupStep($checklist, 'profile')['state'])->toBe('done')
        ->and($checklist['done'])->toBe(1)
        ->and(setupStep($checklist, 'year')['state'])->toBe('next');
});

it('counts only an active academic year, and says so when only a draft exists', function () {
    $tenant = setupTenant();
    yearWithSemesters($tenant, 'draft');

    $withDraft = setupChecklistOf($tenant);

    expect(setupStep($withDraft, 'year')['state'])->not->toBe('done')
        ->and(setupStep($withDraft, 'year')['hint'])->toContain('berstatus draf');

    inSchool($tenant, fn () => AcademicYear::query()->update(['status' => 'active']));

    expect(setupStep(setupChecklistOf($tenant), 'year')['state'])->toBe('done');
});

it('does not count a class that belongs to another year than the active one', function () {
    $tenant = setupTenant();
    setupSaveProfile($tenant);
    yearWithSemesters($tenant, 'active');

    inSchool($tenant, function (): void {
        $archived = AcademicYear::factory()->archived()->create(['name' => '2024/2025']);
        ClassGroup::factory()->create([
            'academic_year_id' => $archived->id,
            'grade_id' => Grade::query()->firstOrFail()->id,
        ]);
    });

    expect(setupStep(setupChecklistOf($tenant), 'classes')['state'])->not->toBe('done');
});

it('asks for majors only at levels that have them, and makes classes wait for them', function () {
    $sd = setupTenant('sd-contoh');
    setupSaveProfile($sd, 'sd');
    yearWithSemesters($sd, 'active');

    $primary = setupChecklistOf($sd);

    expect(setupStep($primary, 'majors')['state'])->toBe('optional')
        ->and($primary['total'])->toBe(6)
        ->and(setupStep($primary, 'classes')['state'])->not->toBe('blocked');

    $sma = setupTenant('sma-contoh');
    setupSaveProfile($sma, 'sma');
    yearWithSemesters($sma, 'active');

    $secondary = setupChecklistOf($sma);

    expect(setupStep($secondary, 'majors')['state'])->not->toBe('optional')
        ->and(setupStep($secondary, 'classes')['state'])->toBe('blocked')
        ->and(setupStep($secondary, 'classes')['hint'])->toBe('Menunggu: jurusan.');

    inSchool($sma, fn () => Major::factory()->create());

    expect(setupStep(setupChecklistOf($sma), 'classes')['state'])->not->toBe('blocked');
});

it('counts a student only once placed in a class of the active year, and reports the unplaced', function () {
    $tenant = setupTenant();
    setupSaveProfile($tenant, 'sd');
    yearWithSemesters($tenant, 'active');
    $class = classIn($tenant);

    inSchool($tenant, function (): void {
        Student::factory()->create(['class_id' => null]);
        Student::factory()->create(['class_id' => null]);
        // Not active, so neither placed nor waiting for a class.
        Student::factory()->status('graduated')->create();
    });

    $waiting = setupChecklistOf($tenant);

    expect($waiting['unplaced'])->toBe(2)
        ->and(setupStep($waiting, 'students')['state'])->not->toBe('done')
        ->and(setupStep($waiting, 'students')['hint'])->toBe('2 siswa belum ditempatkan di kelas.');

    inSchool($tenant, fn () => Student::factory()->create(['class_id' => $class->id]));

    expect(setupStep(setupChecklistOf($tenant), 'students')['state'])->toBe('done');
});

it('has nothing to show once every required step is done', function () {
    $tenant = setupTenant();
    setupSaveProfile($tenant, 'sd');
    yearWithSemesters($tenant, 'active');
    $class = classIn($tenant);

    inSchool($tenant, function () use ($class): void {
        Subject::factory()->create();
        Teacher::factory()->create();
        Student::factory()->create(['class_id' => $class->id]);
    });

    expect(setupChecklistOf($tenant))->toBeNull();
});

it('reads only its own school', function () {
    $finished = setupTenant('sekolah-lengkap');
    setupSaveProfile($finished, 'sd');
    yearWithSemesters($finished, 'active');
    $class = classIn($finished);
    inSchool($finished, function () use ($class): void {
        Subject::factory()->create();
        Teacher::factory()->create();
        Student::factory()->create(['class_id' => $class->id]);
    });

    $empty = setupChecklistOf(setupTenant('sekolah-kosong'));

    expect(setupChecklistOf($finished))->toBeNull()
        ->and($empty['done'])->toBe(0)
        ->and(setupStep($empty, 'profile')['state'])->toBe('next');
});
