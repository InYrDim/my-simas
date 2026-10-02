<?php

namespace Modules\Ppdb\App\Domain\Reports;

use Illuminate\Support\Collection;
use Modules\Core\App\Contracts\DTOs\ReportDefinition;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\ReportTable;
use Modules\Core\App\Contracts\Report;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;
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

        [$labels, $answers] = $this->customAnswers($applicants);

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
                ...array_map(fn (string $label): ?string => $answers[$applicant->id][$label] ?? null, $labels),
            ];
        }

        return new ReportTable(
            'Daftar Pendaftar PPDB',
            ['No. Daftar', 'Nama', 'Jenis Kelamin', 'Asal Sekolah', 'Jalur', 'Gelombang', 'Tanggal Daftar', 'Status Verifikasi', 'Sumber', ...$labels],
            $rows,
        );
    }

    /**
     * The custom fields of the applicants' periods as extra columns, and
     * each applicant's answers to them. Fields with the same label in
     * different periods share a column; an archived field has one only when
     * someone answered it. A checkboxes answer is its choices joined by a
     * comma, an uploaded file is "Ada".
     *
     * @param  Collection<int, Applicant>  $applicants
     * @return array{0: list<string>, 1: array<int, array<string, string>>}
     */
    private function customAnswers(Collection $applicants): array
    {
        if ($applicants->isEmpty()) {
            return [[], []];
        }

        $fields = FormField::query()
            ->whereIn('period_id', $applicants->pluck('period_id')->unique()->all())
            ->whereNull('key')
            ->where('type', '!=', FieldType::Section->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $labels = [];
        $answers = [];

        foreach (ApplicantAnswer::query()->whereIn('applicant_id', $applicants->pluck('id')->all())->get() as $answer) {
            $field = $fields->get($answer->field_id);

            if ($field === null || $answer->value === null) {
                continue;
            }

            $answers[$answer->applicant_id][$field->label] = match ($field->type) {
                FieldType::Checkboxes => implode(', ', (array) json_decode($answer->value, true)),
                FieldType::File => 'Ada',
                default => $answer->value,
            };
        }

        foreach ($fields as $field) {
            if (! $field->isArchived() || collect($answers)->contains(fn (array $byLabel): bool => array_key_exists($field->label, $byLabel))) {
                $labels[$field->label] = $field->label;
            }
        }

        return [array_values($labels), $answers];
    }
}
