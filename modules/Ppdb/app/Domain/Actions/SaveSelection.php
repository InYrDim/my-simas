<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\ResultAnnouncer;

/**
 * Saves the scores and decisions the committee gives the applicants of one
 * path. All or nothing: a row that breaks a rule stops the whole save.
 *
 * - Only verified applicants of this path can be scored or decided, and a
 *   decision needs a score (the ranking is by score).
 * - The accepted applicants of a path never exceed its quota.
 * - Once the results are announced, the only change left is moving
 *   someone from the waiting list to accepted (within the quota); that
 *   applicant is then told. Scores no longer change, and an applicant who
 *   has re-registered is not touched.
 */
final class SaveSelection
{
    public function __construct(
        private readonly ResultAnnouncer $announcer,
    ) {}

    /**
     * @param  list<array{applicant_id: int, score: string|null, decision: string}>  $rows
     *
     * @throws ValidationException
     */
    public function handle(AdmissionPeriod $period, AdmissionPath $path, array $rows): void
    {
        if ($period->status === PeriodStatus::Draft || $path->period_id !== $period->id) {
            throw ValidationException::withMessages(['path_id' => 'Jalur tidak ditemukan di periode ini.']);
        }

        $published = $period->results_published_at !== null;

        /** @var Collection<int, Applicant> $applicants */
        $applicants = Applicant::query()->where('path_id', $path->id)->where('period_id', $period->id)->get()->keyBy('id');

        $changes = [];

        foreach ($rows as $index => $row) {
            $applicant = $applicants->get((int) $row['applicant_id']);

            if ($applicant === null || $applicant->status !== ApplicantStatus::Verified) {
                throw ValidationException::withMessages(["rows.{$index}.applicant_id" => 'Pendaftar tidak ditemukan di jalur ini atau belum terverifikasi.']);
            }

            $decision = Decision::from($row['decision']);
            $score = $row['score'] === null || $row['score'] === '' ? null : number_format((float) $row['score'], 2, '.', '');
            $unchanged = $decision === $applicant->decision && $score === $this->scoreOf($applicant);

            if ($unchanged) {
                continue;
            }

            if ($applicant->isEnrolled()) {
                throw ValidationException::withMessages(["rows.{$index}.decision" => 'Pendaftar yang sudah daftar ulang tidak bisa diubah.']);
            }

            if ($decision !== Decision::Pending && $score === null) {
                throw ValidationException::withMessages(["rows.{$index}.score" => 'Isi nilai seleksi sebelum menetapkan keputusan.']);
            }

            if ($published && ! ($applicant->decision === Decision::Waitlist && $decision === Decision::Accepted && $score === $this->scoreOf($applicant))) {
                throw ValidationException::withMessages(["rows.{$index}.decision" => 'Hasil sudah diumumkan: yang bisa dilakukan hanya menaikkan pendaftar dari cadangan menjadi diterima.']);
            }

            $changes[$applicant->id] = ['decision' => $decision, 'score' => $score];
        }

        $accepted = $applicants->filter(function (Applicant $applicant) use ($changes): bool {
            return ($changes[$applicant->id]['decision'] ?? $applicant->decision) === Decision::Accepted;
        })->count();

        if ($accepted > $path->quota) {
            throw ValidationException::withMessages([
                'quota' => "Jumlah diterima ({$accepted}) melebihi kuota jalur {$path->name} ({$path->quota}). Ubah kuota di Pengaturan atau kurangi yang diterima.",
            ]);
        }

        DB::transaction(function () use ($applicants, $changes): void {
            foreach ($changes as $id => $change) {
                $applicants->get($id)->forceFill(['decision' => $change['decision'], 'score' => $change['score']])->save();
            }
        });

        if ($published) {
            foreach (array_keys($changes) as $id) {
                $this->announcer->announce($applicants->get($id));
            }
        }
    }

    private function scoreOf(Applicant $applicant): ?string
    {
        return $applicant->score === null ? null : number_format((float) $applicant->score, 2, '.', '');
    }
}
