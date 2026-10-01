<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Infrastructure\Onboarding\ApplicantAccessLinks;

/**
 * Provider console: the applicant accounts and inviting a new one. An
 * invited applicant starts without a password and sets it from the
 * emailed link; everything after that is the ordinary onboarding.
 */
final class ApplicantConsoleController
{
    /**
     * Applicants, newest first, each with where they stand.
     */
    public function index(): Response
    {
        $applicants = Applicant::query()->orderByDesc('id')->limit(200)->get();

        $applications = TenantApplication::query()
            ->whereIn('applicant_id', $applicants->pluck('id'))
            ->get()
            ->keyBy('applicant_id');

        return Inertia::render('Platform/Applicants/Index', [
            'applicants' => $applicants->map(function (Applicant $applicant) use ($applications): array {
                $application = $applications->get($applicant->id);

                return [
                    'id' => $applicant->id,
                    'name' => $applicant->name,
                    'email' => $applicant->email,
                    'state' => $this->state($applicant, $application),
                    'schoolName' => $application?->school_name,
                    'applicationId' => $application?->id,
                    'canResendInvitation' => $applicant->tenant_id === null && $applicant->password === null,
                    'createdAt' => $applicant->created_at?->toDateString(),
                ];
            })->all(),
        ]);
    }

    /**
     * Invite: create the account without a password and mail the link.
     */
    public function store(Request $request, ApplicantAccessLinks $links): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:applicants,email'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Email harus berupa alamat email yang valid.',
            'email.unique' => 'Email ini sudah terdaftar sebagai pemohon.',
        ]);

        $applicant = Applicant::query()->create($validated);

        $links->sendInvitation($applicant);

        return back()->with('status', "Undangan dikirim ke {$applicant->email}.");
    }

    /**
     * Send the invitation again to an applicant who has not set a
     * password yet.
     */
    public function resend(Applicant $applicant, ApplicantAccessLinks $links): RedirectResponse
    {
        if ($applicant->tenant_id !== null || $applicant->password !== null) {
            return back()->withErrors(['applicant' => 'Pemohon ini sudah mengaktifkan akunnya.']);
        }

        $links->sendInvitation($applicant);

        return back()->with('status', "Undangan dikirim ulang ke {$applicant->email}.");
    }

    /**
     * @return 'approved'|'invited'|'unverified'|'registered'|'pending'|'rejected'
     */
    private function state(Applicant $applicant, ?TenantApplication $application): string
    {
        return match (true) {
            $applicant->tenant_id !== null => 'approved',
            $applicant->password === null => 'invited',
            ! $applicant->hasVerifiedEmail() => 'unverified',
            $application === null => 'registered',
            default => $application->status->value,
        };
    }
}
