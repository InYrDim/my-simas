<?php

namespace Modules\Platform\App\Http\Controllers\Applicant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\Exceptions\InvalidApplicationException;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Domain\Models\TenantApplicationStatus;

/**
 * The applicant's onboarding page: fill in the school and submit it for
 * provider review, then follow its status.
 *
 * Tahap 1 scope: the school form goes through the existing
 * TenantApplications::submit() with the applicant's own name and email,
 * and the latest application for that email is shown. Binding the
 * application to the account, plan choice and resubmission arrive with
 * Tahap 2 (docs/ai/plan/fase-4/tenant-onboarding-plan.md).
 */
final class OnboardingController
{
    /**
     * Timezone choices for the form (Indonesia's three IANA zones).
     *
     * @var array<int, string>
     */
    private const TIMEZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];

    public function __construct(
        private readonly TenantApplications $applications,
    ) {}

    public function show(): Response
    {
        $applicant = $this->applicant();
        $application = $this->latestApplication($applicant);

        return Inertia::render('Platform/Applicant/Onboarding', [
            'applicant' => ['name' => $applicant->name, 'email' => $applicant->email],
            'timezones' => self::TIMEZONES,
            'application' => $application === null ? null : [
                'schoolName' => $application->school_name,
                'desiredSlug' => $application->desired_slug,
                'timezone' => $application->timezone,
                'status' => $application->status->value,
                'adminNote' => $application->admin_note,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $applicant = $this->applicant();
        $latest = $this->latestApplication($applicant);

        if ($latest !== null && $latest->status !== TenantApplicationStatus::Rejected) {
            return back()->withErrors([
                'application' => 'Anda sudah memiliki pengajuan yang sedang diproses atau sudah disetujui.',
            ]);
        }

        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'desired_slug' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:255'],
            'applicant_message' => ['nullable', 'string', 'max:2000'],
        ], [
            'school_name.required' => 'Nama sekolah wajib diisi.',
            'desired_slug.required' => 'Kode sekolah wajib diisi.',
        ]);

        try {
            $this->applications->submit([
                ...$validated,
                'applicant_name' => $applicant->name,
                'applicant_email' => $applicant->email,
            ]);
        } catch (InvalidApplicationException $e) {
            return back()->withErrors([
                'application' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('applicant.home')
            ->with('status', 'Pengajuan terkirim — mohon tunggu ACC dari provider.');
    }

    private function applicant(): Applicant
    {
        /** @var Applicant $applicant */
        $applicant = Auth::guard('applicant')->user();

        return $applicant;
    }

    private function latestApplication(Applicant $applicant): ?TenantApplication
    {
        return TenantApplication::query()
            ->where('applicant_email', mb_strtolower($applicant->email))
            ->orderByDesc('id')
            ->first();
    }
}
