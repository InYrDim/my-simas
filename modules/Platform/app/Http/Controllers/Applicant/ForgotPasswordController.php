<?php

namespace Modules\Platform\App\Http\Controllers\Applicant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Infrastructure\Onboarding\ApplicantAccessLinks;

/**
 * Applicant "forgot password". The answer is the same whether or not the
 * email belongs to an applicant, so the form cannot be used to probe
 * which emails are registered; what is mailed (if anything) is decided
 * by ApplicantAccessLinks.
 */
final class ForgotPasswordController
{
    public function create(): Response
    {
        return Inertia::render('Platform/Applicant/ForgotPassword');
    }

    public function store(Request $request, ApplicantAccessLinks $links): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Email harus berupa alamat email yang valid.',
        ]);

        $applicant = Applicant::query()
            ->where('email', mb_strtolower(trim($validated['email'])))
            ->first();

        if ($applicant !== null) {
            $links->sendPasswordHelp($applicant);
        }

        return back()->with('status', 'Jika email terdaftar, petunjuk atur ulang kata sandi telah dikirim.');
    }
}
