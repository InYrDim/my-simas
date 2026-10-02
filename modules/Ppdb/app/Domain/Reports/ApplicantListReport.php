<?php

namespace Modules\Ppdb\App\Domain\Reports;

use Modules\Core\App\Contracts\DTOs\ReportDefinition;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\ReportTable;
use Modules\Core\App\Contracts\Report;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\SchoolDay;

/**
 * Daftar Pendaftar PPDB: everyone who registered during the academic year,
 * with the path, the wave and where they stand in verification. No contact
 * details leave the school's pages.
 */
final class ApplicantListReport implements Report
{
    public function __construct(
        private readonly SchoolDay $day,
    ) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'ppdb-applicants',
            group: 'Penerimaan (PPDB)',
            name: 'Daftar Pendaftar PPDB',
            description: 'Pendaftar menurut jalur dan status verifikasi.',
            permission: 'ppdb.view',
            order: 70,
        );
    }

    public function table(ReportPeriod $period): ReportTable
    {
        $paths = AdmissionPath::query()->get()->keyBy('id');
        $waves = AdmissionWave::query()->get()->keyBy('id');

        $applicants = Applicant::query()
            ->whereBetween('registered_on', [$period->startsOn, $period->endsOn])
            ->orderBy('number')
            ->get();

        $rows = [];

        foreach ($applicants as $applicant) {
            $rows[] = [
                $applicant->number,
                $applicant->name,
                $applicant->gender === 'L' ? 'Laki-laki' : 'Perempuan',
                $applicant->origin_school,
                $paths->get($applicant->path_id)?->name,
                $waves->get($applicant->wave_id)?->name,
                $this->day->label($applicant->registered_on),
                $applicant->status->label(),
                $applicant->source->label(),
            ];
        }

        return new ReportTable(
            'Daftar Pendaftar PPDB',
            ['No. Daftar', 'Nama', 'Jenis Kelamin', 'Asal Sekolah', 'Jalur', 'Gelombang', 'Tanggal Daftar', 'Status Verifikasi', 'Sumber'],
            $rows,
        );
    }
}
