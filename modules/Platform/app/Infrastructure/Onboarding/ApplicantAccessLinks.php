<?php

namespace Modules\Platform\App\Infrastructure\Onboarding;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\Platform\App\Contracts\TenantUrl;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Infrastructure\Mail\ApplicantInvitationMail;
use Modules\Platform\App\Infrastructure\Mail\ApplicantPasswordResetMail;
use Modules\Platform\App\Infrastructure\Mail\ApplicantSchoolResetMail;
use Modules\Platform\App\Infrastructure\Tenancy\TenantHydrator;

/**
 * Mails an applicant the links that let them set a password: the
 * provider's invitation and the forgot-password reset. Both are
 * temporary signed URLs (no token table) carrying the applicant's
 * credential hash, so a link dies the moment the password it was issued
 * against changes — each link works once.
 *
 * The URLs are signed RELATIVE and prefixed with TenantUrl::root():
 * invitations are sent from the console host, and an absolute signature
 * would bind the link to that host instead of the one applicants use.
 */
final class ApplicantAccessLinks
{
    /**
     * Days an invitation stays valid.
     */
    public const INVITATION_TTL_DAYS = 7;

    /**
     * Minutes a password-reset link stays valid.
     */
    public const RESET_TTL_MINUTES = 60;

    public function __construct(
        private readonly TenantUrl $urls,
    ) {}

    public function sendInvitation(Applicant $applicant): void
    {
        $url = $this->signed('applicant.invitation', now()->addDays(self::INVITATION_TTL_DAYS), $applicant);

        Mail::to($applicant->email)->queue(new ApplicantInvitationMail($url, $applicant->name));
    }

    /**
     * What "forgot password" means depends on where the applicant is:
     * not activated yet → the invitation again; approved → their
     * password lives on the school account, so point them at the
     * school's own reset; otherwise → an applicant reset link.
     */
    public function sendPasswordHelp(Applicant $applicant): void
    {
        if ($applicant->tenant_id !== null) {
            Mail::to($applicant->email)->queue(new ApplicantSchoolResetMail(
                $this->urls->url($applicant->tenant_id, 'forgot-password'),
                $applicant->name,
                (string) (TenantHydrator::find($applicant->tenant_id)?->slug ?? $applicant->tenant_id),
            ));

            return;
        }

        if ($applicant->password === null) {
            $this->sendInvitation($applicant);

            return;
        }

        $url = $this->signed('applicant.password.reset', now()->addMinutes(self::RESET_TTL_MINUTES), $applicant);

        Mail::to($applicant->email)->queue(new ApplicantPasswordResetMail($url, $applicant->name));
    }

    private function signed(string $route, \DateTimeInterface $expiresAt, Applicant $applicant): string
    {
        $path = URL::temporarySignedRoute(
            $route,
            $expiresAt,
            ['applicant' => $applicant->id, 'hash' => $applicant->credentialHash()],
            absolute: false,
        );

        return $this->urls->root().$path;
    }
}
