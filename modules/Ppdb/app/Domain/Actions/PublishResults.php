<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\ResultAnnouncer;

/**
 * Announces the results of a period: from then on every applicant sees the
 * decision on their own page. Refused while a verified applicant is still
 * undecided (the announcement would leave them hanging), when there is
 * no one to announce to, and when it was announced already. Each applicant
 * who has a decision is told, after the announcement is saved.
 */
final class PublishResults
{
    public function __construct(
        private readonly ResultAnnouncer $announcer,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(AdmissionPeriod $period): void
    {
        if ($period->status === PeriodStatus::Draft) {
            throw ValidationException::withMessages(['publish' => 'Periode ini belum berjalan.']);
        }

        if ($period->results_published_at !== null) {
            throw ValidationException::withMessages(['publish' => 'Hasil periode ini sudah diumumkan.']);
        }

        $verified = Applicant::query()->where('period_id', $period->id)->where('status', ApplicantStatus::Verified->value);

        if ((clone $verified)->doesntExist()) {
            throw ValidationException::withMessages(['publish' => 'Belum ada pendaftar terverifikasi yang bisa diumumkan.']);
        }

        $pending = (clone $verified)->where('decision', Decision::Pending->value)->count();

        if ($pending > 0) {
            throw ValidationException::withMessages(['publish' => "Masih ada {$pending} pendaftar terverifikasi yang belum diputuskan. Putuskan semuanya sebelum mengumumkan."]);
        }

        $period->forceFill(['results_published_at' => now()])->save();

        Applicant::query()
            ->where('period_id', $period->id)
            ->where('decision', '!=', Decision::Pending->value)
            ->orderBy('number')
            ->get()
            ->each(fn (Applicant $applicant): bool => $this->announcer->announce($applicant));
    }
}
