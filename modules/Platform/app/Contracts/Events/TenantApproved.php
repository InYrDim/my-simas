<?php

namespace Modules\Platform\App\Contracts\Events;

/**
 * Fired (synchronously, inside the approval transaction) when a school
 * application is approved and its tenant provisioned. Identity listens
 * to provision the first admin user — a failing listener rolls the
 * whole approval back, so no tenant ever exists without its admin.
 *
 * `passwordHash` is the applicant account's password hash, handed over
 * so the school admin logs in with the password they already chose. It
 * is null for applications without an applicant account (the admin then
 * gets a set-password link). The event is never queued or serialised —
 * it lives only for the duration of the synchronous dispatch.
 */
final class TenantApproved
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $applicantName,
        public readonly string $applicantEmail,
        #[\SensitiveParameter]
        public readonly ?string $passwordHash = null,
    ) {}
}
