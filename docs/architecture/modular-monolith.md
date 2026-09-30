# Modular Monolith — Architecture

SIMAS is developed as a **modular monolith**: one deployable Laravel
application whose internal boundaries are enforced by tooling, not by
convention alone. Two enforcement layers work together:

1. **Deptrac** (`deptrac.php`, run via `composer deptrac`) — class-level
   dependency rules between layers.
2. **Pest arch tests** (`tests/Architecture/ModularMonolithTest.php`) —
   rules that are awkward in Deptrac: import scans for non-class code,
   migration FK scanning (with the tenant_id exception), CONTRACT.md
   presence per module, and the Spatie-import ban outside Platform.

A change that crosses a boundary illegally fails `composer deptrac`,
`composer test`, and CI.

The agent skill that operationalizes these rules is mirrored (tracked)
at `docs/skills/modular-monolith/` — the gitignored working copy lives
in `.claude/skills/modular-monolith/`; keep the two in sync.

## Layer order (bottom → top)

```
Vendor/Laravel  ←  Shared  ←  Platform (Fase 1)  ←  Identity  ←  Core  ←  App/Database glue
```

- **Vendor / Laravel** — the framework and composer packages.
- **Shared** (`Modules\Shared`) — pure technical utilities. Knows no
  business concepts and no other module.
- **Platform** (`Modules\Platform`) — tenancy (resolution, context,
  tenant-scoped models, tenant-partitioned cache/storage, queue
  propagation), module registry, per-tenant feature flags, permission
  registry + Spatie teams integration, provider users, tenant/provider
  Artisan commands. Public surface: `Modules\Platform\App\Contracts`.
- **Identity** (`Modules\Identity`) — tenant-scoped users and session
  authentication (login/logout): `User` model (uses Platform's
  `BelongsToTenant` + `HasTenantRoles`), factory, `users`/
  `password_reset_tokens`/`sessions` migrations, `ResolvesUsers`
  contract, `UserRecord` DTO, policy, login rate limiting. (See “Folder
  shape” below: module code lives under the module's own `app/` tree,
  e.g. `Modules\Identity\App\Domain\Models\User`.)
- **Core** (`Modules\Core`) — master data (skeleton in Fase 0).
- **App / Database** — Laravel glue only: providers, config, root
  seeders. No business logic. (`DatabaseSeeder` creating the example
  user via `UserFactory` is the single documented exception.)

## Modules

| Module   | Layer      | Owns                                  | Public surface                                                   |
| -------- | ---------- | ------------------------------------- | ---------------------------------------------------------------- |
| Shared   | `Shared`   | Generic technical utilities           | Everything (by definition)                                       |
| Platform | `Platform` | Tenancy, module registry, permissions | `Modules\Platform\App\Contracts`                                 |
| Identity | `Identity` | Tenant-scoped users, login/logout     | `Modules\Identity\App\Contracts` (`ResolvesUsers`, `UserRecord`) |
| Core     | `Core`     | Master data (skeleton)                | `Modules\Core\App\Contracts` (empty)                             |

Each module folder follows the module template:

```
modules/<Name>/
  app/
    Contracts/                # PUBLIC surface — the only cross-module API
    Domain/                   # private: Models/, Actions/, Events/, Policies/
    Infrastructure/           # private: Repositories/, Providers/
    Http/                     # private: Controllers/, Requests/, Resources/
  resources/js/               # Pages/, Components/ (module frontend)
  routes/web.php, routes/api.php
  database/migrations|factories|seeders/   # module-owned
  tests/Feature|Unit/                       # module-owned tests
  CONTRACT.md                 # documents the public surface
```

Composer autoload maps `Modules\<Name>\App\`, `Modules\<Name>\Database\`
and `Modules\<Name>\Tests\` to those folders (PSR-4 is case-sensitive on
the `app/` vs `App\` boundary — follow the template exactly).

## Boundary rules

1. **Cross-module access only via Contracts.** Never import another
   module's `Domain\`, `Infrastructure\`, `Http\`, or `Database\`
   namespaces. Deptrac layer `XPublic` contains only
   `Modules\X\App\Contracts\**`. A module's Public surface may use its
   own internals (e.g. a public trait delegating to internal machinery)
   but never another module's internals.
2. **No cross-module foreign keys.** References to other modules' data
   are plain columns (`user_id` etc.), no constraint, no Eloquent
   relation. The arch test scans every migration for `->foreign(`,
   `->constrained(`, `->foreignIdFor(`, `->foreignUuid(` — the sole
   exception is `tenant_id` → `tenants` (owned by Platform, Fase 1);
   lines containing `tenant` are whitelisted.
3. **Contracts never expose Eloquent models.** Public methods return
   DTOs (`Contracts/DTOs`), scalars, or DTO collections. Public events
   that other modules listen to live in the publisher's
   `Contracts/Events`; internal events stay in `Domain/Events`.
   Listeners live in the listening module and register in its provider.
   `app/Events` is only for legacy code not yet extracted.
4. **One-way dependencies.** `Shared` depends on nothing module-ish;
   `Core` may use `IdentityPublic` but not the reverse; feature modules
   (Fase 1+: `attendance`, `ppdb`) never depend on each other.
5. **Other modules must not import `User`.** Store `user_id` as a plain
   column and resolve users through `ResolvesUsers` → `UserRecord`.
6. **Shared stays clean.** All of Shared is importable by every module,
   but its contents stay pure technical utilities — no school business
   concepts (student, class, teacher, attendance, PPDB), nothing used
   by only one module. Business-meaningful code "accidentally used by
   two modules" is reported (candidate contract / Core module), never
   parked in Shared. See `modules/Shared/CONTRACT.md`.
7. **Module creation is authorized per phase.** Platform (Fase 1) and
   Identity are explicitly authorized. Any other module (Attendance,
   Ppdb, …) needs the user's go-ahead in its own phase, and extraction
   proceeds one module per stage.

## Frontend layout

Inertia pages live per module in `modules/<Name>/resources/js/Pages` and
are resolved by the explicit resolver in `resources/js/app.tsx` (the
`@inertiajs/vite` shorthand only supports one page directory). Module
pages are referenced as `"<Module>/<Page>"`; root pages keep their bare
names. Generic UI and cross-module hooks go in
`modules/Shared/resources/js` (import via the `@shared/` alias; `@/`
still points at `resources/js`). Module pages/components never import
another module's internals.

Platform shares two props with the frontend (`ShareTenantContext`
middleware, before the Inertia middleware): `tenant` — `{name, slug,
timezone}` or `null` on central hosts — and `modules` — the sorted list
of module keys active for the current tenant. `modules/Shared/resources
/js/hooks/useTenant.ts` exposes `useTenant()`, `useModules()` and
`hasModule(key)`; they read shared props only and know nothing of
Platform internals.

## Running the checks

```bash
composer deptrac        # dependency rules
vendor/bin/pest         # full suite incl. arch tests
vendor/bin/pint --test  # code style
composer types:check    # PHPStan (also analyses modules/)
npm run build           # frontend
composer test           # lint + phpstan + deptrac + tests
```

CI (`.github/workflows/tests.yml`) runs `composer ci:check`, which runs
the npm checks and the full composer `test` chain — so a boundary
violation fails the pipeline.

`vendor/bin/deptrac debug:unassigned` lists classes no layer claims;
today that list is empty by design.

## Baseline policy

Deptrac 4 supports baselining existing violations. The baseline file
`deptrac.baseline.yaml` (loaded from `deptrac.php`) exists since Fase 1
Stage 9 with exactly ONE class of skip: **trait flattening**. Deptrac
attributes a trait's own imports to every class that `use`s it — so
Identity's `User` (which uses PlatformPublic's `BelongsToTenant` /
`HasTenantRoles`, the sanctioned touchpoint) would otherwise "depend on"
Spatie and Platform internals itself. Skips are per-class and documented
in the file header; they may grow ONLY when another module consumes one
of Platform's public traits — never as a general escape hatch. Any other
violation must be fixed, not baselined.

## Creating a new module

1. Create `modules/<Name>/` with the folder shape above
   (`Contracts/` included even if empty).
2. Add `app/Infrastructure/Providers/<Name>ServiceProvider` (registers
   the module's migrations, routes, bindings), and register it in
   `bootstrap/providers.php`.
3. Register the PSR-4 root once: `Modules\` → `modules/` is already in
   `composer.json` (no change needed), then `composer dump-autoload`.
4. Map `Modules\<Name>\App\`, `Modules\<Name>\Database\`,
   `Modules\<Name>\Tests\` in `composer.json` (per-subnamespace, as the
   existing modules do) and point the module's `ServiceProvider` at its
   own `database/migrations`, `routes/web.php`, and `routes/api.php`.
5. Add two Deptrac layers in `deptrac.php` — `<Name>` (internal:
   `Modules\<Name>\App\(?!Contracts\).*` plus `Database|routes`)
   and `<Name>Public` (`Modules\<Name>\App\Contracts\.*`) — then wire
   the ruleset following the one-way order: internal → `Shared`,
   `Laravel`, `Vendor`, `PlatformPublic`, and `Public` surfaces of
   _lower_ modules only.
6. Add the module to the `$modules` list in
   `tests/Architecture/ModularMonolithTest.php`.
7. Write a filled `CONTRACT.md` (every template section completed —
   the arch test rejects missing/placeholder files). For feature
   modules, note the module key and registered permissions there.
8. Run `composer dump-autoload && composer deptrac && vendor/bin/pest`.

## Tenancy rules (Fase 1 — built)

Every business table is tenant-scoped. The playbook:

1. **Migration**: create the column with `$table->tenantId()` (Blueprint
   macro from Platform) — char(26) ULID, FK to `tenants.id` (the sole
   legal cross-module FK), indexed. For a unique constraint, almost
   always make it composite with tenant: `unique(['tenant_id','email'])`
   — a global-unique column (email alone) silently forbids the same
   value in two tenants.
2. **Model**: `use BelongsToTenant` (PlatformPublic) — queries are
   auto-filtered by the current tenant; `creating` fills `tenant_id`
   from context; `updating` refuses to change it. Queries without
   context FAIL CLOSED (`TenantNotSetException`) — wrap in
   `TenantContext::run($id, ...)` or `runWithoutTenant()` only for
   deliberate central/cross-tenant work.
3. **Queries inside `DB::table()` are NOT scoped** — the global scope
   only protects Eloquent. Never use `DB::table()` for tenant-owned
   data; if unavoidable (rare reporting), the tenant id must appear in
   every where-clause.
4. **Cross-tenant queries**: `Model::withoutTenancy()` — conspicuous on
   purpose, review accordingly. Also: an explicit-`tenant_id` Eloquent
   query still needs context unless wrapped in `runWithoutTenant()`.
5. **Cache/storage/jobs**: use `TenantCache` / `TenantStorage`
   contracts (auto-partitioned per tenant). Jobs need nothing special —
   `TenantQueueContext` stamps payloads on dispatch and restores on
   run (sync-safe).
6. **Casing**: hosts are lowercased, but slugs/emails in the DB are
   case-sensitive as written. Normalise emails to lowercase at write
   time (login throttle keys already do).

Registering a module (feature flag): the module's own provider calls
`ModuleRegistry::register('key', ['label' => ...])` during boot;
`markAlwaysActive('key')` opts out of per-tenant flags (core does).
Routes behind the flag use the `module:{key}` middleware alias
(inactive/unknown → 403). Permissions: register names via
`PermissionRegistry::register(module, names)` in the provider;
materialise with `php artisan permissions:sync` (idempotent) — never at
boot.

### Tenancy traps (each one bit during Fase 1)

- **`WithoutModelEvents` in seeders kills tenancy.** The
  `BelongsToTenant::creating` hook is a model event — with it disabled,
  `tenant_id` is never filled and every insert violates NOT NULL.
  `DatabaseSeeder` deliberately does not use it (see the NOTE there).
- **Never cache Eloquent models.** Cached models unserialize into
  `__PHP_Incomplete_Class` when the classmap shifts and pin object
  graphs in the cache. Cache plain attribute arrays and re-hydrate via
  `(new Model)->setRawAttributes($attrs, true)` — NOT `newInstance()`,
  which drops non-fillable columns (notably the primary key). See
  `TenantHydrator`.
- **Never cache null** for a tenant lookup: a tenant deleted after
  being cached would stay invisible for the whole TTL.
- **Laravel's `flushState()` clears ALL `Queue::createPayloadUsing`
  hooks** between tests — queue tests must re-register via
  `app(TenantQueueContext::class)->register()` in their setup.
- **Successive `$middleware->web(prepend:)` calls stack in REVERSE** —
  the second prepend lands BEFORE the first. Keep the ordering
  requirement in one call (see `bootstrap/app.php`).
- **`throttle:` middleware cannot key on tenant context** — the limiter
  closure may run before `ResolveTenant` sets the context. Identity's
  login rate limiting therefore lives in the controller with the tenant
  id in the key.
- **`Route::middleware('web')->inertia(...)` does not exist** — use
  `Route::middleware('web')->get(..., fn () => Inertia::render(...))`.
- **Module routes via `loadRoutesFrom()` do NOT inherit the root `web`
  group** — declare `Route::middleware('web')` inside the module's
  route file or lose sessions/auth entirely.
- **Inertia shared props are per-request mutable singletons**: the
  middleware writes them via `Inertia::share()` before the Inertia
  middleware merges its own — order matters (prepend order above).
- **Module pages resolve as `<Module>/<Page>`** — a page for component
  name `Identity/Auth/Login` must live at
  `modules/Identity/resources/js/Pages/Identity/Auth/Login.tsx` (note
  the doubled `Identity`), per the resolver in `app.tsx`.
- **The Blade layout must NOT hardcode `resources/js/pages/{component}.tsx`
  as a Vite input** — module pages live in
  `modules/<Module>/resources/js/Pages/…`, so a hardcoded root path
  500s in build (non-hot) mode with "Unable to locate file in Vite
  manifest" for EVERY module page (and in tests via the SSR fallback).
  A `View::creator('app', …)` in `AppServiceProvider` computes the
  correct path per component (`pageVitePath`). Same idea server-side:
  `inertia.pages.paths` only knows the root js/pages dir by default, so
  `PlatformServiceProvider` appends every module's `Pages/` dir for the
  view finder (`ensure_pages_exist` / `assertInertia`).
- **`sessions.user_id` is ambiguous across tenants** (users.id bigint
  repeats per tenant) — NEVER query `sessions` by `user_id` for bulk
  logouts or session audits; you would end sessions of unrelated users
  in other tenants. Enforce deactivation/session policies via model
  attributes in middleware (see `EnsureSessionTenant`), which is
  tenant-aware by design.

### Production requirements (multi-tenant DNS/SSL)

- Wildcard DNS `*.your-domain.com` + wildcard TLS certificate for
  tenant subdomains (custom domains terminate per-tenant).
- `SESSION_DOMAIN` must stay UNSET (host-only cookies): a shared session
  domain would leak tenant sessions across subdomains. `EnsureSessionTenant`
  (web group) is the second layer: a session whose user belongs to
  another tenant is logged out on mismatch.
- Queue worker + scheduler run centrally; jobs carry their tenant in
  the payload (Stage 4 propagation) — no per-tenant workers needed.
- Dev: `*.localhost` resolves locally without /etc/hosts entries; seed
  demo tenants with `php artisan db:seed` (local only).

## Fase 2 notes (planned — see `docs/ai/plan/fase-2/plan.md`)

The authoritative, staged plan lives in `docs/ai/plan/fase-2/plan.md`
(mirrors the Fase 1 plan format: locked decisions, verified repo facts,
11 stages with gates, test map, risks). Summary of the locked scope:

- **School onboarding**: public application form on the central host
  (`tenant_applications`, owned by Platform) → provider approves in the
  platform console → `TenantCreated` (roles seeded) + `TenantApproved`
  (Identity provisions the first Admin Sekolah user via listener; the
  applicant is NOT a user until approval — `users.tenant_id` is NOT
  NULL).
- **Default roles per tenant**: Identity listener on `TenantCreated`
  seeds Admin Sekolah / Guru / Staf-TU through a new PlatformPublic
  `TenantRoles` contract (explicit tenant id — safe from central/CLI).
- **User management by school admin**: create users, assign/remove
  roles, deactivate (`users.deactivated_at` nullable), send reset
  links — gated by `UserPolicy` (first working "permission = action,
  policy = which data" layer).
- **Tenant-aware password reset**: `password_reset_tokens` migrates
  in-place to a composite `(tenant_id, email)` primary key;
  `TenantUrl` contract supplies tenant hosts to queued notifications.
- **Invitations**: admin-created users may have a null password and
  accept via set-password links (same token machinery).
- Queue tenant-awareness is NOT Fase 2 scope — already shipped in
  Fase 1 (Stage 4).

Deferred to Fase 3+: `expires_at`-driven trial expiry jobs, module
flag UI, permission management UI, relation-scoped policies in real
business modules (Absensi).

A Fase 1 deviation from the original plan is recorded in git history:
`password_reset_tokens` was NOT made tenant-aware in Fase 1 (the plan
allowed touching it; it was deliberately left central to keep the auth
surface minimal).
