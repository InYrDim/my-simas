<?php

namespace Modules\Platform\App\Contracts\Events;

/**
 * Fired (synchronously, inside the approval transaction) when a school
 * application is approved and its tenant provisioned. Identity listens
 * to provision the first admin user — a failing listener rolls the
 * whole approval back, so no tenant ever exists without its admin.
 */
final class TenantApproved
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $applicantName,
        public readonly string $applicantEmail,
    ) {}
}
