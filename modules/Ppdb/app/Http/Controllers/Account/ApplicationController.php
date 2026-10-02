<?php

namespace Modules\Ppdb\App\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Ppdb\App\Domain\Actions\RegisterApplicant;
use Modules\Ppdb\App\Domain\Actions\UpdateOwnApplication;
use Modules\Ppdb\App\Domain\Enums\ApplicantSource;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Domain\Support\FormFields;
use Modules\Ppdb\App\Domain\Support\SchoolDay;
use Modules\Ppdb\App\Http\Requests\Account\ApplicationRequest;

/**
 * The applicant's own registration form: send it once, and correct it when
 * the committee asks. Every action works inside the ONE school the account
 * joined — entered explicitly, never taken from the request — and on the
 * one registration that belongs to this account.
 */
final class ApplicationController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SchoolDay $day,
    ) {}

    public function form(): Response|RedirectResponse
    {
        $account = $this->account();

        if ($account->tenant_id === null) {
            return redirect()->route('ppdb.account.join');
        }

        return $this->context->run($account->tenant_id, function () use ($account): Response|RedirectResponse {
            $application = Applicant::query()->where('account_id', $account->id)->first();
            $period = AdmissionPeriod::active();

            if ($application !== null && $application->status !== ApplicantStatus::Revision) {
                return redirect()->route('ppdb.account.home');
            }

            if ($application === null && ($period === null || ! $this->waveIsOpen($period))) {
                return redirect()
                    ->route('ppdb.account.home')
                    ->with('status', 'Pendaftaran belum dibuka atau sudah ditutup.');
            }

            $period ??= AdmissionPeriod::query()->findOrFail($application->period_id);

            return Inertia::render('Ppdb/Account/Form', [
                'period' => $period->name,
                'paths' => $period->paths->map(fn (AdmissionPath $path): array => ['value' => (string) $path->id, 'label' => $path->name])->values()->all(),
                'formFields' => FormFields::values($period->formFields()),
                'applicant' => $application === null ? null : [
                    'pathId' => (string) $application->path_id,
                    'name' => $application->name,
                    'gender' => $application->gender,
                    'birthPlace' => $application->birth_place,
                    'birthDate' => $application->birth_date,
                    'nisn' => $application->nisn,
                    'originSchool' => $application->origin_school,
                    'address' => $application->address,
                    'guardianName' => $application->guardian_name,
                    'guardianPhone' => $application->guardian_phone,
                    'note' => $application->verification_note,
                ],
                'defaultName' => $account->name,
            ]);
        });
    }

    public function store(ApplicationRequest $request, RegisterApplicant $register): RedirectResponse
    {
        $account = $this->account();

        if ($account->tenant_id === null) {
            return redirect()->route('ppdb.account.join');
        }

        $applicant = $this->context->run($account->tenant_id, function () use ($account, $request, $register): Applicant {
            $period = AdmissionPeriod::active();

            if ($period === null) {
                throw ValidationException::withMessages(['period' => 'Pendaftaran belum dibuka atau sudah ditutup.']);
            }

            return $register->handle($period, $request->validated(), ApplicantSource::Online, null, $account);
        });

        return redirect()
            ->route('ppdb.account.home')
            ->with('status', "Pendaftaran terkirim. Nomor pendaftaran Anda: {$applicant->number}.");
    }

    public function update(ApplicationRequest $request, UpdateOwnApplication $update): RedirectResponse
    {
        $account = $this->account();

        if ($account->tenant_id === null) {
            return redirect()->route('ppdb.account.join');
        }

        $this->context->run($account->tenant_id, function () use ($account, $request, $update): void {
            $applicant = Applicant::query()->where('account_id', $account->id)->firstOrFail();

            $update->handle($applicant, $request->validated());
        });

        return redirect()->route('ppdb.account.home')->with('status', 'Perbaikan dikirim ke panitia.');
    }

    private function account(): PpdbAccount
    {
        /** @var PpdbAccount $account */
        $account = Auth::guard('ppdb')->user();

        return $account;
    }

    private function waveIsOpen(AdmissionPeriod $period): bool
    {
        $today = $this->day->today();

        return $period->waves->contains(fn (AdmissionWave $wave): bool => $wave->statusOn($today) === AdmissionWave::OPEN);
    }
}
