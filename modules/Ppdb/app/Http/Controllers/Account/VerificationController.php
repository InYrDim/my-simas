<?php

namespace Modules\Ppdb\App\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Infrastructure\Account\AccountLinks;

/**
 * Email verification of an applicant account. The link is a temporary
 * signed URL (`signed:relative` rejects expired or altered links) carrying
 * a hash of the email, so it works in any browser — the applicant does not
 * have to be signed in where they open their mail.
 */
final class VerificationController
{
    public function notice(): Response|RedirectResponse
    {
        /** @var PpdbAccount $account */
        $account = Auth::guard('ppdb')->user();

        if ($account->hasVerifiedEmail()) {
            return redirect()->route('ppdb.account.home');
        }

        return Inertia::render('Ppdb/Account/VerifyNotice', ['email' => $account->email]);
    }

    public function verify(int $account, string $hash): RedirectResponse
    {
        $found = PpdbAccount::query()->find($account);

        if ($found === null || ! hash_equals($found->verificationHash(), $hash)) {
            abort(403);
        }

        if (! $found->hasVerifiedEmail()) {
            $found->forceFill(['email_verified_at' => now()])->save();
        }

        $target = Auth::guard('ppdb')->id() === $found->id ? 'ppdb.account.home' : 'ppdb.account.login';

        return redirect()->route($target)->with('status', 'Email terverifikasi.');
    }

    public function resend(AccountLinks $links): RedirectResponse
    {
        /** @var PpdbAccount $account */
        $account = Auth::guard('ppdb')->user();

        if ($account->hasVerifiedEmail()) {
            return redirect()->route('ppdb.account.home');
        }

        $links->sendVerification($account);

        return back()->with('status', 'Tautan verifikasi baru sudah dikirim.');
    }
}
