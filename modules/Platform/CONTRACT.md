# Module: Platform

## Owns

- Database tables: `tenants`
- Core domain concepts: tenancy (tenant resolution, tenant context,
  tenant-scoped models, tenant-aware cache/storage/queue propagation),
  the module registry, permission registry, and Spatie-permission
  integration for tenant-scoped roles (Stage 6).

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
- `ModuleRegistry`, `TenantModules` (Stage 5).
- `PermissionRegistry`, `HasTenantRoles` (Stage 6).
- `TenantCreated` event (in `Contracts/Events`) — fired from the Tenant
  model's `created` hook; payload: tenant ULID.

Internal (private): Tenant model + `TenantStatus` enum, TenantScope,
`SubdomainTenantResolver` (interface `TenantResolver`), `TenantHydrator`,
`TenantBridge` (context-change seam; Spatie hooks in Stage 6),
`ResolveTenant` middleware, `PlatformException` base. Other modules access
tenancy only through contracts/DTOs — never the Tenant model.

## Tenant resolution (the one strategy)

- Host normalization: lowercase, strip port and trailing dot.
- Host ∈ `config('tenancy.central_domains')` → **null** → request runs
  without tenant (central). No fallback, no override.
- `"{slug}.{central}"` (exactly one subdomain level) → lookup by slug.
- Anything else → lookup by custom `domain` column.
- No match → generic 404 (`TenantMissingException` → NotFoundHttpException).
- Suspended tenant → 403 (resolver still resolves it; middleware decides).
- Lookups cached (5 min) as plain ids; models re-hydrated via `TenantHydrator`.

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

Testing note: Laravel's `flushState()` clears ALL `createPayloadUsing`
hooks between tests — tests re-register via `app(TenantQueueContext::class)->register()`
in their setup. Also: dispatch inside a `void` closure — `fn () => Job::dispatch()`
RETURNS the PendingDispatch, whose destructor defers the push until after
`run()` restored the context (payload stamped with the wrong tenant).

## Allowed dependencies

- Modules/Shared
- Laravel/Vendor (Deptrac: Platform layer)
- Spatie (from Stage 6; only module allowed — dedicated `Spatie` layer)

PlatformPublic additionally: Laravel types OK (Eloquent collections,
Carbon), never general vendor/Spatie. NativePhp (SPL) is accessible from
every layer via the `php_internal` collector.

## Events published

- `TenantCreated` — when a tenant row is created (also via factory).

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
- Session cookies stay host-only (`SESSION_DOMAIN` unset): a session from
  tenant A is not sent to tenant B's host. Verified by test.
- `ProviderUser` will live here (Stage 6), not in Identity.
- Deptrac notes: `ClassLikeConfig::create()` doubles backslashes — write
  patterns with single backslashes. NativePhp layer uses
  `PhpInteralConfig` (sic, Deptrac's own typo) + PhpStorm stubs. Vendor
  layer requires a backslash so native symbols land only in NativePhp.
