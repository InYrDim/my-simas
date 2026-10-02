<?php

namespace Modules\Ppdb\App\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Http\Requests\Account\EmailRequest;
use Modules\Ppdb\App\Http\Requests\Account\NewPasswordRequest;
use Modules\Ppdb\App\Infrastructure\Account\AccountLinks;

/**
 * "Forgot password" and setting a new one from the emailed link.
 *
 * The answer to the request is the same whether or not the email has an
 * account, so the form cannot be used to probe which emails are
 * registered. The link (`signed:relative`) must still match the account's
 * CURRENT credentials, so each link works once. Opening it proves control
 * of the mailbox, so a successful reset also verifies the email.
 */
final class PasswordController
{
    public function request(): Response
    {
        return Inertia::render('Ppdb/Account/ForgotPassword');
    }

    public function email(EmailRequest $request, AccountLinks $links): RedirectResponse
    {
        $account = PpdbAccount::query()->where('email', $request->validated('email'))->first();

        if ($account !== null) {
            $links->sendPasswordReset($account);
        }

        return back()->with('status', 'Jika email terdaftar, petunjuk atur ulang kata sandi telah dikirim.');
    }

    public function reset(Request $request, int $account, string $hash): Response
    {
        $found = $this->account($account, $hash);

        return Inertia::render('Ppdb/Account/SetPassword', [
            'email' => $found->email,
            // The form posts back to this exact signed URL.
            'action' => $request->getRequestUri(),
        ]);
    }

    public function update(NewPasswordRequest $request, int $account, string $hash): RedirectResponse
    {
        $found = $this->account($account, $hash);

        $found->forceFill([
            'password' => $request->validated('password'),
            'email_verified_at' => $found->email_verified_at ?? now(),
        ])->save();

        Auth::guard('ppdb')->login($found);
        $request->session()->regenerate();

        return redirect()->route('ppdb.account.home')->with('status', 'Kata sandi tersimpan.');
    }

    private function account(int $id, string $hash): PpdbAccount
    {
        $account = PpdbAccount::query()->find($id);

        if ($account === null || ! hash_equals($account->credentialHash(), $hash)) {
            abort(403);
        }

        return $account;
    }
}
