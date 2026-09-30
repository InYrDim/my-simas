<?php

namespace Modules\Platform\App\Contracts;

use Modules\Platform\App\Contracts\DTOs\ApplicationData;
use Modules\Platform\App\Contracts\Exceptions\ApplicationNotPendingException;
use Modules\Platform\App\Contracts\Exceptions\InvalidApplicationException;

/**
 * School onboarding pipeline: public self-service applications reviewed
 * by the SaaS provider. Applications live on the CENTRAL host (the
 * applicant is not a user anywhere yet); approval provisions the tenant
 * transactionally.
 *
 * A pending application carries the proposed school identity. The
 * provider may correct school name/slug/timezone at approval time — the
 * corrected payload becomes the FINAL tenant data. Submissions are
 * validated defensively; validation also RE-RUNS at approval on the
 * final payload (another tenant may have taken the slug while the
 * application sat pending).
 */
interface TenantApplications
{
    /**
     * Fields accepted from the public form.
     *
     * @return array<int, string>
     */
    public static function payloadKeys(): array;

    /**
     * Validate and record a new pending application. Throws with every
     * violation found: slug shape/reserved/taken (tenants incl. trashed)
     * or already pending, applicant email already on a pending
     * application, invalid timezone, missing required fields.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidApplicationException
     */
    public function submit(array $payload): void;

    /**
     * All pending applications (oldest first) for the provider console.
     *
     * @return iterable<ApplicationData>
     */
    public function pending(): iterable;

    /**
     * Approve a pending application, optionally with provider-corrected
     * school data (data final tenant = hasil form ACC). Transactionally:
     * re-validates the final slug, creates the Tenant (TenantCreated →
     * default roles), enables config('tenancy.onboarding_modules')
     * flags, stamps the decision, fires TenantApproved. Idempotency
     * guard: a non-pending application throws.
     *
     * @param  array<string, mixed>  $payload  provider corrections (same keys as submit)
     * @return ApplicationData the updated application
     *
     * @throws ApplicationNotPendingException
     * @throws InvalidApplicationException
     */
    public function approve(int $id, int $decidedBy, array $payload = []): ApplicationData;

    /**
     * Reject a pending application (note optional). Side-effect free:
     * no tenant, no modules, no events. Idempotency guard: a non-pending
     * application throws.
     *
     * @return ApplicationData the updated application
     *
     * @throws ApplicationNotPendingException
     */
    public function reject(int $id, ?string $note, int $decidedBy): ApplicationData;
}
