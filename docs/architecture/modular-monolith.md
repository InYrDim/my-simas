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
| Identity | `Identity` | Tenant-scoped users, auth lifecycle, user management | `Modules\Identity\App\Contracts` (`ResolvesUsers`, `UserRecord`, `AccountProvisioner`, `NewAccount`) |
| Core     | `Core`     | Master data, academic management, CSV import, statistics and reports, WhatsApp notices | `Modules\Core\App\Contracts` (`StudentDirectory`, `ClassDirectory`, `BellSchedule`, `TeacherSchedule`, `ReportRegistry`, `Report`, `StatisticsRegistry`, `StatisticsProvider`, `DashboardRegistry`, `DashboardWidgetProvider`, `NoticeRegistry`, `GuardianNotifier`, `StudentAdmission`, DTOs) |
| Attendance | `Attendance` | Student attendance (Absensi): gate, daily, per lesson, QR | `Modules\Attendance\App\Contracts` (none — nothing uses it)   |
| Ppdb     | `Ppdb`     | Admissions (PPDB): applicants' accounts, registration, selection, announcement, re-registration | `Modules\Ppdb\App\Contracts` (none — nothing uses it) |

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
   contracts (auto-partitioned per tenant). `TenantCache::lock()` gives
   an atomic lock named per tenant for check-then-act sections; it throws
   `LogicException` when the default store cannot lock (`null` driver).
   Jobs need nothing special —
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
- Dev: `localhost:8000/login` for schools (school code = tenant slug,
  printed by `db:seed`; a school's own address is `/<code>/login`), `console.localhost:8000/login` for the provider
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
- UI follows permissions: Platform shares `abilities` (permission name -> bool for the
  signed-in user, from `PermissionRegistry` plus defined Gates; empty without a user).
  Shared's `useCan()` / `<Can permission>` hide actions the server would refuse. Name is
  `abilities` on purpose: pages already return their own `can` prop. Core's `MasterPage`
  takes `writePermission` (default `core.master.manage`; academic pages pass
  `core.academic.manage`) and `FormDialog` / `ConfirmAction` hide themselves without it.
  Rule: every write button or link needs `<Can>`/`useCan`, and a page that is only an editor
  (Profil Sekolah, Penempatan, Pengampu, Wali Kelas) needs the manage permission on both its
  nav child and its GET route. Hiding is cosmetic; the route `can:` stays the real gate.

## Accounts for students and teachers (Fase 8)

- **An account signs in by email or by username.** `users.username` is
  unique per tenant and the email is optional; the login form has one
  field (`login`): a value with `@` is an email, anything else a username.
  Students use their NIS, teachers their NIP.
- **Core creates the accounts, Identity owns them.** Core calls Identity's
  `AccountProvisioner` (create, reset password, follow a new name or
  number, deactivate, reactivate) and stores the returned id in
  `students.user_id` / `teachers.user_id`. A student's first password is
  the birth date (`ddmmyyyy`); a teacher's is random and shown to the
  admin once. Both must be changed at the first login: the
  `RequirePasswordChange` middleware holds a flagged account on
  `/ganti-kata-sandi`.
- **The account follows the record.** A student who graduates, transfers
  or leaves has the account deactivated (reactivated on return); deleting
  a student or teacher deactivates, never deletes.
- **`php artisan roles:sync`** gives schools that already exist the roles
  and permissions added to `modules/Identity/config/roles.php` later
  (role `siswa` came with this phase). Run it after a release that adds a
  role or a permission.
- **Trap: `TenantCache` keys follow the AMBIENT tenant.** Enabling a
  module flag outside the school's context (console, CLI) clears the
  `central:` partition, not the school's, so the school keeps the cached
  answer for up to five minutes. Wrap the call in
  `TenantContext::run($tenantId, ...)` when the change must show at once.
- **Trap: `npx tsc --noEmit` only checks `resources/js`.** Module pages
  are loaded through `import.meta.glob` and are not in `tsconfig.json`'s
  `include`; check them with a config that adds
  `modules/**/resources/js/**/*`.

## WhatsApp per school (Fase 9)

- **Two owners.** Platform owns the gateway side: the provider's OpenWA
  gateway, each school's instance (`whatsapp_instances`), the approval on
  the console page `/whatsapp`, and the contract `WhatsappChannel`
  (`state`, `request`, `connect`, `disconnect`, `sendText`). Core owns
  the school side: the page Integrasi › WhatsApp, the message log
  (`whatsapp_messages`), the notices to guardians and the contracts
  `NoticeRegistry` + `GuardianNotifier` that feature modules use. Core
  never imports the gateway; a feature module never imports Core's log.
- **The flow.** School asks → provider approves (or the provider setting
  `whatsapp.auto_approve` does) → school links its number by QR → school
  sends. On approval SIMAS registers the session `simas-{tenant id}` and
  mints one `operator` key that works for that session only.
- **The session key never reaches a school.** It is encrypted at rest
  with `OPENWA_CREDENTIALS_KEY` (AES-256-GCM; not `APP_KEY`), hidden on
  the model, and absent from every DTO, page prop, console page and
  error message. Every call to the gateway is made by the server; the
  browser only receives the state and the QR image. Tests search the
  whole response for the key.
- **Env.** `OPENWA_API_BASE_URL`, `OPENWA_ADMIN_API_KEY`,
  `OPENWA_CREDENTIALS_KEY` (64 hex). Values live in `.env` only.
- **A module notifies guardians in one call.** It registers a
  `NoticeKind` from its provider and calls
  `GuardianNotifier::notify(new GuardianNotice($studentId, $kind,
  $variables))`. The school's switch (off by default) decides whether a
  message goes out; the wording is the school's own when it has one; the
  guardian's name and number come from the student record; the message
  is logged, queued, and delivered by `DeliverWhatsappMessage`.
- **After a release that adds a permission** (`core.integration.manage`
  came with this phase) run `php artisan roles:sync`, or admins of
  existing schools get 403.
- **A queue worker must run** for messages to leave "Dalam antrean"
  (`QUEUE_CONNECTION=database`). In development `composer run dev` starts
  `queue:listen`. On a server, `queue:work` under Supervisor/systemd plus
  `queue:restart` after each deploy. **On shared hosting (Hostinger)** no
  long-running process is possible, so one cron job runs a short-lived
  worker every minute:
  `* * * * * cd /path/to/simas && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1`
  — it never overlaps the next run, needs no `queue:restart`, and does
  not go through `schedule:run` (which needs `proc_open`, often disabled
  there). The database cache store never removes an expired row until its
  key is read again, so add a second cron job once a day:
  `0 3 * * * cd /path/to/simas && php artisan cache:prune-expired >> /dev/null 2>&1`
  (Platform; does nothing when the store is not `database`). All cron
  jobs are kept in `docs/config/cron.md`.
  Messages then leave up to about a minute late. Design queued
  and scheduled work so it survives that: no sub-minute delivery, no
  daemon, no Horizon/Redis. `QUEUE_CONNECTION=sync` is for trying things
  out only (no retries; many notices at once can hit the execution time
  limit).
- **No webhooks.** The link status is read by polling and a sent message
  is only known to be accepted by WhatsApp, not delivered or read.
- **Trap: `Http::fake([...])` with a URL map calls every stub for every
  request** and takes the first non-empty answer, so a `Http::sequence()`
  under one pattern is used up by requests that match another. Tests
  that need an order use one closure.
- **Trap: with a Vite dev server running (`public/hot`), a full page load
  in a feature test asks it to render (SSR) over HTTP**, which
  `Http::preventStrayRequests()` refuses. Tests that guard stray
  requests set `inertia.ssr.enabled` to false.
- **Trap (browser suite): `auth:provider` leaves `provider` as the default
  guard for the rest of the process.** A test that goes from the console
  back to a school page calls `app('auth')->shouldUse('web')` and
  `refreshSignedInUsers()` first, or the Gate asks the provider's user
  for a school permission (403).

## Attendance (Fase 10)

The first feature module with real data. Details:
`modules/Attendance/CONTRACT.md`.

- **A feature module reads Core through contracts, never models.** Core's
  `StudentDirectory`, `ClassDirectory` and `BellSchedule` hand out DTOs for
  the current school; Attendance keeps plain ids (`student_id`,
  `class_id`, `period_slot_id`, ...) and has no relation to Core's tables.
  It exposes nothing itself.
- **It plugs in through registries.** Permissions (`attendance.*`) with
  Platform's `PermissionRegistry`, sidebar entries with `TenantNavigation`
  (each child carries its permission; a student sees only "QR Absensi"),
  four notice kinds with Core's `NoticeRegistry`, two reports and the
  attendance figures with Core's `ReportRegistry` / `StatisticsRegistry`.
  Core never imports Attendance.
- **Two kinds of record.** `daily_attendances`: one row per student and
  day — the status (hadir, terlambat, sakit, izin, alpa) and the gate
  times. `lesson_sessions` + `lesson_attendances`: one class in one lesson
  slot of one day. There is no timetable: any teacher with school-wide permissions may record any
  class; the classes a teacher teaches or leads are offered first.
- **One-time QR.** The student's page (`attendance.qr.show` + an account
  linked to an active student) asks for a random code kept in
  `TenantCache` for 60 seconds; staff scan it (camera, handheld scanner,
  or a manual pick by name/NIS). No table, nothing about the student in
  the code, one live code per student, used up by the scan. Reading and
  using up a code runs under `TenantCache::lock()` (per code), so two
  scans of one code at the same moment cannot both win; a lock not had
  within 2 seconds refuses the code without using it up. Marking a
  student present in a lesson turns a unique-constraint race into the
  normal "sudah tercatat" refusal.
- **The school's clock, on every database.** Days are stored as the
  school's own `Y-m-d` (never cast to a date object), times of day are
  compared on the tenant timezone, timestamps are converted to the
  application timezone before saving, and months are grouped in PHP.
- **Notices never check the switch.** Attendance says what happened
  (`GuardianNotifier`); whether a message goes out is the school's choice
  on Integrasi › WhatsApp. Only today's events are announced, and an
  absence only when the status just changed to it.
- **No scheduler.** Nothing marks absence automatically at the end of a
  day; a student without a record is "belum diabsen". Shared hosting runs
  only the queue cron (see WhatsApp per school).
- **After the release** run `php artisan migrate` and
  `php artisan roles:sync` (new permissions for all four default roles).
- **Every school has it, for now.** A plan switches off the modules it
  does not list, so `attendance` is in every plan
  (`BillingMasterDataSeeder`), and a one-off Platform migration added it
  to the plans and schools that existed before. Which plan keeps it is
  decided later, by editing the plans in the provider console.

## PPDB (Fase 11)

The last module that was a mockup, and the first feature module with a
**central** (tenant-less) table. Details: `modules/Ppdb/CONTRACT.md`.

- **Two kinds of public registration now, no more.** Platform's school
  applicants (`/daftar-sekolah`, guard `applicant`) and PPDB applicants
  (`/calon-siswa/daftar`, guard `ppdb`). School users (Identity) still have
  no self-registration. The three kinds of account — school users, school
  applicants, PPDB applicants (and the provider's staff) — never share a
  guard or a session key; signing out of one never ends another.
- **The account lives in Ppdb, not Platform.** `ppdb_accounts` has no
  `tenant_id` scope: an applicant exists before they join a school.
  Platform is the lowest layer and does not know the business of
  admissions; its own `applicants` table is for schools, and Ppdb never
  imports it (internal). The account stores only who the applicant is and
  the one school it joined (`tenant_id`, the sole foreign key); every
  detail of the application lives in the school's tables.
- **Joining a school is by code, not by search.** The code is the tenant id
  the school's own people already type at sign-in. A refused join always
  gives the same message, so nobody can list the schools that use SIMAS or
  PPDB. One account joins one school; after the form is sent it is locked
  there and only the school's committee can cancel the registration.
- **Central pages enter a school explicitly.** `ResolveTenant` adopts the
  school a session remembers even for a guest, so the account pages never
  rely on it: they run inside `TenantContext::run($account->tenant_id, …)`
  and read only the registration with the account's own id. The school and
  the account id are never taken from a request.
- **Core makes the student.** `StudentAdmission::admit(NewStudent)` (Core's
  contract) creates an active student without a class and without an
  account; Ppdb gates who may ask and keeps the returned id. Core never
  imports Ppdb; Ppdb never imports Core's models.
- **The registration form is built by the school, per period**, like a
  form builder: the ten built-in fields (path, name and gender locked) and
  custom ones — text, paragraph, number, date, select, checkboxes, file,
  section heading — added, edited, ordered, archived or deleted (only without
  answers) on PPDB › Formulir, with a live preview. Fields are rows
  (`ppdb_form_fields`), answers rows (`ppdb_applicant_answers`); validation is
  built from the period's rows; archiving keeps old answers; a new period
  copies the latest. Uploaded files live in the school's `TenantStorage`
  partition and are served only as attachments through the owner's or the
  committee's own route. Custom answers are columns of the applicant report.
- **Results reach the guardian on WhatsApp.** Core's second notifier
  contract, `ContactNotifier` (`ContactNotice`: kind, recipient name and
  number, who it is about), exists because an applicant has no student
  record for `GuardianNotifier` to look up. Ppdb registers `ppdb.result` and
  sends it when the school announces the results; the school's switch and
  wording are Core's. The applicant also sees their result on their own page
  once it is announced.
- **Stand-in until later.** Document completeness is an interface with an
  always-true implementation (`DocumentCheck`), the same pattern as
  Platform's payment gateway. A public result page is not built.
- **In no plan yet.** `ppdb` is in no billing plan; a provider adds it to
  a plan's modules or switches it on for a school.
- **After the release** run `php artisan migrate` and
  `php artisan roles:sync` (new permissions for admin-sekolah and staf-tu).

## Kelas Saya for teachers (Fase 13)

The teacher's workspace merges what used to be two sidebar entries —
Core's Kelas Saya and Attendance's Absensi Saya — into one: **Kelas Saya**
with Kelas Aktif, Jadwal Hari Ini, Absensi Kelas and Riwayat Absensi.
"Absensi Saya" stays on the sidebar as a "Segera hadir" placeholder.

- **The new public surface is Core's `TeacherSchedule`** (`week`, `onDay`,
  `ScheduleDay` / `ScheduleLesson`): a feature module reads the lesson
  timetable without Core's models. Core's own Jadwal Mengajar reads it
  too; the old internal query is gone.
- **Attendance owns the pages.** Three of the four pages are attendance
  data, and Core may not depend on Attendance, so the merged menu is
  registered by Attendance (`attendance.class.record`; Absensi Kelas and
  Riwayat Absensi additionally need the school's lesson switch through the
  new composite `attendance.class.lesson.use`). Core's `/saya/kelas` page,
  its route and its sidebar entry are removed; `/saya/jadwal` (Jadwal
  Mengajar, all lessons) stays Core's.
- **The school's clock decides.** A lesson is `upcoming`, `running` or
  `finished` on the tenant's timezone; only a running lesson may be filled
  on Absensi Kelas (a future day is refused; the place to correct a record
  is Riwayat Absensi), and the todo checkbox can only be ticked after the
  lesson's hour. `lesson_checks` stores the teacher's own done mark and is
  also written when the teacher saves the attendance.
- **The school-wide pages stay the office's**: `/absensi/input` needs
  `attendance.daily.record`, `/absensi/jam-pelajaran` needs
  `attendance.lesson.school`; a teacher keeps only `Pindai QR`.
- **After the release** run `php artisan migrate` (the `lesson_checks`
  table).

## Attendance scan times (Fase 14)

Scanning is accepted only at its own time, on the tenant's clock. Attendance
internal (`Domain/Support/ScanWindow`), no contract. Details:
`modules/Attendance/CONTRACT.md`, plan `docs/ai/plan/fase-14/`.

- **A lesson is scanned from a school-set tolerance before it starts**
  (`lesson_scan_early_minutes`, default 5) **until it ends**; outside that
  the scan is refused and the correction goes through Riwayat Absensi.
  `MarkLessonPresence` asks it, so every caller is bound.
- **A teacher without `attendance.lesson.school` scans only the own lesson
  that is running** (the scanner offers just that one; the controller
  re-asserts it against `TeacherLessons`). The school-wide permission keeps
  the free choice of class but not the hours.
- **The gate takes scans between `gate_opens_at` and `gate_closes_at`**
  (default 05:00–18:00, closing minute included), in and out alike. Leaving
  before the last lesson of the day ends is stored as
  `daily_attendances.left_early` and shown as "Pulang awal". The daily input
  (`/absensi/input`) is not bound by these hours: it is the correction path.
- **A student recorded as gone home is not scanned into a lesson.** The
  office (`attendance.daily.record`) can take the going-home record back on
  Absensi Gerbang, today only (`CancelGateCheckOut`); the arrival and the status
  stay and nobody is notified.
- **Not built (on purpose):** school holidays and days without lessons are
  not refused (needs a Core calendar contract), no automatic absence, no
  reason for leaving early.
- **After the release** run `php artisan migrate`.

## School setup checklist (Fase 12)

The school's Beranda shows "Persiapan sekolah" to whoever holds
`core.master.manage`: the master-data steps in the order the data depends
on itself (see `docs/architecture/master-data-dependencies.md`), one of
them marked as next. It is Core-internal (`Domain/Queries/SetupChecklist`,
no contract, no table): every state is computed from the school's own
records on each visit, and the card disappears once all required steps are
done.

- **Profile = grades exist.** Grades are seeded only when the profile is
  saved, so that is the real blocker for classes. Reading the checklist
  never calls `SchoolProfile::current()` (it creates a row).
- **Majors are required only where the level has them**
  (`SchoolLevel::hasMajors()`), and classes wait for them, which keeps a
  school from adding majors after its first classes.
- **Students count once placed** in a class of the active year; the
  step links to Penempatan Siswa `?kelas=belum` while some are not.
- Details: `modules/Core/CONTRACT.md` (Surfaces).


## Kelas Saya for students

Students get their own **Kelas Saya** (group "Saya"), owned by Attendance like the
teacher's: Info Kelas (class, homeroom, classmates), Jadwal Pelajaran, Mata Pelajaran
& Guru and Absensi Saya as a child. One sidebar entry cannot be shared between
modules and Core must not import Attendance, so Attendance owns the entry and reads
Core through `ClassDirectory`, `StudentDirectory` and the new `ClassTimetable`
contract. Permission `attendance.class.view-own` (role `siswa`); after a release run
`php artisan roles:sync`. Details: `modules/Attendance/CONTRACT.md`, `modules/Core/CONTRACT.md`.

## Menu guru (Fase 15)

The teacher's sidebar is cut to what a teacher does: Beranda, Profil Saya,
**Jadwal Saya**, **Kelas Mengajar**, Ganti Kata Sandi. Plan:
`docs/ai/plan/fase-15/menu-guru-plan.md`.

- **One schedule.** Attendance's Jadwal Saya (`/absensi/jadwal-saya`) has the
  tabs Hari Ini (todo list, range banner) and Minggu Ini
  (`TeacherSchedule::week()`). Core's Jadwal Mengajar menu entry is gone; its
  `/saya/jadwal` page stays reachable.
- **One door for lesson attendance.** Kelas Mengajar = Kelas Aktif, Absensi
  Kelas (tabs Isi Absensi / Koreksi; Koreksi is the old Riwayat Absensi,
  URLs unchanged) and Pindai QR. The empty teacher "Absensi Saya" and its
  `/absensi/segera-hadir` route are removed. Students still have Kelas Saya.
- **No school-wide admin pages for a teacher.** Role `guru` lost
  `core.master.view` and `core.academic.view`, so Data Induk and Statistik
  & Laporan are not on a teacher's menu (Kelas Aktif already lists the
  students of the teacher's classes).
- **After a release:** `php artisan roles:sync` (it replaces a role's
  permissions, so existing schools lose the two from `guru`).
