<?php

namespace Modules\Platform\App\Contracts;

use Modules\Platform\App\Contracts\DTOs\ApplicationData;
use Modules\Platform\App\Contracts\Exceptions\ApplicationNotPendingException;
use Modules\Platform\App\Contracts\Exceptions\InvalidApplicationException;

/**
 * School onboarding pipeline: an applicant account submits its school,
 * the SaaS provider reviews it. Applications live on the CENTRAL host
 * (the applicant is not a school user yet); approval provisions the
 * tenant transactionally.
 *
 * One applicant has ONE application. A pending application carries the
 * proposed school identity and the chosen plan. The provider may correct
 * school name/slug/timezone/plan at approval time — the corrected
 * payload becomes the FINAL tenant data. Submissions are validated
 * defensively; validation also RE-RUNS at approval on the final payload
 * (another tenant may have taken the slug while the application sat
 * pending). A rejected application is corrected and resubmitted on the
 * same row.
 */
interface TenantApplications
{
    /**
     * School fields accepted from the applicant's onboarding form. The
     * applicant's name and email come from the account, never the form.
     *
     * @return array<int, string>
     */
    public static function payloadKeys(): array;

    /**
     * Validate and record the applicant's application as pending. Throws
     * with every violation found: unknown applicant, applicant already
     * has an application, slug shape/reserved/taken (tenants incl.
     * trashed) or already pending, invalid timezone, plan not selectable,
     * missing required fields.
     *
     * @param  array<string, mixed>  $school  school_name, desired_slug, timezone, plan_key, applicant_message
     *
     * @throws InvalidApplicationException
     */
    public function submit(int $applicantId, array $school): ApplicationData;

    /**
     * Correct the applicant's REJECTED application and send it back to
     * review: same row, status pending again, decision stamp cleared. The
     * provider's last note is kept so the reviewer sees what was asked.
     *
     * @param  array<string, mixed>  $school  same keys as submit()
     *
     * @throws InvalidApplicationException
     * @throws ApplicationNotPendingException when the application is not rejected
     */
    public function resubmit(int $applicantId, array $school): ApplicationData;

    /**
     * The applicant's application, whatever its status; null when they
     * have not submitted one.
     */
    public function forApplicant(int $applicantId): ?ApplicationData;

    /**
     * All pending applications (oldest first) for the provider console.
     *
     * @return iterable<ApplicationData>
     */
    public function pending(): iterable;

    /**
     * Approve a pending application, optionally with provider-corrected
     * school data and plan (data final tenant = hasil form ACC).
     * Transactionally: re-validates the final slug, creates the Tenant
     * (TenantCreated → default roles), enables
     * config('tenancy.onboarding_modules') flags, starts a trial on the
     * application's plan (falling back to config('billing.trial_plan')),
     * stamps the decision, fires TenantApproved. Idempotency guard: a
     * non-pending application throws.
     *
     * @param  array<string, mixed>  $payload  provider corrections (school keys, plan_key, admin_note)
     * @return ApplicationData the updated application
     *
     * @throws ApplicationNotPendingException
     * @throws InvalidApplicationException
     */
    public function approve(int $id, int $decidedBy, array $payload = []): ApplicationData;

    /**
     * Reject a pending application (note optional). Creates nothing: no
     * tenant, no modules, no events. Idempotency guard: a non-pending
     * application throws.
     *
     * @return ApplicationData the updated application
     *
     * @throws ApplicationNotPendingException
     */
    public function reject(int $id, ?string $note, int $decidedBy): ApplicationData;
}
