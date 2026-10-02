<?php

namespace Modules\Ppdb\App\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Domain\Actions\Account\RegisterAccount;
use Modules\Ppdb\App\Http\Requests\Account\RegisterAccountRequest;

/**
 * Registration of an applicant's own account at `/calon-siswa/daftar`
 * (a central page). Creates an account only — joining a school comes
 * after, inside the account.
 *
 * Anti-spam without dependencies: an IP throttle on the POST route plus a
 * hidden honeypot field; a filled honeypot is dropped SILENTLY (the same
 * answer, no row) so bots learn nothing.
 */
final class RegisterController
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('ppdb')->check()) {
            return redirect()->route('ppdb.account.home');
        }

        return Inertia::render('Ppdb/Account/Register');
    }

    public function store(RegisterAccountRequest $request, RegisterAccount $register): RedirectResponse
    {
        if ($request->filled('website')) {
            return redirect()
                ->route('ppdb.account.login')
                ->with('status', 'Akun dibuat — periksa email Anda untuk verifikasi.');
        }

        $account = $register->handle($request->accountData());

        Auth::guard('ppdb')->login($account);
        $request->session()->regenerate();

        return redirect()->route('ppdb.account.verify.notice');
    }
}
