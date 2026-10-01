<?php

namespace Modules\Platform\App\Infrastructure\Onboarding;

use Illuminate\Support\Facades\Mail;
use Modules\Platform\App\Contracts\TenantUrl;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Infrastructure\Mail\ApplicationApprovedMail;
use Modules\Platform\App\Infrastructure\Mail\ApplicationRejectedMail;

/**
 * Tells an applicant what the provider decided. Only applications that
 * belong to an applicant ACCOUNT are notified here: rows from before
 * accounts existed have no applicant login to point at (their admin gets
 * Identity's set-password mail instead).
 *
 * Links are built from TenantUrl::root() rather than route(): decisions
 * are made on the console host, and a route() URL would carry that host.
 */
final class ApplicantDecisionNotifier
{
    public function __construct(
        private readonly TenantUrl $urls,
    ) {}

    /**
     * @param  bool  $passwordHandedOver  the admin account took over the applicant's password, so they can log in right away
     */
    public function approved(TenantApplication $application, string $tenantId, bool $passwordHandedOver): void
    {
        if ($application->applicant_id === null) {
            return;
        }

        $subscription = Subscription::query()->where('tenant_id', $tenantId)->first();
        $plan = $subscription === null ? null : Plan::query()->find($subscription->plan_id);

        Mail::to($application->applicant_email)->queue(new ApplicationApprovedMail(
            recipientName: $application->applicant_name,
            schoolName: $application->school_name,
            schoolCode: $tenantId,
            planName: $plan?->name,
            trialEndsOn: $subscription?->trial_ends_at?->toDateString(),
            loginUrl: $this->urls->root().'/pemohon/masuk',
            passwordReady: $passwordHandedOver,
        ));
    }

    public function rejected(TenantApplication $application): void
    {
        if ($application->applicant_id === null) {
            return;
        }

        Mail::to($application->applicant_email)->queue(new ApplicationRejectedMail(
            recipientName: $application->applicant_name,
            schoolName: $application->school_name,
            note: $application->admin_note,
            onboardingUrl: $this->urls->root().'/pemohon',
        ));
    }
}
