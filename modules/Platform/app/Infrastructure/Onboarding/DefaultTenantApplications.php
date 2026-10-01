<?php

namespace Modules\Platform\App\Infrastructure\Onboarding;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Platform\App\Contracts\DTOs\ApplicationData;
use Modules\Platform\App\Contracts\Events\TenantApproved;
use Modules\Platform\App\Contracts\Exceptions\ApplicationNotPendingException;
use Modules\Platform\App\Contracts\Exceptions\InvalidApplicationException;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Domain\Models\TenantApplicationStatus;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;

/**
 * Default TenantApplications: the transactional approval pipeline.
 * Implementation detail of the public contract — consumers type-hint
 * the interface, never this class.
 *
 * Approval (single DB transaction):
 *  1. re-validate the FINAL school payload (provider corrections are
 *     the source of truth; another tenant may have taken the slug while
 *     the application sat pending — same rules as tenant:create),
 *  2. create the Tenant (its `created` hook fires TenantCreated →
 *     Identity seeds default roles; a failing listener aborts here),
 *  3. enable config('tenancy.onboarding_modules') flags (flagged
 *     modules only — core is always active and never listed),
 *  4. start the trial on the application's plan,
 *  5. stamp the decision columns,
 *  6. fire TenantApproved (Identity provisions the first admin, taking
 *     over the applicant's password hash),
 *  7. bind the applicant to the tenant and clear their password — it
 *     now lives on the school admin account only.
 *
 * The applicant is told about the decision by mail AFTER the transaction
 * committed (ApplicantDecisionNotifier) — a mail must never announce a
 * school that was rolled back.
 *
 * Platform reads the onboarding config; it never hardcodes modules.
 */
final class DefaultTenantApplications implements TenantApplications
{
    /**
     * Required school keys for submit/resubmit payloads.
     *
     * @var array<int, string>
     */
    private const REQUIRED = ['school_name', 'desired_slug'];

    public function __construct(
        private readonly ModuleFlagManager $flags,
        private readonly SubscriptionManager $subscriptions,
        private readonly ApplicantDecisionNotifier $notifier,
    ) {}

    public static function payloadKeys(): array
    {
        return ['school_name', 'desired_slug', 'timezone', 'plan_key', 'applicant_message'];
    }

    public function submit(int $applicantId, array $school): ApplicationData
    {
        $applicant = $this->applicant($applicantId);

        $existing = TenantApplication::query()->where('applicant_id', $applicant->id)->first();

        if ($existing !== null) {
            throw new InvalidApplicationException($existing->status === TenantApplicationStatus::Rejected
                ? 'The application was rejected; resubmit it instead of submitting a new one.'
                : "The applicant already has a {$existing->status->value} application.");
        }

        $this->assertPayload($school, $applicant->email);

        $application = new TenantApplication;
        $application->forceFill([
            'applicant_id' => $applicant->id,
            'applicant_name' => $applicant->name,
            'applicant_email' => mb_strtolower($applicant->email),
            ...$this->schoolAttributes($school),
            'status' => TenantApplicationStatus::Pending,
            'submitted_at' => now(),
        ])->save();

        return $this->toData($application);
    }

    public function resubmit(int $applicantId, array $school): ApplicationData
    {
        $applicant = $this->applicant($applicantId);

        $application = TenantApplication::query()
            ->where('applicant_id', $applicant->id)
            ->lockForUpdate()
            ->first();

        if ($application === null) {
            throw new InvalidApplicationException('The applicant has no application to resubmit.');
        }

        if ($application->status !== TenantApplicationStatus::Rejected) {
            throw new ApplicationNotPendingException(
                "Application [{$application->id}] is not rejected (current: {$application->status->value})."
            );
        }

        $this->assertPayload($school, $applicant->email, ignoreApplicationId: $application->id);

        // The provider's note stays: the reviewer sees what was asked for.
        $application->forceFill([
            'applicant_name' => $applicant->name,
            'applicant_email' => mb_strtolower($applicant->email),
            ...$this->schoolAttributes($school),
            'status' => TenantApplicationStatus::Pending,
            'submitted_at' => now(),
            'decided_at' => null,
            'decided_by' => null,
        ])->save();

        return $this->toData($application);
    }

    public function forApplicant(int $applicantId): ?ApplicationData
    {
        $application = TenantApplication::query()->where('applicant_id', $applicantId)->first();

        return $application === null ? null : $this->toData($application);
    }

    public function pending(): iterable
    {
        return TenantApplication::query()
            ->where('status', TenantApplicationStatus::Pending)
            ->orderBy('id')
            ->get()
            ->map(fn (TenantApplication $application): ApplicationData => $this->toData($application))
            ->all();
    }

    public function approve(int $id, int $decidedBy, array $payload = []): ApplicationData
    {
        $this->assertDeciderExists($decidedBy);

        $application = TenantApplication::query()->lockForUpdate()->findOrFail($id);

        if ($application->status !== TenantApplicationStatus::Pending) {
            throw new ApplicationNotPendingException(
                "Application [{$id}] is not pending (current: {$application->status->value})."
            );
        }

        // Provider corrections win: the FINAL school data is the merged
        // payload, re-validated with the exact tenant:create rules. No
        // applicant re-check: identity is already pinned to THIS row.
        $final = [
            'school_name' => $payload['school_name'] ?? $application->school_name,
            'desired_slug' => $payload['desired_slug'] ?? $application->desired_slug,
            'timezone' => $payload['timezone'] ?? $application->timezone,
        ];
        $this->assertSchoolData($final, ignoreApplicationId: $application->id);

        // A plan the provider picks here must be selectable. The plan the
        // applicant picked earlier is NOT re-checked: if it was archived
        // meanwhile, the trial simply does not start (see startTrial).
        $correctedPlan = $this->planKey($payload);

        if ($correctedPlan !== null) {
            $this->assertPlanSelectable($correctedPlan);
        }

        $planKey = $correctedPlan ?? $application->plan_key;

        [$tenantId, $passwordHandedOver] = DB::transaction(function () use ($application, $final, $planKey, $decidedBy, $payload): array {
            $tenant = Tenant::query()->create([
                'name' => $final['school_name'],
                'slug' => $final['desired_slug'],
                'timezone' => $final['timezone'],
                'status' => TenantStatus::Active,
            ]);

            // Flagged modules only; core is always active without a flag
            // row. Config-driven — no hardcoded module keys here.
            foreach ((array) config('tenancy.onboarding_modules', ['identity']) as $module) {
                $this->flags->enable($tenant->id, $module);
            }

            $this->startTrial($tenant->id, $planKey);

            $application->forceFill([
                'school_name' => $final['school_name'],
                'desired_slug' => $final['desired_slug'],
                'timezone' => $final['timezone'],
                'plan_key' => $planKey,
                'status' => TenantApplicationStatus::Approved,
                'admin_note' => $payload['admin_note'] ?? null,
                'decided_at' => now(),
                'decided_by' => $decidedBy,
            ])->save();

            $applicant = $application->applicant_id === null
                ? null
                : Applicant::query()->lockForUpdate()->find($application->applicant_id);

            $passwordHandedOver = $applicant?->password !== null;

            // Synchronous, INSIDE the transaction: Identity provisions
            // the first admin — a failing listener rolls everything back
            // so no tenant exists without its admin. The applicant's
            // password hash travels with the event so the admin keeps
            // the password they registered with.
            Event::dispatch(new TenantApproved(
                $tenant->id,
                $application->applicant_name,
                $application->applicant_email,
                $applicant?->password,
            ));

            // The password now lives on the school admin account: MOVED,
            // not copied, so no second credential is left behind. From
            // here on the applicant login opens the school session.
            $applicant?->forceFill(['tenant_id' => $tenant->id, 'password' => null])->save();

            return [$tenant->id, $passwordHandedOver];
        });

        $application->refresh();

        $this->notifier->approved($application, $tenantId, $passwordHandedOver);

        return $this->toData($application);
    }

    /**
     * New tenants start on a trial of the chosen plan, or of the default
     * trial plan when the application carries none. A missing plan
     * (master data not seeded, or archived since the applicant chose it)
     * must not block onboarding: the tenant just has no subscription
     * until the provider assigns one.
     */
    private function startTrial(string $tenantId, ?string $planKey): void
    {
        try {
            $this->subscriptions->startTrial($tenantId, $planKey ?? (string) config('billing.trial_plan'));
        } catch (BillingException $e) {
            Log::warning('Trial not started for new tenant: '.$e->getMessage(), ['tenant_id' => $tenantId]);
        }
    }

    public function reject(int $id, ?string $note, int $decidedBy): ApplicationData
    {
        $this->assertDeciderExists($decidedBy);

        $application = TenantApplication::query()->lockForUpdate()->findOrFail($id);

        if ($application->status !== TenantApplicationStatus::Pending) {
            throw new ApplicationNotPendingException(
                "Application [{$id}] is not pending (current: {$application->status->value})."
            );
        }

        $application->forceFill([
            'status' => TenantApplicationStatus::Rejected,
            'admin_note' => $note,
            'decided_at' => now(),
            'decided_by' => $decidedBy,
        ])->save();

        $this->notifier->rejected($application);

        return $this->toData($application);
    }

    private function applicant(int $applicantId): Applicant
    {
        $applicant = Applicant::query()->find($applicantId);

        if ($applicant === null) {
            throw new InvalidApplicationException("Applicant [{$applicantId}] does not exist.");
        }

        return $applicant;
    }

    /**
     * The columns a submission writes from the school payload.
     *
     * @param  array<string, mixed>  $school
     * @return array<string, mixed>
     */
    private function schoolAttributes(array $school): array
    {
        return [
            'school_name' => $this->str($school, 'school_name'),
            'desired_slug' => $this->normalizedSlug($school),
            'timezone' => $this->normalizedTimezone($school),
            'plan_key' => $this->planKey($school),
            'applicant_message' => $school['applicant_message'] ?? null,
        ];
    }

    /**
     * Full submission-shape validation for a school payload coming from
     * the applicant with the given email.
     *
     * @param  array<string, mixed>  $school
     */
    private function assertPayload(array $school, string $applicantEmail, ?int $ignoreApplicationId = null): void
    {
        $errors = [];

        foreach (self::REQUIRED as $key) {
            if (! isset($school[$key]) || trim((string) $school[$key]) === '') {
                $errors[] = "The {$key} is required.";
            }
        }

        if ($errors !== []) {
            throw new InvalidApplicationException(implode(' ', $errors));
        }

        $this->assertSchoolData([...$school, 'applicant_email' => $applicantEmail], $ignoreApplicationId);

        $planKey = $this->planKey($school);

        if ($planKey !== null) {
            $this->assertPlanSelectable($planKey);
        }
    }

    /**
     * School-data rules — EXACTLY the tenant:create ones: slug shape,
     * reserved list, taken (incl. trashed tenants), duplicate pending
     * application, valid IANA timezone, plus the applicant-email
     * uniqueness among pending applications. ignoreApplicationId
     * excludes the application under approval or resubmission from the
     * pending checks (its own row must not block itself).
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertSchoolData(array $payload, ?int $ignoreApplicationId = null): void
    {
        $errors = [];

        $slug = $this->normalizedSlug($payload);

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $errors[] = "The slug [{$slug}] must be lowercase letters, digits, and dashes.";
        } elseif (in_array($slug, (array) config('tenancy.reserved_slugs', []), true)) {
            $errors[] = "The slug [{$slug}] is reserved.";
        } elseif (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $errors[] = "The slug [{$slug}] is already taken.";
        } elseif (TenantApplication::query()
            ->whereKeyNot($ignoreApplicationId ?? 0)
            ->where('desired_slug', $slug)
            ->where('status', TenantApplicationStatus::Pending)
            ->exists()) {
            $errors[] = "The slug [{$slug}] is already pending approval.";
        }

        $timezone = $this->normalizedTimezone($payload);

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $errors[] = "The timezone [{$timezone}] is not a valid IANA timezone.";
        }

        if (isset($payload['applicant_email'])) {
            $email = $this->normalizedEmail($payload);

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = "The email [{$email}] is not a valid email address.";
            } elseif (TenantApplication::query()
                ->whereKeyNot($ignoreApplicationId ?? 0)
                ->where('applicant_email', $email)
                ->where('status', TenantApplicationStatus::Pending)
                ->exists()) {
                $errors[] = "The email [{$email}] already has a pending application.";
            }
        }

        if ($errors !== []) {
            throw new InvalidApplicationException(implode(' ', $errors));
        }
    }

    /**
     * A plan can be chosen only while it is active and not archived.
     */
    private function assertPlanSelectable(string $planKey): void
    {
        if (! Plan::query()->selectable()->where('key', $planKey)->exists()) {
            throw new InvalidApplicationException("The plan [{$planKey}] is not available.");
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function planKey(array $payload): ?string
    {
        $planKey = trim((string) ($payload['plan_key'] ?? ''));

        return $planKey !== '' ? $planKey : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function str(array $payload, string $key): string
    {
        return trim((string) $payload[$key]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizedSlug(array $payload): string
    {
        return mb_strtolower(trim((string) ($payload['desired_slug'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizedTimezone(array $payload): string
    {
        $timezone = trim((string) ($payload['timezone'] ?? ''));

        return $timezone !== '' ? $timezone : (string) config('tenancy.default_timezone', 'Asia/Jakarta');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizedEmail(array $payload): string
    {
        return mb_strtolower(trim((string) ($payload['applicant_email'] ?? '')));
    }

    private function assertDeciderExists(int $decidedBy): void
    {
        if (! ProviderUser::query()->whereKey($decidedBy)->exists()) {
            throw new InvalidArgumentException("Provider user [{$decidedBy}] does not exist.");
        }
    }

    private function toData(TenantApplication $application): ApplicationData
    {
        return ApplicationDataMapper::map($application);
    }
}
