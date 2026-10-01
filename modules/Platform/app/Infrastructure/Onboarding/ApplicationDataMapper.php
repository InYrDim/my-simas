<?php

namespace Modules\Platform\App\Infrastructure\Onboarding;

use Modules\Platform\App\Contracts\DTOs\ApplicationData;
use Modules\Platform\App\Domain\Models\TenantApplication;

/**
 * The one place a TenantApplication row becomes the public DTO, so the
 * contract implementation and the review controller cannot drift apart.
 */
final class ApplicationDataMapper
{
    public static function map(TenantApplication $application): ApplicationData
    {
        return new ApplicationData(
            id: (int) $application->id,
            schoolName: $application->school_name,
            desiredSlug: $application->desired_slug,
            timezone: $application->timezone,
            applicantName: $application->applicant_name,
            applicantEmail: $application->applicant_email,
            applicantMessage: $application->applicant_message,
            status: $application->status->value,
            adminNote: $application->admin_note,
            decidedAt: $application->decided_at?->toIso8601String(),
            decidedBy: $application->decided_by,
            applicantId: $application->applicant_id,
            planKey: $application->plan_key,
            submittedAt: $application->submitted_at?->toIso8601String(),
        );
    }
}
