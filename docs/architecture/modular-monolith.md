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
- **Identity** (`Modules\Identity`) — tenant-scoped users and their
  full auth lifecycle (Fase 2): login/logout, tenant-scoped password
  reset + set-password acceptance, default role seeding
  (`TenantCreated`), first-admin provisioning (`TenantApproved`),
  school-admin user management (create/invite/roles/
  deactivate/reactivate/reset-link) behind `UserPolicy`. `User` model
  (uses Platform's `BelongsToTenant` + `HasTenantRoles`), factory,
  `users`/`password_reset_tokens`/`sessions` migrations,
  `ResolvesUsers` contract, `UserRecord` DTO, login rate limiting.
  (See “Folder shape” below: module code lives under the module's own
  `app/` tree, e.g. `Modules\Identity\App\Domain\Models\User`.)
- **Core** (`Modules\Core`) — master data (school profile, academic years,
  classes, subjects, rooms, people, extracurriculars) and academic
  management (homerooms, teaching assignments, student placement, bell
  schedule, academic calendar), plus the CSV import of students and
  teachers, and Statistik & Laporan: Core owns the pages and two
  registries (`ReportRegistry`, `StatisticsRegistry` in Core's Contracts)
  that every module — Core included — registers its reports and figures
  with; Core never imports a feature module. Permissions `core.master.*`
  and `core.academic.*`. Surface: see `modules/Core/CONTRACT.md`.
- **App / Database** — Laravel glue only: providers, config, root
  seeders. No business logic. (`DatabaseSeeder` creating the example
  user via `UserFactory` is the single documented exception.)

## Modules

| Module   | Layer      | Owns                                  | Public surface                                                   |
| -------- | ---------- | ------------------------------------- | ---------------------------------------------------------------- |
| Shared   | `Shared`   | Generic technical utilities           | Everything (by definition)                                       |
| Platform | `Platform` | Tenancy, module registry, permissions | `Modules\Platform\App\Contracts`                                 |
| Identity | `Identity` | Tenant-scoped users, auth lifecycle, user management | `Modules\Identity\App\Contracts` (`ResolvesUsers`, `UserRecord`) |
| Core     | `Core`     | Master data, academic management, CSV import, statistics and reports | `Modules\Core\App\Contracts` (`ReportRegistry`, `Report`, `StatisticsRegistry`, `StatisticsProvider`, DTOs) |
| Attendance | `Attendance` | Daily attendance (Absensi) — mockup | `Modules\Attendance\App\Contracts` (none yet)                  |
| Ppdb     | `Ppdb`     | Admissions (PPDB) — mockup            | `Modules\Ppdb\App\Contracts` (none yet)                         |

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
timezone}` or `null` when no school is selected — and `modules` — the sorted list
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
- **`ResolveTenant` must run AFTER `StartSession`.** The tenant comes
  from the session (school code at login), so it is appended to the web
  group and ordered with `appendToPriorityList(after: StartSession)`;
  prepending it makes every logged-in request tenant-less.
- **Never pass the `school` field to `Auth::validate()`** — the provider
  turns every credential key into a WHERE column. Pass only
  `email`/`password`.
- **`/login` exists twice** (console host = provider, anywhere else =
  school): the console `Route::domain()` routes in Platform must
  register BEFORE Identity's. Platform's provider is listed first in
  `bootstrap/providers.php`; keep that order.
- **A logged-in session whose school is gone is ended, not resolved** —
  never call the web guard's `logout()` without a tenant context (it
  reads the user through the tenant-scoped provider); drop the session
  and `forgetUser()` instead (see `ResolveTenant`).
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

- DNS for the central domain and the provider console host
  (`TENANCY_CONSOLE_DOMAIN`, e.g. `console.your-domain.com`); no
  wildcard DNS/certificate is needed — schools share the central host
  and log in with their school code.
- Keep `SESSION_DOMAIN` UNSET (host-only cookies, so the console and
  school sessions never mix). `EnsureSessionTenant` (web group) is the
  second layer: a session whose user belongs to another tenant is
  logged out on mismatch.
- Queue worker + scheduler run centrally; jobs carry their tenant in
  the payload (Stage 4 propagation) — no per-tenant workers needed.
- Dev: `localhost:8000/login` for schools (school code = tenant id,
  printed by `db:seed`), `console.localhost:8000/login` for the provider
  (`*.localhost` resolves without /etc/hosts entries); seed demo
  tenants with `php artisan db:seed` (local only).

## Fase 2 (built — auth & onboarding)

Shipped across Stages 1–10 (`docs/ai/plan/fase-2/plan.md` is the
staged record; this section is the canonical summary).

### School onboarding flow

> Fase 4 (in progress, `docs/ai/plan/fase-4/tenant-onboarding-plan.md`)
> puts an applicant account in front of this flow: `/daftar-sekolah` now
> registers an **applicant** (central, guard `applicant`, Platform-owned)
> and the school form lives at `/pemohon`. Public registration exists
> only for applicants; school users are still never self-registered.

Application form on the central host (`/pemohon`, behind a verified
applicant account; registration at `/daftar-sekolah` with throttle IP +
honeypot, owned by Platform) → provider reviews in the
platform console (`console.localhost/applications`, guard `provider`,
console host only) → approve corrects school data from the form (the
corrected payload is the FINAL tenant data) → inside one transaction:
tenant row created (`TenantCreated` fires → default roles seeded),
onboarding modules enabled from `config('tenancy.onboarding_modules')`
(default `['identity']` — flagged modules only; core is always-active
without a flag row; Platform reads the config, never hardcodes
modules), decision columns filled, `TenantApproved` fired → Identity's
`ProvisionFirstAdmin` listener creates the first Admin Sekolah user
(password null) with a tenant-scoped set-password token and queues
`SetPasswordMail`. A failure rolls the whole approval back — no tenant
exists without its admin. The applicant is never a user before
approval (`users.tenant_id` is NOT NULL); their identity lives in
`tenant_applications`.

### Default roles per tenant

Identity's `SeedDefaultRoles` listener on `TenantCreated` calls the
PlatformPublic `TenantRoles` contract (`ensure($tenantId, $name,
$permissions)` — idempotent, runs its own `TenantContext::run` +
permission sync, fails closed on an unknown tenant id; `names()` for
UI dropdowns). The role set lives in `modules/Identity/config/roles.php`:
keys are Spatie role MACHINE names (`admin-sekolah`, `guru`,
`staf-tu`) — stable identifiers in code/DB; labels ("Admin Sekolah",
…) live in config only, renamable without touching tenant DB rows.
Guru/Staf-TU are born with empty permission sets — business modules
extend them later from their own config/listeners.

### Deactivation convention

`users.deactivated_at` (nullable timestamp) — non-null means
deactivated. Rows and roles are KEPT (audit + reactivation); deletion
is not a Fase 2 concern. Login refuses deactivated accounts with the
same generic error as wrong credentials (anti-enumeration), and
Platform's `EnsureSessionTenant` middleware ends live sessions by
reading the attribute — no trait, no interface, baseline untouched.
Two anti-lockout invariants live in `DeactivateUser`: no
self-deactivation, and the tenant's LAST ACTIVE admin-sekolah cannot
be deactivated (checked under the target's own tenant context; the UI
mirrors both as disabled buttons + `isSelf` / `isLastActiveAdmin`
props — the action refuses regardless).

### Tenant-scoped password tokens + `TenantUrl`

`password_reset_tokens` was migrated in-place (pre-production) to
`tenant_id` + composite PK `(tenant_id, email)`. The internal
`TenantDatabaseTokenRepository` subclasses Laravel's and stamps/scopes
every row by the AMBIENT tenant context — fail closed
(`TenantNotSetException`) without one. A token minted in tenant A is
never valid on tenant B, even for the same email. All token TTLs are
60 minutes. Because these mails are built inside the queue (no
trustworthy request root), PlatformPublic's `TenantUrl` contract
supplies scheme/host/port per tenant for any link in a queued mail.

Three flows share this ONE token machinery — the token table is
generic; the consuming page decides the effect:

1. **Reset** (`/reset-password`, forgot-password form): generic
   response ALWAYS — unknown email, deactivated user, or a user
   without a password all get the identical "jika email terdaftar…"
   answer and NO email (anti-enumeration).
2. **First-admin provisioning** (`TenantApproved` listener, Stage 8).
3. **Invitations** (Stage 10, admin UI): `InviteUser` create-or-
   reinvites a password-null user and queues `SetPasswordMail` with a
   tenant-host link. Re-inviting an invited user UPDATES it (fresh
   name/role) and REPLACES the token row (tenant-scoped
   deleteExisting) — this is the deliberate replacement for a resend
   button. There is deliberately NO separate invite permission:
   invitations are gated by `identity.users.create`. An ACTIVE user
   (has a password) refuses re-invitation — `identity.users.sendReset`
   is the only password-mail path for them.

A known, accepted cross-consumption: a set-password token consumed on
the reset page sets the password WITHOUT email verification, and vice
versa. The table does not know the issuing context; recorded in the
plan as an accepted trade-off.

### User management + policy convention

School-admin UI at `/users` (Identity, routes behind `auth` +
`module:identity`): list, direct-create (email marked verified —
trusted admin input), edit (name + role full-sync from
`TenantRoles::names()`-backed dropdown), deactivate/reactivate, send
reset link, invite. `UserPolicy` is the first working example of the
"permission = which action, policy = which data" convention:
`viewAny/create/update/deactivate/reactivate/sendReset` gate on the
`identity.users.*` permissions (via tenant roles), re-assert the
target's tenancy (`sameTenant`), and a `before` hook denies
DEACTIVATED actors entirely. Every mutation goes through Domain
actions (`CreateUser`, `InviteUser`, `SendResetLink`,
`DeactivateUser`, `ReactivateUser`) so the rules are testable without
HTTP. Post-login landing stays `route('home')` (welcome) — a dedicated
school dashboard waits for the business modules.

### Transactional email convention (no Notification machinery)

Mailables sent directly via `Mail::to(...)->queue(...)` — no
`Notification` classes, no database/broadcast channels (locked
decision). Module mail views resolve through the module's view
namespace (`Identity::reset-password-text`, registered via
`loadViewsFrom`) — a dot-path into `modules/…` does NOT work (the view
finder maps dots to separators and searches the path doubled).

### Anti-enumeration convention

Every unauthenticated auth surface answers with the SAME generic
message regardless of account state: login (deactivated = wrong
password), forgot-password (unknown email = sent), set-password
(deactivated = invalid link). Status is never disclosed to
unauthenticated probes.

Deferred to Fase 3+: `expires_at`-driven trial expiry jobs, module
flag UI, permission management UI, relation-scoped policies in real
business modules (Absensi), a dedicated school dashboard.

A Fase 1 deviation from the original plan is recorded in git history:
`password_reset_tokens` was NOT made tenant-aware in Fase 1 (the plan
allowed touching it; it was deliberately left central to keep the auth
surface minimal) — fixed in Fase 2 Stage 4.

## Provider-console billing and school admins (Fase 3)

- **Billing is Platform-internal** (`plans`, `subscriptions`, `invoices`;
  services under `Infrastructure/Billing`). No Billing module. Cross-table
  links are plain indexed columns — the only FK stays `tenant_id → tenants`.
  The payment step is a stub (`AlwaysSucceedsPaymentGateway`, always `true`).
  Initial plans come from `BillingMasterDataSeeder`; values and assumptions
  are recorded in `modules/Platform/CONTRACT.md`.
- **School admins across tenants are an Identity page.** Platform cannot import
  Identity, so `Console/SchoolAdminController` lives in Identity on the console
  host (`auth:provider`), reads tenants through Platform's read-only
  `Contracts/TenantDirectory`, and runs every action inside the target tenant's
  context. Its console route registers before the tenant `/users` routes.
- Platform additions to its public surface: `TenantDirectory`,
  `TenantRoles::rolePermissions()`.
- School-side shell: Platform's `Contracts/TenantNavigation` (modules register
  their own sidebar entries; shared prop `tenantNav` is filtered by active
  module + Gate) feeds Shared's generic `TenantShell`, used by Core's Beranda
  and Identity's `/users` pages. Icon names are kebab-case lucide names, loaded lazily by the shell.
