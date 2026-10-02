<?php

namespace Modules\Ppdb\App\Infrastructure\Account;

use DateTimeInterface;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\Platform\App\Contracts\TenantUrl;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Infrastructure\Mail\AccountPasswordResetMail;
use Modules\Ppdb\App\Infrastructure\Mail\AccountVerificationMail;

/**
 * Mails an applicant account its two links: verify the email, and set a
 * new password. Both are temporary signed URLs (no token table), signed
 * RELATIVE and prefixed with the application's root, so the link works on
 * the host applicants use whatever host sent it. A verification link is
 * tied to the email; a reset link also to the current password, so it
 * works once.
 */
final class AccountLinks
{
    public const VERIFICATION_TTL_MINUTES = 60;

    public const RESET_TTL_MINUTES = 60;

    public function __construct(
        private readonly TenantUrl $urls,
    ) {}

    public function sendVerification(PpdbAccount $account): void
    {
        $url = $this->signed(
            'ppdb.account.verify',
            now()->addMinutes(self::VERIFICATION_TTL_MINUTES),
            $account,
            $account->verificationHash(),
        );

        Mail::to($account->email)->queue(new AccountVerificationMail($url, $account->name));
    }

    public function sendPasswordReset(PpdbAccount $account): void
    {
        $url = $this->signed(
            'ppdb.account.password.reset',
            now()->addMinutes(self::RESET_TTL_MINUTES),
            $account,
            $account->credentialHash(),
        );

        Mail::to($account->email)->queue(new AccountPasswordResetMail($url, $account->name));
    }

    private function signed(string $route, DateTimeInterface $expiresAt, PpdbAccount $account, string $hash): string
    {
        $path = URL::temporarySignedRoute($route, $expiresAt, ['account' => $account->id, 'hash' => $hash], absolute: false);

        return $this->urls->root().$path;
    }
}
