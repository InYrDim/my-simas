<?php

namespace Modules\Platform\App\Http\Controllers\Applicant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Models\Applicant;

/**
 * Setting an applicant's password from an emailed link — the provider's
 * invitation and the forgot-password reset share this page. The route's
 * `signed:relative` middleware rejects expired or altered links; this
 * controller adds that the link still matches the applicant's CURRENT
 * credentials (so each link works once) and that the applicant has not
 * been approved (their password then lives on the school account).
 *
 * Opening a link proves control of the mailbox, so a successful set also
 * verifies the email.
 */
final class PasswordSetupController
{
    public function show(Request $request, int $applicant, string $hash): Response
    {
        $account = $this->applicant($applicant, $hash);

        return Inertia::render('Platform/Applicant/SetPassword', [
            'email' => $account->email,
            'name' => $account->name,
            'invitation' => $request->routeIs('applicant.invitation'),
            // The form posts back to this exact signed URL.
            'action' => $request->getRequestUri(),
        ]);
    }

    public function store(Request $request, int $applicant, string $hash): RedirectResponse
    {
        $account = $this->applicant($applicant, $hash);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal :min karakter.',
        ]);

        $account->forceFill([
            'password' => $validated['password'],
            'email_verified_at' => $account->email_verified_at ?? now(),
        ])->save();

        Auth::guard('applicant')->login($account);
        $request->session()->regenerate();

        return redirect()
            ->route('applicant.home')
            ->with('status', 'Kata sandi tersimpan.');
    }

    private function applicant(int $id, string $hash): Applicant
    {
        $account = Applicant::query()->find($id);

        if ($account === null
            || $account->tenant_id !== null
            || ! hash_equals($account->credentialHash(), $hash)) {
            abort(403);
        }

        return $account;
    }
}
