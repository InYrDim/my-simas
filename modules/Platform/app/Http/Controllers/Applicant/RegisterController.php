<?php

namespace Modules\Platform\App\Http\Controllers\Applicant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Infrastructure\Onboarding\ApplicantVerification;

/**
 * Applicant registration at `/daftar-sekolah` (central host): the first
 * step of bringing a school onto the platform. Creates an APPLICANT
 * account only — never a school user; school data follows in onboarding.
 *
 * Anti-spam without dependencies: IP throttle on the POST route plus a
 * hidden honeypot field; a filled honeypot is rejected SILENTLY (generic
 * success, no row) so bots learn nothing.
 */
final class RegisterController
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('applicant.home');
        }

        return Inertia::render('Platform/Applicant/Register');
    }

    public function store(Request $request, ApplicantVerification $verification): RedirectResponse
    {
        // Honeypot: humans never see (or fill) this field. Pretend
        // success — no row, no error hint for the bot.
        if ($request->filled('website')) {
            return redirect()
                ->route('applicant.login')
                ->with('status', 'Akun dibuat — periksa email Anda untuk verifikasi.');
        }

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:applicants,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Email harus berupa alamat email yang valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal :min karakter.',
        ]);

        $applicant = Applicant::query()->create($validated);

        $verification->send($applicant);

        Auth::guard('applicant')->login($applicant);
        $request->session()->regenerate();

        return redirect()->route('applicant.verify.notice');
    }
}
