<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\ApplicantSource;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Domain\Support\ApplicantChecks;
use Modules\Ppdb\App\Domain\Support\SchoolDay;

/**
 * Registers an applicant in the school's running period and gives them
 * the next registration number (`PPDB-27-0001`: the year the students
 * start, then a running number for the school).
 *
 * The committee (source Staff) may put an applicant in any wave. An
 * applicant who registers through their own account (source Online) is
 * put in the wave that is open today — and only when one is — and only
 * once per account.
 */
final class RegisterApplicant
{
    private const ATTEMPTS = 5;

    public function __construct(
        private readonly ApplicantChecks $checks,
        private readonly SchoolDay $day,
        private readonly SaveAnswers $saveAnswers,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the applicant's own fields (Applicant::DATA_FIELDS)
     * @param  PpdbAccount|null  $account  the applicant's account; required for source Online, ignored otherwise
     * @param  array<array-key, mixed>  $answers  answers to the period's custom fields, by field id
     *
     * @throws ValidationException when the period is not running, a choice is wrong or the account cannot register
     */
    public function handle(
        AdmissionPeriod $period,
        array $data,
        ApplicantSource $source,
        ?int $recordedBy = null,
        ?PpdbAccount $account = null,
        array $answers = [],
    ): Applicant {
        if ($period->status !== PeriodStatus::Active) {
            throw ValidationException::withMessages(['period' => 'Pendaftaran hanya untuk periode PPDB yang sedang berjalan.']);
        }

        $account = $source === ApplicantSource::Online ? $account : null;

        if ($source === ApplicantSource::Online) {
            $data['wave_id'] = $this->waveFor($period, $account)->id;
        }

        $data = Arr::only($data, Applicant::DATA_FIELDS);

        $this->checks->choices($period, $data);

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($period, $data, $source, $recordedBy, $account, $answers): Applicant {
                    $applicant = Applicant::query()->create([
                        ...$data,
                        'period_id' => $period->id,
                        'account_id' => $account?->id,
                        'number' => $this->nextNumber($period),
                        'source' => $source,
                        'registered_on' => $this->day->today(),
                        'status' => ApplicantStatus::Submitted,
                        'decision' => Decision::Pending,
                        'recorded_by' => $recordedBy,
                    ]);

                    $this->saveAnswers->handle($applicant, $period, $answers);

                    return $applicant;
                });
            } catch (UniqueConstraintViolationException $exception) {
                // Two registrations asked for the same number at once — or
                // the account registered twice at once.
                if ($account !== null && Applicant::query()->where('account_id', $account->id)->exists()) {
                    throw $this->alreadyRegistered();
                }

                if ($attempt >= self::ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * The wave an applicant registering by themselves goes into.
     *
     * @throws ValidationException
     */
    private function waveFor(AdmissionPeriod $period, ?PpdbAccount $account): AdmissionWave
    {
        if ($account === null || $account->tenant_id !== $period->tenant_id) {
            throw ValidationException::withMessages(['school' => 'Akun Anda belum bergabung ke sekolah ini.']);
        }

        if (Applicant::query()->where('account_id', $account->id)->exists()) {
            throw $this->alreadyRegistered();
        }

        $today = $this->day->today();
        $open = $period->waves->first(fn (AdmissionWave $wave): bool => $wave->statusOn($today) === AdmissionWave::OPEN);

        if ($open === null) {
            throw ValidationException::withMessages(['period' => 'Pendaftaran belum dibuka atau sudah ditutup. Lihat jadwal gelombang pendaftaran sekolah.']);
        }

        return $open;
    }

    private function alreadyRegistered(): ValidationException
    {
        return ValidationException::withMessages(['application' => 'Anda sudah mengirim formulir pendaftaran.']);
    }

    private function nextNumber(AdmissionPeriod $period): string
    {
        $prefix = sprintf('PPDB-%02d-', $period->entry_year % 100);

        $last = Applicant::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
