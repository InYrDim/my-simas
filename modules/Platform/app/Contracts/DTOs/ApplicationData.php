<?php

namespace Modules\Platform\App\Contracts\DTOs;

/**
 * Read-only snapshot of a tenant application, for the provider console
 * and the applicant's own onboarding page. The internal model is never
 * handed to other modules.
 */
final readonly class ApplicationData
{
    public function __construct(
        public int $id,
        public string $schoolName,
        public string $desiredSlug,
        public string $timezone,
        public string $applicantName,
        public string $applicantEmail,
        public ?string $applicantMessage,
        public string $status,
        public ?string $adminNote,
        public ?string $decidedAt,
        public ?int $decidedBy,
        public ?int $applicantId = null,
        public ?string $planKey = null,
        public ?string $submittedAt = null,
    ) {}
}
