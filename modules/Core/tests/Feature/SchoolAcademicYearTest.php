<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Core\App\Domain\Models\Semester;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/**
 * Profil Sekolah, Tahun Ajaran and Semester: the first Core pages backed
 * by the database.
 */
// ---- Profil Sekolah

it('creates the school profile on first visit and shows it', function () {
    $tenant = schoolAs('prof-a');

    get(school($tenant->slug, '/master/sekolah'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/School/Show')
            ->where('school.level', 'sma')
            ->where('school.code', 'prof-a'));

    expect(inSchool($tenant, fn () => SchoolProfile::query()->count()))->toBe(1);
});

it('saves the school profile and shows the new level on every master page', function () {
    $tenant = schoolAs('prof-b');

    put(school($tenant->slug, '/master/sekolah'), [
        'level' => 'smk',
        'npsn' => '20512345',
        'ownership' => 'Negeri',
        'accreditation' => 'A',
        'address' => 'Jl. Pendidikan 1',
        'phone' => '022-555',
        'email' => 'info@prof-b.test',
        'headmaster' => 'Ibu Kepala',
        'headmaster_nip' => '1968',
    ])->assertRedirect();

    $profile = inSchool($tenant, fn () => SchoolProfile::query()->sole());
    expect($profile->level->value)->toBe('smk')->and($profile->headmaster)->toBe('Ibu Kepala');

    get(school($tenant->slug, '/master/tahun-ajaran'))
        ->assertInertia(fn (Assert $page) => $page->where('school.level', 'smk')->where('school.hasMajors', true));
});

it('validates the school profile', function () {
    $tenant = schoolAs('prof-c');

    put(school($tenant->slug, '/master/sekolah'), ['level' => 'tk', 'email' => 'bukan-email'])
        ->assertSessionHasErrors(['level', 'email']);
});

it('locks the jenjang once the school has a class but still saves the other fields', function () {
    $tenant = schoolAs('prof-g');

    get(school($tenant->slug, '/master/sekolah'))
        ->assertInertia(fn (Assert $page) => $page->where('levelLocked', false));

    classIn($tenant);

    get(school($tenant->slug, '/master/sekolah'))
        ->assertInertia(fn (Assert $page) => $page->where('levelLocked', true));

    put(school($tenant->slug, '/master/sekolah'), ['level' => 'sma', 'headmaster' => 'Kepala Baru'])
        ->assertSessionHasNoErrors();

    $profile = inSchool($tenant, fn () => SchoolProfile::query()->sole());
    expect($profile->headmaster)->toBe('Kepala Baru')->and($profile->level->value)->toBe('sma');
});

it('does not lock the jenjang because another school has classes', function () {
    classIn(schoolAs('prof-h'));
    $tenant = schoolAs('prof-i');

    put(school($tenant->slug, '/master/sekolah'), ['level' => 'sd'])->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => SchoolProfile::query()->sole()->level->value))->toBe('sd');
});

it('keeps each school profile separate', function () {
    $a = schoolAs('prof-d');
    put(school($a->slug, '/master/sekolah'), ['level' => 'sd', 'headmaster' => 'Kepala A'])->assertRedirect();

    $b = schoolAs('prof-e');
    get(school($b->slug, '/master/sekolah'))
        ->assertInertia(fn (Assert $page) => $page->where('school.level', 'sma')->where('school.headmaster', ''));
});

it('forbids a teacher from changing the school profile', function () {
    $tenant = schoolAs('prof-f', 'guru');

    put(school($tenant->slug, '/master/sekolah'), ['level' => 'sd'])->assertForbidden();
    get(school($tenant->slug, '/master/sekolah'))->assertOk();
});

// ---- Tahun Ajaran

it('creates a draft academic year with its two semesters', function () {
    $tenant = schoolAs('year-a');

    post(school($tenant->slug, '/master/tahun-ajaran'), [
        'name' => '2026/2027',
        'curriculum' => 'Kurikulum Merdeka',
        'start_date' => '2026-07-13',
        'end_date' => '2027-06-26',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $year = inSchool($tenant, fn () => AcademicYear::query()->with('semesters')->sole());

    expect($year->status)->toBe(AcademicYearStatus::Draft)
        ->and($year->semesters->pluck('name')->all())->toBe(['Ganjil', 'Genap'])
        ->and($year->semesters[0]->start_date->toDateString())->toBe('2026-07-13')
        ->and($year->semesters[1]->end_date->toDateString())->toBe('2027-06-26')
        ->and($year->semesters[0]->end_date->lt($year->semesters[1]->start_date))->toBeTrue();
});

it('lists academic years newest first with their semesters', function () {
    $tenant = schoolAs('year-b');
    yearWithSemesters($tenant, 'archived', '2024-07-15');
    yearWithSemesters($tenant, 'active', '2025-07-14');

    get(school($tenant->slug, '/master/tahun-ajaran'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/AcademicYears/Index')
            ->has('years', 2)
            ->where('years.0.name', '2025/2026')
            ->where('years.0.status', 'active')
            ->has('years.0.semesters', 2));
});

it('rejects an invalid or duplicate academic year', function () {
    $tenant = schoolAs('year-c');
    yearWithSemesters($tenant, 'draft', '2026-07-13');

    post(school($tenant->slug, '/master/tahun-ajaran'), [
        'name' => '2026/2027', 'curriculum' => 'K', 'start_date' => '2026-07-13', 'end_date' => '2027-06-26',
    ])->assertSessionHasErrors('name');

    post(school($tenant->slug, '/master/tahun-ajaran'), [
        'name' => '2027-2028', 'curriculum' => 'K', 'start_date' => '2027-07-13', 'end_date' => '2027-01-01',
    ])->assertSessionHasErrors(['name', 'end_date']);
});

it('allows the same academic year name in another school', function () {
    $a = schoolAs('year-d');
    yearWithSemesters($a, 'draft', '2026-07-13');

    $b = schoolAs('year-e');
    post(school($b->slug, '/master/tahun-ajaran'), [
        'name' => '2026/2027', 'curriculum' => 'K', 'start_date' => '2026-07-13', 'end_date' => '2027-06-26',
    ])->assertSessionHasNoErrors();
});

it('keeps only one academic year active', function () {
    $tenant = schoolAs('year-f');
    $old = yearWithSemesters($tenant, 'active', '2025-07-14');
    $next = yearWithSemesters($tenant, 'draft', '2026-07-13');

    post(school($tenant->slug, "/master/tahun-ajaran/{$next->id}/aktifkan"))->assertRedirect();

    expect(inSchool($tenant, fn () => $old->fresh()->status))->toBe(AcademicYearStatus::Archived)
        ->and(inSchool($tenant, fn () => $next->fresh()->status))->toBe(AcademicYearStatus::Active);
});

it('refuses to reactivate an archived year', function () {
    $tenant = schoolAs('year-g');
    $archived = yearWithSemesters($tenant, 'archived', '2024-07-15');

    post(school($tenant->slug, "/master/tahun-ajaran/{$archived->id}/aktifkan"))->assertSessionHasErrors('status');
});

it('deletes only a draft year together with its semesters', function () {
    $tenant = schoolAs('year-h');
    $draft = yearWithSemesters($tenant, 'draft', '2026-07-13');
    $active = yearWithSemesters($tenant, 'active', '2025-07-14');

    delete(school($tenant->slug, "/master/tahun-ajaran/{$active->id}"))->assertSessionHasErrors('status');
    delete(school($tenant->slug, "/master/tahun-ajaran/{$draft->id}"))->assertRedirect();

    expect(inSchool($tenant, fn () => AcademicYear::query()->count()))->toBe(1)
        ->and(inSchool($tenant, fn () => Semester::query()->count()))->toBe(2);
});

it('refuses to move a year past its semesters', function () {
    $tenant = schoolAs('year-i');
    $year = yearWithSemesters($tenant, 'draft', '2026-07-13');

    put(school($tenant->slug, "/master/tahun-ajaran/{$year->id}"), [
        'name' => '2026/2027', 'curriculum' => 'K', 'start_date' => '2026-08-01', 'end_date' => '2027-06-26',
    ])->assertSessionHasErrors('start_date');

    put(school($tenant->slug, "/master/tahun-ajaran/{$year->id}"), [
        'name' => '2026/2027', 'curriculum' => 'Kurikulum 2013', 'start_date' => '2026-07-01', 'end_date' => '2027-07-10',
    ])->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => $year->fresh()->curriculum))->toBe('Kurikulum 2013');
});

it('cannot reach another school\'s academic year', function () {
    $other = TenantFactory::new()->create(['slug' => 'year-other']);
    $foreign = yearWithSemesters($other, 'draft', '2026-07-13');

    $tenant = schoolAs('year-j');

    put(school($tenant->slug, "/master/tahun-ajaran/{$foreign->id}"), [
        'name' => '2026/2027', 'curriculum' => 'K', 'start_date' => '2026-07-13', 'end_date' => '2027-06-26',
    ])->assertNotFound();
    delete(school($tenant->slug, "/master/tahun-ajaran/{$foreign->id}"))->assertNotFound();
});

it('forbids a teacher from changing academic years', function () {
    $tenant = schoolAs('year-k', 'guru');
    $year = yearWithSemesters($tenant, 'draft', '2026-07-13');

    post(school($tenant->slug, '/master/tahun-ajaran'), ['name' => '2027/2028'])->assertForbidden();
    post(school($tenant->slug, "/master/tahun-ajaran/{$year->id}/aktifkan"))->assertForbidden();
    delete(school($tenant->slug, "/master/tahun-ajaran/{$year->id}"))->assertForbidden();
});

// ---- Semester

it('derives each semester status and week from the school\'s clock', function () {
    $tenant = schoolAs('sem-a');
    yearWithSemesters($tenant, 'archived', '2024-07-15');
    yearWithSemesters($tenant, 'active', '2025-07-14');
    yearWithSemesters($tenant, 'draft', '2026-07-13');

    $this->travelTo('2026-03-30 10:00:00');

    get(school($tenant->slug, '/master/semester'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Master/Semesters/Index')
            ->has('semesters', 6)
            ->where('semesters', fn ($rows) => collect($rows)->map(fn ($row) => "{$row['year']} {$row['name']} {$row['status']}")->all() === [
                '2026/2027 Genap upcoming',
                '2026/2027 Ganjil upcoming',
                '2025/2026 Genap current',
                '2025/2026 Ganjil finished',
                '2024/2025 Genap finished',
                '2024/2025 Ganjil finished',
            ])
            ->where('semesters.2.week', 13)
            ->where('semesters.3.week', null));
});

it('updates a semester inside its academic year', function () {
    $tenant = schoolAs('sem-b');
    $year = yearWithSemesters($tenant, 'draft', '2026-07-13');
    $ganjil = inSchool($tenant, fn () => $year->semesters()->where('name', 'Ganjil')->sole());

    put(school($tenant->slug, "/master/semester/{$ganjil->id}"), [
        'start_date' => '2026-07-14', 'end_date' => '2026-12-18',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => $ganjil->fresh()->end_date->toDateString()))->toBe('2026-12-18');
});

it('refuses semester dates outside the year or overlapping the other semester', function () {
    $tenant = schoolAs('sem-c');
    $year = yearWithSemesters($tenant, 'draft', '2026-07-13');
    $ganjil = inSchool($tenant, fn () => $year->semesters()->where('name', 'Ganjil')->sole());

    put(school($tenant->slug, "/master/semester/{$ganjil->id}"), [
        'start_date' => '2026-06-01', 'end_date' => '2026-12-18',
    ])->assertSessionHasErrors('start_date');

    put(school($tenant->slug, "/master/semester/{$ganjil->id}"), [
        'start_date' => '2026-07-13', 'end_date' => '2027-01-10',
    ])->assertSessionHasErrors('start_date');
});

it('forbids a teacher from changing a semester', function () {
    $tenant = schoolAs('sem-d', 'guru');
    $year = yearWithSemesters($tenant, 'draft', '2026-07-13');
    $ganjil = inSchool($tenant, fn () => $year->semesters()->where('name', 'Ganjil')->sole());

    put(school($tenant->slug, "/master/semester/{$ganjil->id}"), [
        'start_date' => '2026-07-14', 'end_date' => '2026-12-18',
    ])->assertForbidden();
});

// ---- Saran tahun ajaran

it('suggests the running academic year for a school without any', function () {
    $tenant = schoolAs('sugg-a');

    $this->travelTo('2026-10-01 09:00:00');
    get(school($tenant->slug, '/master/tahun-ajaran'))->assertInertia(fn (Assert $page) => $page
        ->where('suggestion.name', '2026/2027')
        ->where('suggestion.start_date', '2026-07-13')
        ->where('suggestion.end_date', '2027-06-26')
        ->where('suggestion.curriculum', 'Kurikulum Merdeka'));

    $this->travelTo('2026-03-10 09:00:00');
    get(school($tenant->slug, '/master/tahun-ajaran'))->assertInertia(fn (Assert $page) => $page
        ->where('suggestion.name', '2025/2026')
        ->where('suggestion.start_date', '2025-07-13'));
});

it('suggests the year after the latest one and inherits its curriculum', function () {
    $tenant = schoolAs('sugg-b');
    $year = yearWithSemesters($tenant, 'active', '2025-07-14');
    inSchool($tenant, fn () => $year->forceFill(['curriculum' => 'Kurikulum 2013'])->save());

    get(school($tenant->slug, '/master/tahun-ajaran'))->assertInertia(fn (Assert $page) => $page
        ->where('suggestion.name', '2026/2027')
        ->where('suggestion.curriculum', 'Kurikulum 2013')
        ->where('suggestion.start_date', '2026-07-14')
        ->where('suggestion.end_date', '2027-06-26'));
});

it('skips a suggested name that is already taken', function () {
    $tenant = schoolAs('sugg-c');
    yearWithSemesters($tenant, 'draft', '2026-07-13');
    inSchool($tenant, fn () => AcademicYear::factory()->archived()->create([
        'name' => '2027/2028', 'start_date' => '2020-07-13', 'end_date' => '2021-06-26',
    ]));

    get(school($tenant->slug, '/master/tahun-ajaran'))->assertInertia(fn (Assert $page) => $page
        ->where('suggestion.name', '2028/2029')
        ->where('suggestion.start_date', '2028-07-13'));
});

it('builds the suggestion from this school only', function () {
    $other = TenantFactory::new()->create(['slug' => 'sugg-other']);
    yearWithSemesters($other, 'active', '2030-07-14');

    $tenant = schoolAs('sugg-d');
    $this->travelTo('2026-10-01 09:00:00');

    get(school($tenant->slug, '/master/tahun-ajaran'))->assertInertia(fn (Assert $page) => $page
        ->where('suggestion.name', '2026/2027'));
});

it('accepts the suggestion as it is', function () {
    $tenant = schoolAs('sugg-e');
    yearWithSemesters($tenant, 'active', '2025-07-14');

    $suggestion = get(school($tenant->slug, '/master/tahun-ajaran'))->viewData('page')['props']['suggestion'];

    post(school($tenant->slug, '/master/tahun-ajaran'), $suggestion)->assertRedirect()->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => AcademicYear::query()->count()))->toBe(2)
        ->and(inSchool($tenant, fn () => Semester::query()->count()))->toBe(4);
});
