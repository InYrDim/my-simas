# Module: Platform

## Owns

- Database tables: `tenants`, `tenant_modules`, `tenant_applications`
  (school applications: applicant onboarding → provider review),
  `applicants` (central accounts of people registering a school), `plans`,
  `subscriptions`, `invoices`, `payments`, `billing_notices` (provider-side
  subscription billing), Spatie
  permission tables (`permissions`, `roles`, `model_has_permissions`,
  `model_has_roles`, `role_has_permissions`), `provider_users`,
  `whatsapp_instances` (one per tenant: the school's WhatsApp request, the
  provider's decision and its gateway session), `provider_settings`
  (settings of the provider itself, one row per key)
- Core domain concepts: tenancy (tenant resolution, tenant context,
  tenant-scoped models, tenant-aware cache/storage/queue propagation),
  the module registry, per-tenant feature flags, permission registry,
  Spatie-permission integration for tenant-scoped roles (Stage 6),
  school onboarding (application → provider ACC → tenant
  provisioning, Fase 2), and each school's WhatsApp number on the
  provider's OpenWA gateway (request → approval → linking → sending,
  Fase 9).

## Public interface (Contracts/)

- `TenantData` — readonly DTO (id, name, slug, timezone, status). The
  internal `Tenant` model is never handed out.
- `TenantContext` — current-tenant accessor: `current()`, `currentOrFail()`,
  `id()`, `timezone()`, `set()`, `forget()`, `run()` (nested-safe,
  restores previous context in `finally`), `runWithoutTenant()`.
  Singleton; bound as `DefaultTenantContext` with a class alias so
  concrete and interface type-hints share one instance.
- `TenantCache` + `PartitionedTenantCache` (Stage 4): cache partitioned
  per tenant — every key is prefixed `tenant:{id}:` (central: `central:`).
  API: `repository()`, `key()`, `get()`, `put()`, `forget()`,
  `rememberForever()`, `lock()` (atomic lock named per tenant; throws
  when the store cannot lock). Backed by the default cache store,
  resolved lazily.
- `TenantStorage` + `PartitionedTenantStorage` (Stage 4): file storage
  partitioned per tenant on the `local` disk — `path(module, relative)`
  is rooted at `tenants/{id}/{module}/…` (central: `central/{module}/…`).
  `.`/`..` segments are resolved inside the partition, so a hostile
  relative path cannot climb out of the tenant directory. API: `path()`,
  `get()`, `put()`, `delete()`, `exists()`.
- `BelongsToTenant` + `TenantNotSetException` (Stage 3).
- `ModuleRegistry` (Stage 5): module keys register THEMSELVES from
  their own provider (`register(key, meta)`); Platform never hardcodes
  business module names. `markAlwaysActive(key)` opts a module out of
  per-tenant flagging (core declares itself, not Platform). API:
  `register()`, `exists()`, `all()`, `markAlwaysActive()`,
  `isAlwaysActive()`. Implementation `DefaultModuleRegistry` (in-memory,
  idempotent registration).
- `TenantModules` (Stage 5): read path for per-tenant flags —
  `isEnabled(module, ?tenantId)` (default: current tenant). Semantics:
  always-active → true without context; unknown key → false (fail
  closed); flag row → `enabled && (expires_at === null || future)`.
  Results cached via TenantCache (TTL 5 min), invalidated on every
  enable/disable — flag changes are visible on the next request.
  Flag-controlled lookups without tenant id/context throw
  `TenantNotSetException`.
- `UnknownModuleException` (Stage 5): enabling/disabling an
  unregistered key fails loudly instead of silently enabling nothing.
- `PermissionRegistry` (Stage 6): permission names registered by each
  module's own provider (`register(module, names)`); Platform never
  hardcodes business permission names. Materialisation happens via
  `permissions:sync` (Stage 7), never at boot.
- `HasTenantRoles` (Stage 6, trait in `Contracts/Concerns`): the public
  WRAPPER of Spatie's HasRoles — pulls in Spatie itself, so consumers
  (Identity, Stage 9) never import Spatie. API: `assignTenantRole`,
  `removeTenantRole`, `hasTenantRole`, `tenantRoleNames`,
  `flushTenantPermissionCache`, `syncTenantTeam`. Deptrac exception:
  PlatformPublic → Spatie is sanctioned for this one trait.
- `UnknownModuleException`, `TenantNotSetException` (Contracts/Exceptions).
- `TenantCreated` event (in `Contracts/Events`) — fired from the Tenant
  model's `created` hook; payload: tenant ULID.
- `TenantRoles` (Fase 2): `ensure($tenantId, $name, $permissions)` —
  idempotent create/update of the tenant's role holding EXACTLY the
  given permission set (syncs missing global permission rows before
  attaching); `names($tenantId)` — machine names visible to the tenant
  (own rows + global). Runs its own `TenantContext::run` (safe from
  central/CLI); fails closed on an unknown tenant id — never a global
  role.
- `TenantUrl` (Fase 2): `host()`, `scheme()`, `port()`, `root()`,
  `url($tenantId, $path, $query)` — links built inside queued mails
  (queue workers have no trustworthy request root). Every tenant
  shares the first central host; `url()` appends `school=<tenant id>`
  so the link resolves the tenant on arrival. Scheme/port from
  `config('tenancy.url_scheme')` / `url_port`.
- `TenantApplications` (Fase 2, reshaped in Fase 4) + `ApplicationData`
  readonly DTO (Contracts/DTOs, now with `applicantId`, `planKey`,
  `submittedAt`): `submit($applicantId, $school)` (ONE application per
  applicant; slug reserved/taken checks; plan must be selectable; name
  and email come from the applicant account), `resubmit($applicantId,
  $school)` (a REJECTED application back to pending on the same row, the
  provider's note kept), `forApplicant($applicantId)`, `pending()`,
  `approve($id, $decidedBy, $payload)` — REVALIDATES the corrected
  slug (and a provider-corrected `plan_key`), creates the tenant, starts
  the trial on the application's plan (fallback
  `config('billing.trial_plan')`), enables onboarding modules from
  `config('tenancy.onboarding_modules')` (default `['identity']`;
  Platform reads the config, never hardcodes modules), fires
  `TenantApproved` INSIDE the transaction; `reject($id, $note,
  $decidedBy)`. Idempotent via `ApplicationNotPendingException`.
- `TenantApproved` event (Contracts/Events) — fired inside the
  approval transaction; payload: tenantId, applicantName,
  applicantEmail, and (Fase 4) `passwordHash` — the applicant account's
  hash, null for applications without an account. Never queued. Identity's `ProvisionFirstAdmin` consumes it (a
  failure rolls the whole approval back).
- `SchoolSessionOpener` (Fase 4): `attempt($tenantId, $email,
  $password, $remember): bool` — signs an active user of that school in
  on the school guard and remembers the school in the session; false for
  every failure alike. DEFINED here, IMPLEMENTED by Identity (dependency
  inversion: Platform cannot import school users). Platform's own
  binding, `NullSchoolSessionOpener`, always refuses.
- `TenantSession` (Fase 4): `remember($tenantId)` — the one sanctioned
  way for another module to set the session's school; the session key
  stays internal.
- `WhatsappChannel` (Fase 9) — the WhatsApp number of the school in the
  current tenant context; fails closed without one. `state(bool $refresh
  = false)`, `request(int $requestedBy)`, `connect()`, `disconnect()`,
  `sendText(string $phone, string $text)`. With `DTOs/WhatsappState`
  (`stage`: none, pending, rejected, disabled, active; `connection` = the
  gateway's session status; `phone`, `pushName`, `note`, `qrCode` as a
  PNG data URL, `lastError`, `requestedAt`; `connected()`) and
  `DTOs/WhatsappSendResult` (`sent`, `messageId`, `unavailable`, `error`,
  `retryable`). Nothing in these carries an API key, a key id or a
  gateway session id. A gateway that refuses or cannot be reached never
  throws: it comes back as `lastError` / a failed result, in words a
  school may read. See "WhatsApp per school" below.
- `TenantBilling` (Fase 17) — the school's own door to its subscription,
  on the current tenant (fails closed without one). `overview()`,
  `invoices()`, `offers()`, `subscribe($planKey, $cycle)`,
  `startPayment($invoiceNumber)`, `reportTransfer(...)`, `changePlan($planKey)`,
  `cancelScheduledChange()`, `changeCycle($cycle)`, `invoicePdf($invoiceNumber)`.
  DTOs (Contracts/DTOs): `BillingOverview`, `InvoiceSummary`, `PlanOffer`,
  `PaymentInstructions`, `PlanChangeOutcome`, `InvoiceFile`. An invoice is
  named by its number and must be the school's own, or the call refuses
  as not found (`BillingActionRefusedException::$notFound`). It checks no
  permission: the caller does (`platform.billing.view` / `.pay`).
  See "Subscription billing" below.
- `UsageMeters` + `TenantUsage` (Fase 17) with `DTOs/UsageLine` — what a
  school uses against its plan limits. A module registers its own meters
  (`register(module, key, label, unit, counter, ?limitKey)`) from its
  provider; `TenantUsage::forTenant($id)` computes them in the tenant's
  context (meters of inactive modules left out), `isOverLimit($key)` and
  `remaining($key)` read the current school. Display only: nothing blocks.
- Exceptions (Contracts/Exceptions): `TenantNotSetException`,
  `BillingActionRefusedException` (a school's billing action refused, in
  words the school can read), `UnknownModuleException`, `ApplicationNotPendingException`,
  `InvalidApplicationException` (slug conflicts at submit/approve),
  `WhatsappUnavailableException` (`connect`/`disconnect` for a school
  whose WhatsApp is not approved, or is disabled).

Internal (private): Tenant model + `TenantStatus` enum, TenantScope,
`SchoolCodeTenantResolver` (interface `TenantResolver`), `TenantHydrator`,
`TenantBridge` (context-change seam; Spatie hooks in Stage 6),
`ResolveTenant` middleware, `PlatformException` base,
`TenantApplication` model + status enum, the application review
controllers (guard `provider`, central-only), and the applicant
surface (Fase 4): `Applicant` model + guard `applicant`, registration at
`/daftar-sekolah` (IP throttle on POST + honeypot), `/pemohon/*` (login,
signed-URL email verification, onboarding), and the public landing page
`Platform/Landing` that the app shell renders at `/` for a visitor with
no session (its own visual world, scoped to `.landing-lembar`; content
in `Components/Landing/content.ts` is product truth only). Other modules access
tenancy only through contracts/DTOs — never the Tenant model.

## Tenant resolution (the one strategy)

Tenants are NOT resolved from the host. A school is identified by its
**school code** (the tenant's slug, which the provider can change on the
console tenant page › Pengaturan; the id still resolves for sessions —
`SchoolCodeTenantResolver` is the only place that mapping lives; the school's
own login address is `/<code>/login`; `TenantDirectory::findByCode` for
other modules):

- Console host (`config('tenancy.console_domain')`, default
  `console.localhost`) → never a tenant; hosts the provider console
  (`/login`, `/dashboard`, `/applications`).
- Logged-in session → the tenant stored in the session (`tenant_id`) is
  authoritative; a `school` input cannot switch it.
- Guest + `school` input (login/forgot-password form field, emailed
  link query) → resolved, then remembered in the session.
- Guest + session tenant → the school chosen earlier in the session.
- Unknown/malformed code → NO tenant context (no 404): the controllers
  answer generically (same error as a wrong password) so codes cannot
  be probed. Malformed codes never reach the database.
- Suspended tenant → 403 (resolver still resolves it; middleware decides).
- Lookups cached (5 min) as plain ids; models re-hydrated via `TenantHydrator`.
- Slug and custom `domain` columns are no longer used for resolution.

`ResolveTenant` middleware: forgets context at request start (and in the
`terminate` terminator — Octane-safe), resolves, adopts the DTO. Wired
globally: prepended to the `web` group AND the middleware priority list
(before `SubstituteBindings` and auth) in `bootstrap/app.php`. Alias
`tenant` exists for non-web contexts.

## Applicant accounts (Fase 4, in progress)

Plan and status: `docs/ai/plan/fase-4/tenant-onboarding-plan.md`.

- `applicants` is CENTRAL (never `BelongsToTenant`): an applicant exists
  before their tenant. `tenant_id` is nullable and filled at approval;
  `password` is nullable (invited-not-yet-activated, or handed over to
  the school admin at approval).
- Guard `applicant` (session), separate from `web` and `provider`. The
  pages use `AuthenticateApplicant` middleware instead of
  `auth:applicant`, because the app's guest redirect is host-aware and
  would send an applicant to the school login.
- Public registration exists ONLY for applicants. School users are still
  never self-registered (Identity's locked decision is unchanged).
- Email verification uses a temporary signed URL (60 min) carrying a hash
  of the email; no token table. Mail views live under the `Platform::`
  view namespace (`modules/Platform/mail`).
- An application belongs to its applicant through
  `tenant_applications.applicant_id` (unique, plain column). Rows from
  before accounts existed have it null and still approve/reject.
- Onboarding at `/pemohon` offers the selectable plans
  (`Plan::selectable()`, provider's `sort_order`); the choice is stored
  in `tenant_applications.plan_key`. The trial starts at APPROVAL, on
  that plan — before approval there is no tenant to subscribe.
- The applicant is mailed the decision AFTER the approval transaction
  committed (`ApplicantDecisionNotifier`; links from `TenantUrl::root()`
  because decisions are made on the console host).
- At approval the applicant's password hash travels on `TenantApproved`
  to Identity's first-admin listener and is then CLEARED on the
  applicant (moved, not copied); `applicants.tenant_id` is set. One
  credential only: the school admin account.
- The provider may also INVITE an applicant from the console
  (`/applicants`, guard `provider`): the account starts without a
  password and gets a link to set one. Invitation and forgot-password
  links come from `ApplicantAccessLinks`: temporary signed URLs, signed
  RELATIVE and prefixed with `TenantUrl::root()` (they are issued on
  the console host), carrying a hash of email + current password so
  each works once. Setting a password through one verifies the email.
- Forgot password answers generically. An approved applicant is mailed
  the SCHOOL reset page instead; an invited one, the invitation again.
- From then on `/pemohon/masuk` opens the SCHOOL session for that email
  through `SchoolSessionOpener` and redirects to `/`. No applicant
  session is created for an approved applicant.

## Queue context propagation (Stage 4)

`TenantQueueContext` (internal, registered by the provider on boot):

- `Queue::createPayloadUsing` stamps every payload with the dispatching
  tenant id under key `tenant_id` (only when a context is set).
- `Queue::before` SAVES the caller's context per job object
  (SplObjectStorage — `SyncJob::getJobId()` returns '' on every job, ids
  are unreliable), then sets the payload's tenant or forgets.
- `JobProcessed` / `JobFailed` / `JobExceptionOccurred` RESTORE the saved
  context (never a blind forget): the sync driver runs jobs inside the
  dispatching request, so the caller's context must survive; sequential
  jobs on one worker must not inherit each other's context. Restore is
  per job object, so nested/reentrant processing is safe.
- Jobs dispatched centrally run without tenant context.
- Applies automatically to queued listeners, mailables, notifications.

## Module registry & per-tenant flags (Stage 5)

- `tenant_modules` row per (tenant_id, module): `enabled`, `enabled_at`,
  `expires_at` (nullable — trials), `meta` json.
- Read path: `TenantModules::isEnabled()`; write path: internal
  `ModuleFlagManager` (`enable($tenantId, $module, ?$expiresAt)` /
  `disable()`) — consumed by Platform's own commands (Stage 7
  `tenant:modules`), not exposed as a contract.
- Cache: internal `TenantModulesCache` over `TenantCache` — key
  `modules:enabled:{tenantId}:{module}` inside the tenant partition.
- Middleware alias `module:{key}` (`EnsureModuleActive`): inactive or
  unknown module → **403** (recorded decision, not 404); core always
  passes. Runs after `ResolveTenant` (requires tenant context).
- `tenant_modules` lookups use `Model::withoutTenancy()` with an
  explicit `tenant_id` where-clause (flag checks must work from central
  and CLI, not just with ambient context).
  Testing note: Laravel's `flushState()` clears ALL `createPayloadUsing`
  hooks between tests — tests re-register via `app(TenantQueueContext::class)->register()`
  in their setup. Also: dispatch inside a `void` closure — `fn () => Job::dispatch()`
  RETURNS the PendingDispatch, whose destructor defers the push until after
  `run()` restored the context (payload stamped with the wrong tenant).

## Permission tenancy (Stage 6)

Spatie `teams = true` with `team_foreign_key = 'tenant_id'` (config
published and owned here). Design decisions (user-approved):

- **`roles.tenant_id` NULLABLE** — Spatie teams semantics: null means a
  GLOBAL role visible to every tenant. The (tenant_id, name,
  guard_name) unique key keeps same-named roles apart.
- **Pivot `tenant_id` REQUIRED and part of the primary key**
  (`model_has_roles` / `model_has_permissions`): every assignment
  belongs to exactly one tenant — never null, never global.
- The permission cache resets ONLY on an actual team change
  (`TenantPermissionBridge` guards), so same-tenant requests keep the
  cache warm.
- The Spatie team pointer is driven by `TenantBridge`: every context
  change (set/adopt/forget/restore) lands on Spatie via
  `TenantPermissionBridge` — the ONLY internal class calling Spatie's
  team API.
- `TenantRoleResolver` (internal): resolve-or-create per-tenant role
  rows; throws `TenantNotSetException` without context (a missing
  context must never silently create a GLOBAL role). Removal path uses
  `resolveOrNull` — removing never materialises rows.
- Spatie permission tables are a COPY of the published migration in
  `modules/Platform/database/migrations` (0001_01_01_000002): FKs to
  tenants.id are the legal Platform-owned cross-module exception.

## Provider users (Stage 6)

- `provider_users` table is CENTRAL (no tenant_id, never
  BelongsToTenant): SaaS staff, not school users.
- Guard `provider` (session) with provider `provider_users`
  (`config/auth.php` owned here). Guard separation enforced by routes
  in Stage 9; no `Gate::before` super-admin (recorded decision).
- `ProviderUser` model is internal (`App/Domain/Models`) — Stage 7
  adds the `provider:create-user` command.

## Commands (Stage 7)

`registerCommands()` in the provider registers: `tenant:create`,
`tenant:list`, `tenant:status` (abstract; `tenant:suspend` /
`tenant:activate`), `tenant:modules`, `tenant:run` (runs a command
inside a tenant's context; relays inner output via BufferedOutput),
`permissions:sync` (idempotent; runs in `runWithoutTenant`),
`provider:create-user` (`--password=` option, prompt fallback),
`cache:prune-expired` (deletes expired rows of the `database` cache
table; for a daily cron, no-op on other stores). Status
changes flush the `TenantHydrator` cache entry for the tenant.

Dev seeding: `PlatformDevSeeder` (local only, called from
`DatabaseSeeder`) creates sekolah-a (Jakarta; core+identity) and
sekolah-b (Makassar; core), a provider console login
`admin@simas.com` / `admin123`, and one pending application
(sekolah-c) so the review → ACC → provisioning flow runs end-to-end
without filling the public form. The dev login user
`admin@sekolah-a.test` / `password` gets its admin-sekolah role from
the root seeder (glue layer — legal).

## Frontend share (Stage 8)

`ShareTenantContext` middleware (prepended right after `ResolveTenant`,
before the Inertia middleware): shares `tenant` (`{name, slug,
timezone}` — null on central) and `modules` (sorted active keys) via
`Inertia::share()`. Deliberately opaque — no ids, no status enum. A third
prop, `billing` (Fase 17), is OPTIONAL (`Inertia::optional`): the account
panels ask for it with a partial reload when they open, and it carries the
school's subscription plus the URL of every billing action (built by
`Http/Support/SchoolBillingPayload`, only for `platform.billing.view`;
Shared may not import Platform, so it gets its links from this prop). A
fourth prop, `trial`, is lazy and goes to every signed-in user of the school
while it is on its trial or in the grace period after it (`state`, `endsOn`,
`daysLeft`, `accessEndsOn`; null otherwise, and for an exempt school); the
trial banner in Shared's `TenantShell` reads it (`Http/Support/TrialNoticePayload`).
Hooks
live in Shared (`useTenant()`, `useModules()`, `hasModule(key)`), types
in `modules/Shared/resources/js/types/tenant.ts` re-exported by root
`resources/js/types`.

## Session isolation (Stage 9)

`EnsureSessionTenant` middleware (web group, appended last): on a
request with tenant context, an authenticated user whose `tenant_id`
attribute differs from the resolved tenant is logged out and the
session invalidated. Generic by design — inspects the `tenant_id`
attribute, never imports Identity. Primary isolation is the per-session tenant
(`tenant_id` in the session); schools now share one host, so this
middleware is the second layer: a session whose user belongs to another
tenant is logged out.

## Allowed dependencies

- Modules/Shared
- Laravel/Vendor (Deptrac: Platform layer)
- Spatie (from Stage 6; only module allowed — dedicated `Spatie` layer)

PlatformPublic additionally: Laravel types OK (Eloquent collections,
Carbon), never general vendor/Spatie. NativePhp (SPL) is accessible from
every layer via the `php_internal` collector.

## Events published

- `TenantCreated` — when a tenant row is created (also via factory).
- `TenantApproved` (Fase 2) — inside the application-approval
  transaction; consumed by Identity (first-admin provisioning).

## Events consumed

- None.

## Subscription billing & master data (provider console and school)

Billing lives inside Platform (no Billing module). Internal except what is
listed under "Public additions" at the end of this section. Fase 17 made
it ready for real money; the decisions behind it, and the stages, are in
`docs/ai/plan/fase-17/billing-siap-nyata-plan.md`. Terms: `CONTEXT.md`.

- **Tables.** `plans` (key, name, `price_monthly`, `price_yearly` in whole
  rupiah, `limits` json — `students`, `staff_accounts`, `storage_mb`, a
  missing key = no limit — `modules` json, `is_active`, `is_public`,
  `sort_order`, `archived_at`; a non-public plan is not offered at sign-up
  but the provider can assign it to one school), `subscriptions` (ONE per
  tenant, unique `tenant_id`; `plan_id` and `scheduled_plan_id` are plain
  indexed columns, no FK), `invoices` (`number` INV-YYMM-####, `kind`
  activation|renewal|upgrade, snapshot `plan_name`/`billing_cycle`/`amount`,
  `status` unpaid|paid|void), `payments` (one attempt to pay an invoice:
  `gateway`, `method`, `status` pending|paid|failed|expired, `reference`,
  `external_id` unique per gateway, `paid_on`, `confirmed_by`, `meta`),
  `billing_notices` (each billing message sent, unique per subscription +
  kind + anchor date for scheduled ones). `tenants` carries `billing_email`,
  `billing_name`, `billing_exempt` and `suspended_reason`
  (`billing`|`manual`). The only FK is `tenant_id → tenants`. `invoices`,
  `subscriptions`, `payments` and `billing_notices` have NO tenant scope:
  every query from the school side names the tenant itself.
- **States and access.** Stored `subscriptions.status` = `trial` | `active`
  | `cancelled`. Display states (`Subscription::displayState()`): `trial`,
  `trial_expired`, `active`, `due`, `overdue`, `cancelled`, computed from
  dates. Access: `accessEndsAt()` is the trial end or period end plus the
  grace period (`billing.trial_grace_days`, `overdue_grace_days`, 7 each);
  a cancelled subscription ("not renewed") keeps access to the end it had,
  with no grace. "Today" is `BillingClock::today()`, the date in
  `billing.timezone` (default Asia/Jakarta), not the UTC date.
- **Flow.** Approving an application starts a trial (`startTrial`, in the
  approval transaction; a missing trial plan only logs a warning).
  `activate()` issues an UNPAID `activation` invoice; `renew()` the next
  period's `renewal` invoice (due the day the paid period ends). At most
  one unpaid invoice exists per subscription: asking again for the same
  plan and cycle returns it, anything else voids the old one.
  `billing:daily` issues renewals 14 days ahead.
- **Payments.** `PaymentGateway::initiate(Invoice): Payment` creates (or
  reuses) a pending Payment with the way to pay; bound to
  `ManualTransferGateway` (the provider's own bank account from
  `config('billing.issuer.bank')`). The ONLY door from a payment to an
  invoice is `SubscriptionManager::settle(Payment, PaymentOutcome)`: it
  locks the invoice row, is a no-op when the invoice is already paid, refuses
  an amount that differs from the invoice, and computes the real period at
  payment time (continuing from the old end while it runs, else from today).
  Failed and expired outcomes only change the Payment. The provider
  confirms a manual transfer in the console (method, reference, paid date,
  note; the confirming user is recorded); what the school reported
  (`payments.meta.reported`) is shown there. A real gateway implements
  `initiate()` and calls `settle()` from its webhook.
- **Plan change.** A school's own change (`requestPlanChange`, public plans
  only): during a trial at once; on a paid period a dearer plan (compared at
  the current cycle) is an `upgrade` invoice for the prorated difference
  (ceil of price difference × remaining days ÷ period days), the current plan
  staying until it is paid, and an equal or cheaper plan is a downgrade
  scheduled for the end of the period (`scheduled_plan_id`). A plan or cycle
  change voids open invoices, so paying a stale one cannot revert it.
  `SubscriptionManager::changePlan` stays as the PROVIDER's immediate,
  no-charge override.
- **Module flags.** `tenant_modules.source` is `plan` or `manual`. A plan
  sync (`syncModules`) only touches flags it set itself; a module the
  provider switched on or off by hand survives renewals and plan changes.
  `core` and `config('tenancy.onboarding_modules')` are never disabled.
- **Suspension.** `TenantLifecycle::suspend($tenant, SuspensionReason)`:
  `billing` (access ran out) is reopened by the first confirmed payment or a
  trial extension; `manual` never is. A suspended school gets ONE neutral
  403 page (`TenantSuspendedException`, view `Platform::tenant-suspended`)
  naming the school and the provider's contact and nothing about billing;
  WhatsApp sending stops (`WhatsappSendResult::suspended()`, closed as not
  sent). `billing_exempt` takes a school out of billing:daily, reminders and
  the revenue figures.
- **Messages and documents.** `BillingNotifier` sends seven queued text
  mails to `tenants.billing_email` (invoice issued, due reminder, trial
  ending, overdue, payment received, access stopped, access reopened) and
  records each in `billing_notices`; a school with no contact gets a
  `failed` notice, never an error. `InvoiceDocument` renders the PDF
  (invoice and receipt are one document; `barryvdh/laravel-dompdf`) from the
  invoice snapshot and `config('billing.issuer')`, read live.
- **Daily job.** `php artisan billing:daily` (cron, see `docs/config/cron.md`):
  renewals, reminders, suspension, payment expiry; each step on its own;
  `--only`, `--date`, `--dry-run`, `--force`; a guard holds back a mass
  suspension past `billing.suspend_cap` until `--force`; the console shows
  when it last finished (`billing.last_run_at`).
- **School side.** `TenantBilling` (above) behind permissions
  `platform.billing.view` and `platform.billing.pay` (the school admin holds
  both from Identity's `roles.php`; run `php artisan roles:sync` after
  release). Actions live under `/langganan/*` (`school.billing.*`).
- **Seeding.** `BillingMasterDataSeeder` (idempotent, production-safe) creates
  the initial plans. Run in production with
  `php artisan db:seed --class="Modules\Platform\Database\Seeders\BillingMasterDataSeeder"`.
  `PlatformDevSeeder` (local only) calls it and gives `sekolah-a` an active
  subscription and `sekolah-b` a trial.

### Master data & assumptions (confirm / change as needed)

| Item | Current value | Where |
|---|---|---|
| Plan Starter | Rp150.000/bln, Rp1.500.000/thn, 300 students, 25 staff accounts, core+identity+attendance | `BillingMasterDataSeeder` |
| Plan Standard | Rp350.000/bln, Rp3.500.000/thn, 1.000 students, 100 staff accounts, core+identity+attendance | same |
| Plan Pro | Rp750.000/bln, Rp7.500.000/thn, no limits, core+identity+attendance+ppdb | same |
| Storage limit | none until the provider sets one in the console | plans table |
| Yearly price | 10 × monthly (assumption) | same |
| Trial | 14 days, plan `starter`, automatic on approval | `config/billing.php` |
| Grace period | 7 days after a trial / paid period ends, then suspended by `billing:daily` | `config/billing.php` |
| Invoice due | activation: 7 days after issue; renewal: the day the period ends | `config/billing.php` |
| Renewal invoice | issued 14 days before the period ends | `config/billing.php` |
| Reminders | trial 3 days before; due 7 and 1 days before; late 1 and 5 days after | `config/billing.php` |
| "Due soon" window | 7 days | `config/billing.php` |
| Mass-suspension guard | more than 5 schools, or 20% of billed schools (once there are 10) | `config/billing.php` |
| Provider identity and bank account | `BILLING_ISSUER_*` in `.env` | `config/billing.php` |
| Plan modules | Every plan includes `attendance` (decision of 2026-10-02: every school gets Absensi). Only Pro includes `ppdb`; a provider can also switch a module on per school (it then survives plan changes). Whether PPDB can be sold as a paid subscription to public schools (BOS rules, Pasal 66 Permendikdasmen 8/2026) is unconfirmed | plans table |
| Tax | no PPN line on invoices; depends on the provider's PKP status | — |

Public additions: `Contracts/TenantDirectory` (read-only tenant lookup for
other modules' provider pages), `TenantRoles::rolePermissions()`, and (Fase 17)
`Contracts/TenantBilling`, `Contracts/UsageMeters` and `Contracts/TenantUsage`
with their DTOs. Core registers the `students` meter and Identity the
`staff_accounts` meter from their own providers.

School-side navigation: `Contracts/TenantNavigation` is the registry for the
tenant sidebar. Each module registers its own entries (label, lucide icon
name, named route, optional permission, order) from its service provider;
`ShareTenantContext` shares the `tenantNav` prop (lazy), filtered by active
module and Gate. The generic `TenantShell` in Shared renders it.

## WhatsApp per school (Fase 9)

Schools send WhatsApp through the provider's own **OpenWA** gateway
(`https://docs.open-wa.org`, an unofficial WhatsApp API). Platform owns
the gateway side; Core owns the school page and the message log.

- **Config** (`config/services.php` → `openwa`): `OPENWA_API_BASE_URL`,
  `OPENWA_ADMIN_API_KEY` (creates sessions and keys; never leaves the
  server), `OPENWA_CREDENTIALS_KEY` (64 hex characters; SIMAS's own
  AES-256 key for the stored session keys — not an OpenWA setting),
  `OPENWA_TIMEOUT`.
- **Flow.** The school asks (`WhatsappChannel::request`) → the provider
  approves or rejects on the console page `/whatsapp`, or the provider
  setting `whatsapp.auto_approve` approves a new request on the spot →
  the school links its number by QR (`connect`, then `state(refresh:
  true)` polled by the page) → messages go out with `sendText`. The
  provider may disable a school (the session stops, the linked device is
  kept) and enable it again.
- **On approval** `ApproveWhatsappInstance` registers the session
  `simas-{tenant id, lowercase}` (a taken name → the existing session is
  reused) and mints one `operator` key scoped to that session
  (`allowedSessions`). Every later call uses that key; the admin key is
  only for sessions and keys.
- **The session key is never shown.** It is stored through
  `CredentialCast` (`CredentialVault`: AES-256-GCM with
  `OPENWA_CREDENTIALS_KEY`, not `APP_KEY`), hidden from every array/JSON
  form of the model, and absent from the contract DTOs, the console page
  and every error message. Without a usable credentials key no key is
  minted or stored. Changing `OPENWA_CREDENTIALS_KEY` makes the stored
  keys unreadable: every school then needs a new key.
- **School-readable errors.** `OpenWaException` messages name the call
  and the status and are for the provider; `forSchool()` is what reaches
  `lastError`, without URL or session id.
- **Decisions baked in.** School "Putuskan" = gateway `logout` then
  `stop` (the next link needs a new QR); provider "Nonaktifkan" = `stop`
  and holds in SIMAS even when the gateway does not answer. `connect`
  reads the session first and starts it only when it is not running.
  `sendText` decides "not linked" from the stored status, without a
  gateway call. Status comes from polling; there are no webhooks, so
  delivered/read receipts are unknown.
- **Models are plain** (`WhatsappInstance`, `ProviderSetting`; pattern of
  `Subscription`): read across schools from the console and always
  filtered by an explicit `tenant_id`.
- **Safe sending (Fase 16).** OpenWA is unofficial: WhatsApp can
  restrict or ban a school's number, and the risk is never zero. Platform
  protects the number in `SendGuard`, called by `sendText` before the
  gateway: a random pause between messages per school
  (`OPENWA_PACE_MIN`/`OPENWA_PACE_MAX`, seconds), a daily limit
  (`OPENWA_DAILY_LIMIT`, counted per school day, only for messages sent),
  and a circuit breaker that pauses the school after
  `OPENWA_BREAKER_THRESHOLD` retryable failures in a row for
  `OPENWA_BREAKER_PAUSE` seconds (4xx answers do not count). A held-back
  message comes back `WhatsappSendResult::throttled` with `retryAfter`:
  waiting is not a failure. All state is integers in `TenantCache`.
  `WhatsappState` carries `pausedUntil`, `pauseReason` and
  `riskAcknowledged`. `acknowledgeRisk(userId)` records who accepted the
  warning (first acceptance wins; `whatsapp_instances.risk_acknowledged_*`,
  no FK) and `connect()` throws `WhatsappRiskNotAcknowledgedException`
  until then.
- **Tests fake the gateway** (`Http::fake()` + `Http::preventStrayRequests()`);
  the real gateway is never called from a test. With a URL map,
  `Http::fake` invokes every stub for every request, so a
  `Http::sequence()` must live in a single closure.

## Explicitly NOT exposed

- `WhatsappInstance`, `ProviderSetting`, `OpenWaClient`,
  `CredentialVault`, the request/approve/reject/disable/enable actions,
  `SyncWhatsappSession` and the console controller. Other modules reach
  WhatsApp only through `WhatsappChannel`.
- `Plan`, `Subscription`, `Invoice`, `Payment`, `BillingNotice`,
  `SubscriptionManager`, `InvoiceIssuer`, `PaymentGateway` / `ManualTransferGateway`,
  `BillingSummary`, `BillingNotifier`, `InvoiceDocument`, `BillingClock`, the
  `billing:daily` steps, `DefaultTenantBilling`, `DefaultUsageMeters`,
  `TenantLifecycle`, `TenantSuspendedException`.
- `Tenant` Eloquent model, `TenantStatus`, anything under
  `App/Domain/**`, `App/Infrastructure/**`, `App/Http/**`, `database/**`.
- The `TenantResolver` interface and its implementation — internal by
  design (single strategy).
- Direct Spatie classes (Stage 6) — consumers use `HasTenantRoles`.

## Notes for maintainers

- `tenants` migration is `0000_…` so it runs before all other modules'
  migrations — proven by `php artisan migrate:fresh` ordering.
- Central requests must NOT hit `currentOrFail()` (shared-props and
  central pages use `current()`/`id()` and handle null).
- Schools share one host, so session isolation rests on the session's
  own `tenant_id` + `EnsureSessionTenant`; a logged-in session ignores
  a different `school` input. Verified by test.
- `ProviderUser` lives here, not in Identity.
- Deptrac notes: `ClassLikeConfig::create()` doubles backslashes — write
  patterns with single backslashes. NativePhp layer uses
  `PhpInteralConfig` (sic, Deptrac's own typo) + PhpStorm stubs. Vendor
  layer requires a backslash so native symbols land only in NativePhp.
- **Trait flattening**: deptrac attributes a public trait's own imports
  to every consuming class — consumers of `BelongsToTenant` /
  `HasTenantRoles` are skipped per-class in `deptrac.baseline.yaml`.
  Grow that file ONLY when another module consumes a public trait.
- **Deptrac Database layer** may access Platform internals: the root
  `DatabaseSeeder` delegates to module dev seeders (seeding is app-level
  wiring, not a module dependency).
- `TenantHydrator` caches plain attribute ARRAYS, never models (see
  docs/architecture/modular-monolith.md → Tenancy traps), validates the
  cached `id`, never caches null, and exposes `flush($tenantId)` for
  status changes.
- **Fase 2 TODO parked here**: `expires_at` trial flags still need an
  expiry job. Resolved in Fase 2: `password_reset_tokens` is now
  tenant-scoped (Identity, Stage 4); `TenantCreated` has a listener
  (Identity's default-role seeding).
- **Trait-flattening scope note**: the Fase 2 contracts kept the
  baseline untouched — `TenantRoles`/`TenantUrl`/`TenantApplications`
  are pure interface/DTO/event surfaces, and Identity's trait consumers
  were already baselined per-class.
