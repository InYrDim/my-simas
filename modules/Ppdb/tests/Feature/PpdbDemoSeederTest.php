<?php

namespace Modules\Ppdb\Tests\Feature;

use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\Database\Seeders\PpdbDemoSeeder;

require_once __DIR__.'/Support/helpers.php';

it('fills a school that uses PPDB with a sample period, once', function () {
    $tenant = ppdbTenant(slug: 'demo-ppdb');
    $without = ppdbTenant(enabled: false, slug: 'demo-tanpa-ppdb');

    $this->seed(PpdbDemoSeeder::class);
    $this->seed(PpdbDemoSeeder::class);

    $period = ppdbSchool($tenant, fn () => AdmissionPeriod::query()->sole());
    $applicants = ppdbSchool($tenant, fn () => Applicant::query()->orderBy('number')->get());

    expect($period->name)->toBe('PPDB 2027/2028')
        ->and($period->status)->toBe(PeriodStatus::Active)
        ->and($period->results_published_at)->toBeNull()
        ->and(ppdbSchool($tenant, fn () => AdmissionWave::query()->orderBy('opens_on')->pluck('name')->all()))->toBe(['Gelombang 1', 'Gelombang 2', 'Gelombang 3'])
        ->and(ppdbSchool($tenant, fn () => AdmissionPath::query()->orderBy('sort_order')->pluck('quota', 'name')->all()))->toBe(['Zonasi' => 3, 'Prestasi' => 2, 'Afirmasi' => 1, 'Mutasi' => 1])
        ->and($applicants)->toHaveCount(8)
        ->and($applicants->first()->number)->toBe('PPDB-27-0001')
        ->and($applicants->pluck('number')->unique())->toHaveCount(8)
        ->and($applicants->where('status', ApplicantStatus::Revision))->toHaveCount(1)
        ->and($applicants->where('decision', Decision::Accepted))->toHaveCount(3)
        // Within each path's quota.
        ->and($applicants->where('decision', Decision::Accepted)->groupBy('path_id')->map->count()->max())->toBeLessThanOrEqual(2)
        ->and(ppdbSchool($without, fn () => AdmissionPeriod::query()->count()))->toBe(0);
});

it('leaves a school that already has a period alone', function () {
    $tenant = ppdbTenant(slug: 'demo-ppdb-ada');
    ppdbPeriod($tenant, ['name' => 'PPDB Milik Sekolah']);

    $this->seed(PpdbDemoSeeder::class);

    expect(ppdbSchool($tenant, fn () => AdmissionPeriod::query()->pluck('name')->all()))->toBe(['PPDB Milik Sekolah'])
        ->and(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0);
});

it('gives the same sample to every school', function () {
    $first = ppdbTenant(slug: 'demo-ppdb-satu');
    $second = ppdbTenant(slug: 'demo-ppdb-dua');

    $this->seed(PpdbDemoSeeder::class);

    $names = fn ($tenant) => ppdbSchool($tenant, fn () => Applicant::query()->orderBy('number')->get(['name', 'number', 'score', 'decision'])->map(fn ($a) => [$a->name, $a->number, $a->score, $a->decision])->all());

    expect($names($first))->toBe($names($second));
});
