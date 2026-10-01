<?php

namespace Modules\Platform\App\Http\Controllers\Applicant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Infrastructure\Onboarding\ApplicantVerification;

/**
 * Applicant email verification. The link is a temporary signed URL
 * (the `signed` middleware rejects expired or altered links) carrying
 * a hash of the email, so it works in any browser — the applicant does
 * not have to be logged in where they open their mail.
 */
final class VerificationController
{
    public function notice(Request $request): Response|RedirectResponse
    {
        /** @var Applicant $applicant */
        $applicant = Auth::guard('applicant')->user();

        if ($applicant->hasVerifiedEmail()) {
            return redirect()->route('applicant.home');
        }

        return Inertia::render('Platform/Applicant/VerifyNotice', [
            'email' => $applicant->email,
        ]);
    }

    public function verify(Request $request, int $applicant, string $hash): RedirectResponse
    {
        $account = Applicant::query()->find($applicant);

        if ($account === null || ! hash_equals($account->verificationHash(), $hash)) {
            abort(403);
        }

        if (! $account->hasVerifiedEmail()) {
            $account->forceFill(['email_verified_at' => now()])->save();
        }

        $target = Auth::guard('applicant')->id() === $account->id
            ? 'applicant.home'
            : 'applicant.login';

        return redirect()->route($target)->with('status', 'Email terverifikasi.');
    }

    public function resend(ApplicantVerification $verification): RedirectResponse
    {
        /** @var Applicant $applicant */
        $applicant = Auth::guard('applicant')->user();

        if ($applicant->hasVerifiedEmail()) {
            return redirect()->route('applicant.home');
        }

        $verification->send($applicant);

        return back()->with('status', 'Tautan verifikasi baru sudah dikirim.');
    }
}
