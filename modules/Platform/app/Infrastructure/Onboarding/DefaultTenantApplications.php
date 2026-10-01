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
 *  4. stamp the decision columns,
 *  5. fire TenantApproved (Identity provisions the first admin).
 *
 * Platform reads the onboarding config; it never hardcodes modules.
 */
final class DefaultTenantApplications implements TenantApplications
{
    /**
     * Required keys for submit/approve payloads.
     *
     * @var array<int, string>
     */
    private const REQUIRED = ['school_name', 'desired_slug', 'applicant_name', 'applicant_email'];

    public function __construct(
        private readonly ModuleFlagManager $flags,
        private readonly SubscriptionManager $subscriptions,
    ) {}

    public static function payloadKeys(): array
    {
        return ['school_name', 'desired_slug', 'timezone', 'applicant_name', 'applicant_email', 'applicant_message'];
    }

    public function submit(array $payload): void
    {
        $this->assertPayload($payload);

        $application = TenantApplication::query()->create([
            'school_name' => $this->str($payload, 'school_name'),
            'desired_slug' => $this->normalizedSlug($payload),
            'timezone' => $this->normalizedTimezone($payload),
            'applicant_name' => $this->str($payload, 'applicant_name'),
            'applicant_email' => $this->normalizedEmail($payload),
            'applicant_message' => $payload['applicant_message'] ?? null,
            'status' => TenantApplicationStatus::Pending,
        ]);
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

        $tenantId = DB::transaction(function () use ($application, $final, $decidedBy, $payload): string {
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

            $this->startTrial($tenant->id);

            $application->forceFill([
                'school_name' => $final['school_name'],
                'desired_slug' => $final['desired_slug'],
                'timezone' => $final['timezone'],
                'status' => TenantApplicationStatus::Approved,
                'admin_note' => $payload['admin_note'] ?? null,
                'decided_at' => now(),
                'decided_by' => $decidedBy,
            ])->save();

            // Synchronous, INSIDE the transaction: Identity provisions
            // the first admin — a failing listener rolls everything back
            // so no tenant exists without its admin.
            Event::dispatch(new TenantApproved(
                $tenant->id,
                $application->applicant_name,
                $application->applicant_email,
            ));

            return $tenant->id;
        });

        return $this->toData($application->refresh());
    }

    /**
     * New tenants start on a trial. A missing trial plan (master data not
     * seeded) must not block onboarding: the tenant just has no
     * subscription until the provider assigns one.
     */
    private function startTrial(string $tenantId): void
    {
        try {
            $this->subscriptions->startTrial($tenantId, (string) config('billing.trial_plan'));
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

        return $this->toData($application);
    }

    /**
     * Full submission-shape validation (applicant identity required).
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertPayload(array $payload): void
    {
        $errors = [];

        foreach (self::REQUIRED as $key) {
            if (! isset($payload[$key]) || trim((string) $payload[$key]) === '') {
                $errors[] = "The {$key} is required.";
            }
        }

        if ($errors !== []) {
            throw new InvalidApplicationException(implode(' ', $errors));
        }

        $this->assertSchoolData($payload);
    }

    /**
     * School-data rules — EXACTLY the tenant:create ones: slug shape,
     * reserved list, taken (incl. trashed tenants), duplicate pending
     * application, valid IANA timezone, plus the applicant-email
     * uniqueness among pending applications. ignoreApplicationId
     * excludes the application under approval from the pending checks
     * (its own row must not block its own approval).
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
        );
    }
}
