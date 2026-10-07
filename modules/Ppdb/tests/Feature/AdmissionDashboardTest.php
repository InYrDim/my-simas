<?php

namespace Modules\Ppdb\Tests\Feature;

use Modules\Ppdb\App\Domain\Models\Applicant;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Admissions on the Beranda: the committee sees the running period's
 * applicants and who still waits for verification, through Core's
 * DashboardRegistry.
 */

/** The deferred widgets of the Beranda, as the page would receive them. */
function ppdbBerandaWidgets($tenant, callable $assert): void
{
    get(school($tenant->slug, '/beranda'))->assertOk()->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $assert($page)));
}

it('shows the committee the applicants of the running period and who waits longest', function () {
    $tenant = ppdbTenant(slug: 'dash-ppdb');
    [$period, $wave, $path] = ppdbSetup($tenant);

    ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Baru', 'number' => 'PPDB-27-0002', 'registered_on' => '2027-01-20']);
    ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Lama', 'number' => 'PPDB-27-0001', 'registered_on' => '2027-01-12']);
    ppdbSchool($tenant, fn () => Applicant::factory()->verified()->create([
        'number' => 'PPDB-27-0003', 'period_id' => $period->id, 'wave_id' => $wave->id, 'path_id' => $path->id,
    ]));

    ppdbBerandaWidgets($tenant, fn ($page) => $page
        ->where('widgets.figures', fn ($figures) => collect($figures)->pluck('payload.value', 'key')->all() === [
            'ppdb.applicants-total' => 3,
            'ppdb.awaiting-count' => 2,
        ])
        ->where('widgets.attention.0.key', 'ppdb.awaiting-list')
        ->where('widgets.attention.0.payload.items.0.label', 'Lama')
        ->where('widgets.attention.0.payload.items.0.detail', 'PPDB-27-0001 · mendaftar 2027-01-12')
        ->where('widgets.attention.0.payload.items.1.label', 'Baru'));
});

it('shows at most five waiting applicants and the real count with a link to the rest', function () {
    $tenant = ppdbTenant(slug: 'dash-ppdb-batas');
    [$period, $wave, $path] = ppdbSetup($tenant);

    foreach (range(1, 7) as $n) {
        ppdbApplicant($tenant, $period, $wave, $path, ['name' => "Calon {$n}", 'number' => "PPDB-27-000{$n}", 'registered_on' => '2027-01-1'.$n]);
    }

    ppdbBerandaWidgets($tenant, fn ($page) => $page
        ->has('widgets.attention.0.payload.items', 5)
        ->where('widgets.attention.0.payload.total', 7)
        ->where('widgets.attention.0.href', '/ppdb/pendaftar'));
});

it('tells the committee when nobody waits', function () {
    $tenant = ppdbTenant(slug: 'dash-ppdb-beres');
    ppdbSetup($tenant);

    ppdbBerandaWidgets($tenant, fn ($page) => $page
        ->where('widgets.attention.0.payload.items', [])
        ->where('widgets.attention.0.payload.empty', 'Tidak ada pendaftar yang menunggu verifikasi.'));
});

it('shows nothing before the school has an admissions period', function () {
    $tenant = ppdbTenant(slug: 'dash-ppdb-kosong');

    ppdbBerandaWidgets($tenant, fn ($page) => $page->where('widgets', fn ($widgets) => ! collect($widgets)->flatten(1)->pluck('key')->contains(fn ($key) => str_starts_with($key, 'ppdb.'))));
});

it('shows nothing of admissions to a role without PPDB access', function () {
    $tenant = ppdbTenant(role: 'guru', slug: 'dash-ppdb-guru');
    ppdbSetup($tenant);

    ppdbBerandaWidgets($tenant, fn ($page) => $page->where('widgets', fn ($widgets) => ! collect($widgets)->flatten(1)->pluck('key')->contains(fn ($key) => str_starts_with($key, 'ppdb.'))));
});

it('shows nothing in a school without the module', function () {
    $tenant = ppdbTenant(enabled: false, slug: 'dash-ppdb-mati');

    ppdbBerandaWidgets($tenant, fn ($page) => $page->where('widgets', []));
});
