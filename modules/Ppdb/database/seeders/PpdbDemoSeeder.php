<?php

namespace Modules\Ppdb\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantDirectory;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Ppdb\App\Domain\Actions\SavePaths;
use Modules\Ppdb\App\Domain\Actions\SavePeriod;
use Modules\Ppdb\App\Domain\Actions\SaveWave;
use Modules\Ppdb\App\Domain\Enums\ApplicantSource;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * Fills the schools that use PPDB with a sample admissions period — three
 * waves, the usual paths with a quota, and eight applicants at different
 * points of the process — so the pages, the reports and the figures have
 * something to show. Development only; skips a school that already has a
 * period. Results are not announced, so the selection can still be worked
 * through.
 *
 *   php artisan db:seed --class="Modules\Ppdb\Database\Seeders\PpdbDemoSeeder"
 *
 * Deliberately no WithoutModelEvents: `tenant_id` is filled by a model
 * event.
 */
class PpdbDemoSeeder extends Seeder
{
    /**
     * Name, gender, origin school, path, registered on, status, score,
     * decision. Always the same, so two fresh databases look alike.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: ApplicantStatus, 6: string|null, 7: Decision}>
     */
    private const APPLICANTS = [
        ['Nadia Putri Anggraini', 'P', 'SMPN 3 Bandung', 'Zonasi', '2027-01-12', ApplicantStatus::Verified, '88.40', Decision::Accepted],
        ['Rafi Maulana', 'L', 'SMP Al-Azhar', 'Prestasi', '2027-01-14', ApplicantStatus::Verified, '92.00', Decision::Accepted],
        ['Salsabila Azzahra', 'P', 'SMPN 1 Cimahi', 'Zonasi', '2027-01-20', ApplicantStatus::Submitted, null, Decision::Pending],
        ['Dika Prasetyo', 'L', 'SMPN 12 Bandung', 'Afirmasi', '2027-01-22', ApplicantStatus::Revision, null, Decision::Pending],
        ['Anindya Kirana', 'P', 'SMP Pasundan 2', 'Prestasi', '2027-02-03', ApplicantStatus::Verified, '94.50', Decision::Accepted],
        ['Muhammad Fikri', 'L', 'SMPN 5 Bandung', 'Mutasi', '2027-02-05', ApplicantStatus::Verified, '61.50', Decision::Rejected],
        ['Tiara Ramadhani', 'P', 'SMPN 9 Bandung', 'Zonasi', '2027-02-08', ApplicantStatus::Verified, '86.10', Decision::Pending],
        ['Galih Permana', 'L', 'SMP Taruna Bakti', 'Afirmasi', '2027-02-10', ApplicantStatus::Submitted, null, Decision::Pending],
    ];

    /**
     * Seats per path.
     *
     * @var array<string, int>
     */
    private const QUOTAS = ['Zonasi' => 3, 'Prestasi' => 2, 'Afirmasi' => 1, 'Mutasi' => 1];

    public function run(TenantContext $context, TenantDirectory $tenants, TenantModules $modules): void
    {
        foreach ($tenants->all() as $tenant) {
            if ($modules->isEnabled('ppdb', $tenant->id)) {
                $context->run($tenant->id, fn () => $this->seedSchool());
            }
        }
    }

    /**
     * Seeds the current tenant (call it inside the tenant's context).
     */
    public function seedSchool(): void
    {
        if (AdmissionPeriod::query()->exists()) {
            return;
        }

        $period = app(SavePeriod::class)->handle(null, [
            'name' => 'PPDB 2027/2028',
            'entry_year' => 2027,
            'status' => PeriodStatus::Active->value,
        ]);

        $saveWave = app(SaveWave::class);
        $waves = [
            $saveWave->handle($period, null, ['name' => 'Gelombang 1', 'opens_on' => '2027-01-01', 'closes_on' => '2027-03-31']),
            $saveWave->handle($period, null, ['name' => 'Gelombang 2', 'opens_on' => '2027-04-01', 'closes_on' => '2027-06-30']),
            $saveWave->handle($period, null, ['name' => 'Gelombang 3', 'opens_on' => '2027-07-01', 'closes_on' => '2027-07-15']),
        ];

        $paths = $period->paths()->get();

        app(SavePaths::class)->handle($period, array_values($paths->map(fn ($path): array => [
            'id' => $path->id,
            'name' => $path->name,
            'quota' => self::QUOTAS[$path->name] ?? 0,
        ])->all()));

        foreach (self::APPLICANTS as $index => [$name, $gender, $origin, $pathName, $registeredOn, $status, $score, $decision]) {
            Applicant::query()->create([
                'period_id' => $period->id,
                'wave_id' => $waves[0]->id,
                'path_id' => $paths->firstWhere('name', $pathName)->id,
                'number' => sprintf('PPDB-27-%04d', $index + 1),
                'name' => $name,
                'gender' => $gender,
                'birth_date' => sprintf('2012-%02d-%02d', ($index % 12) + 1, ($index % 27) + 1),
                'origin_school' => $origin,
                'guardian_name' => 'Wali '.$name,
                'guardian_phone' => sprintf('0812000%04d', $index + 1),
                'source' => ApplicantSource::Staff,
                'registered_on' => $registeredOn,
                'status' => $status,
                'verification_note' => $status === ApplicantStatus::Revision ? 'Akta kelahiran belum terbaca.' : null,
                'score' => $score,
                'decision' => $decision,
            ]);
        }
    }
}
