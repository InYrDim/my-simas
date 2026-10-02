<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\ReportRegistry;
use Modules\Core\App\Contracts\StatisticsRegistry;
use Modules\Core\App\Infrastructure\Insight\DefaultReportRegistry;
use Modules\Core\App\Infrastructure\Insight\DefaultStatisticsRegistry;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/insight.php';

/*
 * The registries behind Statistik & Laporan: what a module registers
 * through Core's contracts, and what the signed-in user gets to see.
 */
it('lists a registered report in its group, ahead of the announced ones', function () {
    $tenant = schoolAs('registry-katalog');
    app(ReportRegistry::class)->register('core', LibraryLoansReport::class);

    $catalogue = inSchool($tenant, fn () => app(DefaultReportRegistry::class)->catalogue());

    expect(array_column($catalogue, 'title'))->toBe(['Perpustakaan', 'Kesiswaan', 'Akademik', 'Penerimaan (PPDB)'])
        ->and($catalogue[0]['reports'])->toBe([
            ['key' => 'library-loans', 'name' => 'Peminjaman Buku', 'description' => 'Buku yang sedang dipinjam.', 'available' => true],
        ])
        ->and(array_column($catalogue[2]['reports'], 'available', 'key'))->toBe(['teaching-load' => true, 'schedule' => false])
        ->and(array_column($catalogue[3]['reports'], 'available', 'key'))->toBe(['ppdb-applicants' => false, 'ppdb-result' => false]);
});

it('hides a report from a user without its permission', function () {
    $tenant = schoolAs('registry-izin', 'guru');
    app(ReportRegistry::class)->register('core', LibraryLoansReport::class);
    app(ReportRegistry::class)->register('core', ManagersOnlyReport::class);

    $catalogue = inSchool($tenant, fn () => app(DefaultReportRegistry::class)->catalogue());

    expect(array_column($catalogue[0]['reports'], 'key'))->toBe(['library-loans']);
});

it('replaces an announced report once a module registers its key', function () {
    $tenant = schoolAs('registry-ganti');
    app(ReportRegistry::class)->register('core', PpdbApplicantsReport::class);

    $registry = app(DefaultReportRegistry::class);
    $admissions = inSchool($tenant, fn () => collect($registry->catalogue())->firstWhere('title', 'Penerimaan (PPDB)'));

    expect(array_column($admissions['reports'], 'available', 'key'))->toBe(['ppdb-applicants' => true, 'ppdb-result' => false])
        ->and(inSchool($tenant, fn () => $registry->find('ppdb-applicants')))->toBeInstanceOf(PpdbApplicantsReport::class);
});

it('offers nothing from a module the school has not enabled', function () {
    $tenant = schoolAs('registry-modul');
    app(ReportRegistry::class)->register('ppdb', PpdbApplicantsReport::class);

    $registry = app(DefaultReportRegistry::class);
    $admissions = inSchool($tenant, fn () => collect($registry->catalogue())->firstWhere('title', 'Penerimaan (PPDB)'));

    expect(array_column($admissions['reports'], 'key'))->toBe(['ppdb-result'])
        ->and(inSchool($tenant, fn () => $registry->find('ppdb-applicants')))->toBeNull();
});

it('finds no report for an unknown or merely announced key', function (string $key) {
    $tenant = schoolAs('registry-cari');

    expect(inSchool($tenant, fn () => app(DefaultReportRegistry::class)->find($key)))->toBeNull();
})->with(['tidak-ada', 'schedule']);

it('marks the announced figures and panels until a provider supplies them', function () {
    $tenant = schoolAs('registry-statistik');
    $registry = app(DefaultStatisticsRegistry::class);
    $period = new ReportPeriod(1, '2026/2027', '2026-07-13', '2027-06-26');

    $announced = inSchool($tenant, fn () => collect($registry->figures($period))->firstWhere('key', 'attendance-rate'));

    expect($announced)->toBe(['key' => 'attendance-rate', 'label' => 'Rata-rata kehadiran', 'value' => null, 'hint' => null, 'available' => false]);

    app(StatisticsRegistry::class)->register('core', AttendanceStatistics::class);

    $figure = inSchool($tenant, fn () => collect($registry->figures($period))->firstWhere('key', 'attendance-rate'));
    $panel = inSchool($tenant, fn () => collect($registry->panels($period))->firstWhere('key', 'attendance-trend'));

    expect($figure)->toBe(['key' => 'attendance-rate', 'label' => 'Rata-rata kehadiran', 'value' => '94,6%', 'hint' => '2026/2027', 'available' => true])
        ->and($panel['available'])->toBeTrue()
        ->and($panel['points'])->toBe([['label' => 'Sep', 'value' => 94.6]]);
});

it('skips the statistics of a module the school has not enabled', function () {
    $tenant = schoolAs('registry-statistik-modul');
    app(StatisticsRegistry::class)->register('attendance', AttendanceStatistics::class);

    $figure = inSchool($tenant, fn () => collect(app(DefaultStatisticsRegistry::class)->figures(null))->firstWhere('key', 'attendance-rate'));

    expect($figure['available'])->toBeFalse();
});
