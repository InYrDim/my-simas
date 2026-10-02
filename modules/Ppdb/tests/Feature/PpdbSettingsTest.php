<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Pengaturan PPDB: periods, waves, paths with their quota, and the code
 * and link the school hands to applicants.
 */

it('shows the school code and the link applicants join with', function () {
    $tenant = ppdbTenant(slug: 'atur-kode');

    get(school($tenant->slug, '/ppdb/pengaturan'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Settings')
        ->where('school.code', $tenant->id)
        ->where('school.name', $tenant->name)
        ->where('school.joinUrl', fn (string $url) => str_contains($url, '/calon-siswa/gabung?school='.$tenant->id))
        ->where('selected', null)
        ->where('periods', [])
    );
});

it('creates a period with the usual paths and no seats', function () {
    $tenant = ppdbTenant(slug: 'atur-buat');

    $response = post(school($tenant->slug, '/ppdb/pengaturan/periode'), [
        'name' => 'PPDB 2027/2028',
        'entry_year' => 2027,
        'status' => 'draft',
    ]);

    $period = ppdbSchool($tenant, fn () => AdmissionPeriod::query()->sole());
    $paths = ppdbSchool($tenant, fn () => $period->paths->map(fn (AdmissionPath $path) => [$path->name, $path->quota])->all());

    $response->assertRedirect("/ppdb/pengaturan?periode={$period->id}");
    expect($period->name)->toBe('PPDB 2027/2028')
        ->and($period->entry_year)->toBe(2027)
        ->and($period->status)->toBe(PeriodStatus::Draft)
        ->and($paths)->toBe([['Zonasi', 0], ['Prestasi', 0], ['Afirmasi', 0], ['Mutasi', 0]]);
});

it('refuses a period without a name or with a year that makes no sense', function (array $data, string $field) {
    $tenant = ppdbTenant(slug: 'atur-validasi');

    post(school($tenant->slug, '/ppdb/pengaturan/periode'), $data)->assertSessionHasErrors($field);

    expect(ppdbSchool($tenant, fn () => AdmissionPeriod::query()->count()))->toBe(0);
})->with([
    'no name' => [['name' => '', 'entry_year' => 2027, 'status' => 'draft'], 'name'],
    'year in words' => [['name' => 'PPDB', 'entry_year' => 'tahun depan', 'status' => 'draft'], 'entry_year'],
    'year out of range' => [['name' => 'PPDB', 'entry_year' => 1900, 'status' => 'draft'], 'entry_year'],
    'unknown status' => [['name' => 'PPDB', 'entry_year' => 2027, 'status' => 'terbuka'], 'status'],
]);

it('keeps one active period: activating one closes the other', function () {
    $tenant = ppdbTenant(slug: 'atur-satu-aktif');
    $first = ppdbPeriod($tenant, ['name' => 'PPDB 2026', 'entry_year' => 2026, 'status' => PeriodStatus::Active]);
    $second = ppdbPeriod($tenant, ['name' => 'PPDB 2027']);

    put(school($tenant->slug, "/ppdb/pengaturan/periode/{$second->id}"), [
        'name' => 'PPDB 2027',
        'entry_year' => 2027,
        'status' => 'active',
    ])->assertRedirect();

    expect(ppdbSchool($tenant, fn () => $first->fresh()->status))->toBe(PeriodStatus::Closed)
        ->and(ppdbSchool($tenant, fn () => $second->fresh()->status))->toBe(PeriodStatus::Active)
        ->and(ppdbSchool($tenant, fn () => AdmissionPeriod::query()->where('status', 'active')->count()))->toBe(1);
});

it('closes the active period when a new period is created as active', function () {
    $tenant = ppdbTenant(slug: 'atur-baru-aktif');
    $old = ppdbPeriod($tenant, ['status' => PeriodStatus::Active]);

    post(school($tenant->slug, '/ppdb/pengaturan/periode'), [
        'name' => 'PPDB Baru',
        'entry_year' => 2028,
        'status' => 'active',
    ]);

    expect(ppdbSchool($tenant, fn () => $old->fresh()->status))->toBe(PeriodStatus::Closed)
        ->and(ppdbSchool($tenant, fn () => AdmissionPeriod::active()?->name))->toBe('PPDB Baru');
});

it('does not turn a period with announced results back into a draft', function () {
    $tenant = ppdbTenant(slug: 'atur-sudah-umum');
    $period = ppdbPeriod($tenant, ['status' => PeriodStatus::Closed, 'results_published_at' => '2027-07-01 08:00:00']);

    put(school($tenant->slug, "/ppdb/pengaturan/periode/{$period->id}"), [
        'name' => $period->name,
        'entry_year' => 2027,
        'status' => 'draft',
    ])->assertSessionHasErrors('status');

    expect(ppdbSchool($tenant, fn () => $period->fresh()->status))->toBe(PeriodStatus::Closed);
});

it('adds a wave and rejects dates that are backwards or overlap another wave', function () {
    $tenant = ppdbTenant(slug: 'atur-gelombang');
    $period = ppdbPeriod($tenant);
    ppdbWave($tenant, $period, ['name' => 'Gelombang 1', 'opens_on' => '2027-01-01', 'closes_on' => '2027-03-31']);
    $url = school($tenant->slug, "/ppdb/pengaturan/periode/{$period->id}/gelombang");

    post($url, ['name' => 'Gelombang 2', 'opens_on' => '2027-04-01', 'closes_on' => '2027-06-30'])->assertSessionHasNoErrors();
    post($url, ['name' => 'Mundur', 'opens_on' => '2027-09-10', 'closes_on' => '2027-09-01'])->assertSessionHasErrors('closes_on');
    post($url, ['name' => 'Tumpang', 'opens_on' => '2027-03-31', 'closes_on' => '2027-04-05'])->assertSessionHasErrors('opens_on');
    post($url, ['name' => 'Tanpa tanggal', 'opens_on' => '', 'closes_on' => ''])->assertSessionHasErrors(['opens_on', 'closes_on']);

    expect(ppdbSchool($tenant, fn () => AdmissionWave::query()->orderBy('opens_on')->pluck('name')->all()))->toBe(['Gelombang 1', 'Gelombang 2']);
});

it('lets a wave be changed without overlapping itself', function () {
    $tenant = ppdbTenant(slug: 'atur-ubah-gelombang');
    $period = ppdbPeriod($tenant);
    $wave = ppdbWave($tenant, $period, ['opens_on' => '2027-01-01', 'closes_on' => '2027-03-31']);

    put(school($tenant->slug, "/ppdb/pengaturan/gelombang/{$wave->id}"), [
        'name' => 'Gelombang Awal',
        'opens_on' => '2027-01-01',
        'closes_on' => '2027-04-15',
    ])->assertSessionHasNoErrors();

    $saved = ppdbSchool($tenant, fn () => $wave->fresh());

    expect($saved->name)->toBe('Gelombang Awal')
        ->and($saved->closes_on)->toBe('2027-04-15');
});

it('works out whether a wave is upcoming, open or closed on the school day', function () {
    $tenant = ppdbTenant(slug: 'atur-hari-sekolah');
    $period = ppdbPeriod($tenant, ['status' => PeriodStatus::Active]);
    ppdbWave($tenant, $period, ['name' => 'Lalu', 'opens_on' => '2027-01-01', 'closes_on' => '2027-02-09']);
    ppdbWave($tenant, $period, ['name' => 'Sekarang', 'opens_on' => '2027-02-10', 'closes_on' => '2027-02-20']);
    ppdbWave($tenant, $period, ['name' => 'Nanti', 'opens_on' => '2027-03-01', 'closes_on' => '2027-03-31']);

    // 17:30 UTC on the 9th is already the 10th in Makassar (UTC+8): the
    // wave that closed on the 9th is closed, though it is still the 9th in UTC.
    ppdbSchool($tenant, fn () => $tenant->forceFill(['timezone' => 'Asia/Makassar'])->save());
    $this->travelTo('2027-02-09 17:30:00');

    get(school($tenant->slug, '/ppdb/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('waves', fn ($waves) => collect($waves)->pluck('status', 'name')->all() === [
            'Lalu' => 'closed',
            'Sekarang' => 'open',
            'Nanti' => 'upcoming',
        ])
    );
});

it('saves the paths and their seats, in the order sent', function () {
    $tenant = ppdbTenant(slug: 'atur-jalur');
    $period = ppdbPeriod($tenant);
    $zonasi = ppdbPath($tenant, $period, ['name' => 'Zonasi', 'sort_order' => 0]);
    $prestasi = ppdbPath($tenant, $period, ['name' => 'Prestasi', 'sort_order' => 1]);

    put(school($tenant->slug, "/ppdb/pengaturan/periode/{$period->id}/jalur"), [
        'paths' => [
            ['id' => $prestasi->id, 'name' => 'Prestasi', 'quota' => 40],
            ['id' => $zonasi->id, 'name' => 'Zonasi Dekat', 'quota' => 90],
            ['id' => null, 'name' => 'Afirmasi', 'quota' => 15],
        ],
    ])->assertSessionHasNoErrors();

    $rows = ppdbSchool($tenant, fn () => $period->paths()->get()->map(fn (AdmissionPath $path) => [$path->name, $path->quota])->all());

    expect($rows)->toBe([['Prestasi', 40], ['Zonasi Dekat', 90], ['Afirmasi', 15]])
        ->and(ppdbSchool($tenant, fn () => $zonasi->fresh()->name))->toBe('Zonasi Dekat');
});

it('refuses repeated path names, negative seats and ids of another period', function () {
    $tenant = ppdbTenant(slug: 'atur-jalur-salah');
    $period = ppdbPeriod($tenant);
    $other = ppdbPeriod($tenant, ['name' => 'PPDB Lain']);
    $zonasi = ppdbPath($tenant, $period, ['name' => 'Zonasi', 'quota' => 10]);
    $foreign = ppdbPath($tenant, $other, ['name' => 'Milik Lain', 'quota' => 5]);
    $url = school($tenant->slug, "/ppdb/pengaturan/periode/{$period->id}/jalur");

    put($url, ['paths' => [['id' => $zonasi->id, 'name' => 'Zonasi', 'quota' => 10], ['id' => null, 'name' => 'zonasi', 'quota' => 5]]])
        ->assertSessionHasErrors('paths.1.name');
    put($url, ['paths' => [['id' => $zonasi->id, 'name' => 'Zonasi', 'quota' => -1]]])
        ->assertSessionHasErrors('paths.0.quota');
    put($url, ['paths' => [['id' => $foreign->id, 'name' => 'Diambil', 'quota' => 1]]])
        ->assertSessionHasErrors('paths.0.id');

    expect(ppdbSchool($tenant, fn () => $zonasi->fresh()->quota))->toBe(10)
        ->and(ppdbSchool($tenant, fn () => $foreign->fresh()->name))->toBe('Milik Lain')
        ->and(ppdbSchool($tenant, fn () => $period->paths()->count()))->toBe(1);
});

it('chooses the period shown: the one asked for, else the active one', function () {
    $tenant = ppdbTenant(slug: 'atur-pilih');
    $old = ppdbPeriod($tenant, ['name' => 'PPDB 2026', 'entry_year' => 2026, 'status' => PeriodStatus::Closed]);
    $current = ppdbPeriod($tenant, ['name' => 'PPDB 2027', 'entry_year' => 2027, 'status' => PeriodStatus::Active]);

    get(school($tenant->slug, '/ppdb/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('selected.id', $current->id)
        ->has('periods', 2)
    );

    get(school($tenant->slug, "/ppdb/pengaturan?periode={$old->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('selected.id', $old->id)
    );
});

it('lets only the school admin change the settings', function (string $role, int $status) {
    $tenant = ppdbTenant(role: $role, slug: "atur-izin-{$role}");

    get(school($tenant->slug, '/ppdb/pengaturan'))->assertStatus($status);
    post(school($tenant->slug, '/ppdb/pengaturan/periode'), ['name' => 'PPDB', 'entry_year' => 2027, 'status' => 'draft'])
        ->assertStatus($status === 200 ? 302 : 403);

    expect(ppdbSchool($tenant, fn () => AdmissionPeriod::query()->count()))->toBe($status === 200 ? 1 : 0);
})->with([
    'admin' => ['admin-sekolah', 200],
    'staf' => ['staf-tu', 403],
    'guru' => ['guru', 403],
]);

it('keeps another school out of the periods, waves and paths', function () {
    $tenant = ppdbTenant(slug: 'atur-sekolah-a');
    $other = ppdbTenant(slug: 'atur-sekolah-b');
    $foreignPeriod = ppdbPeriod($other, ['name' => 'Milik B']);
    $foreignWave = ppdbWave($other, $foreignPeriod);
    ppdbPeriod($tenant, ['name' => 'Milik A']);

    // The second ppdbTenant() signed in as the other school's admin; sign back in.
    ppdbMember($tenant, 'admin-sekolah');

    get(school($tenant->slug, '/ppdb/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('periods', fn ($periods) => collect($periods)->pluck('name')->all() === ['Milik A'])
    );

    put(school($tenant->slug, "/ppdb/pengaturan/periode/{$foreignPeriod->id}"), ['name' => 'Dibajak', 'entry_year' => 2027, 'status' => 'active'])->assertNotFound();
    put(school($tenant->slug, "/ppdb/pengaturan/gelombang/{$foreignWave->id}"), ['name' => 'Dibajak', 'opens_on' => '2027-01-01', 'closes_on' => '2027-01-02'])->assertNotFound();
    post(school($tenant->slug, "/ppdb/pengaturan/periode/{$foreignPeriod->id}/gelombang"), ['name' => 'Dibajak', 'opens_on' => '2027-01-01', 'closes_on' => '2027-01-02'])->assertNotFound();
    put(school($tenant->slug, "/ppdb/pengaturan/periode/{$foreignPeriod->id}/jalur"), ['paths' => [['id' => null, 'name' => 'Dibajak', 'quota' => 1]]])->assertNotFound();

    expect(ppdbSchool($other, fn () => AdmissionPeriod::query()->sole()->name))->toBe('Milik B')
        ->and(ppdbSchool($other, fn () => AdmissionWave::query()->sole()->name))->toBe('Gelombang 1');
});

it('shows the overview of the active period and its waves', function () {
    $tenant = ppdbTenant(slug: 'ringkasan-aktif');
    $period = ppdbPeriod($tenant, ['name' => 'PPDB 2027/2028', 'status' => PeriodStatus::Active]);
    ppdbWave($tenant, $period, ['name' => 'Gelombang 1', 'opens_on' => '2027-01-01', 'closes_on' => '2027-03-31']);
    ppdbWave($tenant, $period, ['name' => 'Gelombang 2', 'opens_on' => '2027-04-01', 'closes_on' => '2027-06-30']);

    get(school($tenant->slug, '/ppdb'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Overview')
        ->where('period.name', 'PPDB 2027/2028')
        ->where('period.status', 'active')
        ->where('period.closesOn', '30 Juni 2027')
        ->where('waves.0.opensOn', '1 Januari 2027')
        ->has('waves', 2)
        ->where('can.manageSettings', true)
    );
});

it('tells the overview there is no period when none is active', function () {
    $tenant = ppdbTenant(slug: 'ringkasan-kosong', role: 'staf-tu');
    ppdbPeriod($tenant, ['status' => PeriodStatus::Closed]);

    get(school($tenant->slug, '/ppdb'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('period', null)
        ->where('waves', [])
        ->where('can.manageSettings', false)
    );
});
