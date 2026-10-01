# Module: Platform

## Owns

- Database tables: `tenants`, `tenant_modules`, `tenant_applications`
  (school applications: central form → provider review), Spatie
  permission tables (`permissions`, `roles`, `model_has_permissions`,
  `model_has_roles`, `role_has_permissions`), `provider_users`
- Core domain concepts: tenancy (tenant resolution, tenant context,
  tenant-scoped models, tenant-aware cache/storage/queue propagation),
  the module registry, per-tenant feature flags, permission registry,
  Spatie-permission integration for tenant-scoped roles (Stage 6),
  school onboarding (application → provider ACC → tenant
  provisioning, Fase 2).

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
  `rememberForever()`. Backed by the default cache store, resolved lazily.
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
- `TenantApplications` (Fase 2) + `ApplicationData` readonly DTO
  (Contracts/DTOs): `submit(payload)` (slug reserved/taken checks,
  one pending application per email), `pending()`,
  `approve($id, $decidedBy, $payload)` — REVALIDATES the corrected
  slug, creates the tenant, enables onboarding modules from
  `config('tenancy.onboarding_modules')` (default `['identity']`;
  Platform reads the config, never hardcodes modules), fires
  `TenantApproved` INSIDE the transaction; `reject($id, $note,
  $decidedBy)`. Idempotent via `ApplicationNotPendingException`.
- `TenantApproved` event (Contracts/Events) — fired inside the
  approval transaction; payload: tenantId, applicantName,
  applicantEmail. Identity's `ProvisionFirstAdmin` consumes it (a
  failure rolls the whole approval back).
- Exceptions (Contracts/Exceptions): `TenantNotSetException`,
  `UnknownModuleException`, `ApplicationNotPendingException`,
  `InvalidApplicationException` (slug conflicts at submit/approve).

Internal (private): Tenant model + `TenantStatus` enum, TenantScope,
`SchoolCodeTenantResolver` (interface `TenantResolver`), `TenantHydrator`,
`TenantBridge` (context-change seam; Spatie hooks in Stage 6),
`ResolveTenant` middleware, `PlatformException` base,
`TenantApplication` model + status enum, the application review
controllers (guard `provider`, central-only) and the public
`/daftar-sekolah` form (IP throttle + honeypot). Other modules access
tenancy only through contracts/DTOs — never the Tenant model.

## Tenant resolution (the one strategy)

Tenants are NOT resolved from the host. A school is identified by its
**school code** (the tenant id today; NPSN later — `SchoolCodeTenantResolver`
is the only place that mapping lives):

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
`provider:create-user` (`--password=` option, prompt fallback). Status
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
`Inertia::share()`. Deliberately opaque — no ids, no status enum. Hooks
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

## Explicitly NOT exposed

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
