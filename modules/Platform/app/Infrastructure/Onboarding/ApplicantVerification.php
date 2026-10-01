<?php

namespace Modules\Platform\App\Infrastructure\Onboarding;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Infrastructure\Mail\ApplicantVerifyMail;

/**
 * Sends an applicant their email-verification link: a temporary signed
 * URL (no token table), built here during the request and handed to the
 * queued mail as a plain string.
 */
final class ApplicantVerification
{
    /**
     * Minutes a verification link stays valid.
     */
    public const LINK_TTL_MINUTES = 60;

    public function send(Applicant $applicant): void
    {
        $url = URL::temporarySignedRoute(
            'applicant.verify',
            now()->addMinutes(self::LINK_TTL_MINUTES),
            ['applicant' => $applicant->id, 'hash' => $applicant->verificationHash()],
        );

        Mail::to($applicant->email)->queue(new ApplicantVerifyMail($url, $applicant->name));
    }
}
