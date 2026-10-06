<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

# Modular Monolith — Standing Rules (Addendum)

Module template is the authoritative folder shape; the modular-monolith
skill is still read, but where the skill's structure differs, the
template wins. Current template deviations are reported in
docs/architecture/modular-monolith.md.

## Layer order (bottom -> top)

Shared -> Platform -> Identity -> Core -> feature modules (Attendance,
Ppdb). Dependencies only point upward; feature modules never depend on
each other, not even via Public surfaces.

## Module template (authoritative shape)

Modules/<Name>/ with CONTRACT.md; app/{Contracts,Domain,Infrastructure,Http};
resources/js/{Pages,Components}; routes/{web,api}.php;
database/{migrations,factories,seeders}; tests/{Feature,Unit};
single provider at app/Infrastructure/Providers/<Name>ServiceProvider.php
(registered in bootstrap/providers.php — no root Providers/ folder).
Do not add nwidart/laravel-modules just to scaffold.
Namespaces follow folders: Modules\<Name>\App\...; Modules\<Name>\Database\,
Modules\<Name>\Tests\ map to database/, tests/ (per composer.json).

## Public surface = app/Contracts/** only

- Everything another module may use lives under app/Contracts/ (incl.
  Contracts/Events, Contracts/DTOs, Contracts/Exceptions, Contracts/Concerns).
- Public events (listened to by other modules) live in Contracts/Events;
  internal events stay in Domain/Events.
- Contracts never expose Eloquent models from Domain/ — use DTOs
  (Contracts/DTOs), scalars, or DTO collections.
- Contract implementations live in Infrastructure/ or Domain/ and are
  bound in the module's service provider.
- No Eloquent relations across modules; no cross-module FKs — sole
  exception: tenant_id -> tenants (owned by Platform).

## Shared

All of Shared is importable by every module (Deptrac: whole Shared layer
is an allowed target), but its contents stay pure technical utilities —
no school business concepts. Shared depends on no module. Business-
meaningful code "accidentally used by two modules" is reported as a
candidate contract / Core module — never parked in Shared.

## Deptrac (4.x, deptrac.php — not yaml)

- <Name>Public -> Modules/<Name>/app/Contracts/**; <Name> -> the rest
  (Domain, Infrastructure, Http, Providers, routes, database, tests,
  resources).
- <Name>Public may access Shared and its OWN internal layer (public
  traits may delegate to internals, e.g. BelongsToTenant -> TenantScope);
  never another module's internals. Keep contracts pure interface/DTO
  wherever possible.
- <Name> -> Shared, Laravel/vendor, own <Name>Public, and <Other>Public
  only for lower layers per the order above. Feature modules never
  depend on each other (incl. via Public).
- Root tests/ is its own glue layer: may use anything, nothing uses it.

## Pest arch tests (complement Deptrac)

Every module has a filled CONTRACT.md; no cross-module FKs in
migrations (except tenant_id); Shared never imports modules; no
Spatie\ imports outside Platform.

## Platform surface (Fase 1 replaces the old Phase-1 list)

All in Modules/Platform/app/Contracts/**: TenantContext, BelongsToTenant
(public trait), TenantNotSetException, TenantCache, TenantStorage,
ModuleRegistry, PermissionRegistry, HasTenantRoles (Spatie wrapper for
Identity), TenantCreated event, helpers. Private: TenantScope, resolver,
queue listeners, Spatie integration, Tenant model + persistence.
ProviderUser stays in Platform (not Identity).

## Identity (Fase 1 + Fase 2 done)

Other modules never import the User model: store user_id as a plain
column (no FK, no Eloquent relation) and use Identity's Contracts for
user data; for the logged-in user just use Auth/Gate. users is
tenant-scoped: tenant_id + unique(tenant_id, email); User uses
BelongsToTenant + HasTenantRoles from PlatformPublic and never imports
Spatie directly. Auth surface (Fase 2): login/logout, forgot/reset
password, set-password activation, school-admin user management
(/users + invite) behind UserPolicy — NO public registration.
password_reset_tokens is tenant-scoped (tenant_id + composite PK,
Stage 4): token minting via the ambient-context repository (fail
closed), TTL 60 min, ONE shared table for reset + provisioning +
invitations (the page decides the effect; cross-consumption accepted
and recorded). Rate limiting lives in controllers keyed
{flow}:{tenant_id}:{email}:{ip} — throttle: middleware cannot rely on
tenant context. Mails are queued Mailables with URLs from TenantUrl
(queue-safe); views via the Identity:: namespace (dot-path into
modules/ does NOT work); no Notification machinery (locked decision).
Anti-enumeration: generic responses on login/forgot/set-password
regardless of account state. Roles: machine names from
modules/Identity/config/roles.php (labels config-only); permissions
identity.users.* registered via PermissionRegistry; UserPolicy =
permission gate + same-tenant re-assert + deactivated-actor deny;
invitations reuse identity.users.create (no separate invite
permission). Deactivation: deactivated_at nullable, rows+roles kept,
anti-lockout invariants in DeactivateUser (no self, last ACTIVE admin
protected). Events consumed: TenantCreated → SeedDefaultRoles,
TenantApproved → ProvisionFirstAdmin (both idempotent, listeners in
IdentityServiceProvider).

## Tenancy traps (all bit during Fase 1 — full list in docs/architecture)

- WithoutModelEvents in seeders kills the tenant_id creating hook.
- Never cache Eloquent models (attribute arrays + setRawAttributes);
  never cache null tenant lookups.
- flushState() clears Queue::createPayloadUsing hooks between tests —
  re-register via app(TenantQueueContext::class)->register().
- Successive web(prepend:) calls stack in REVERSE — one call only.
- Module routes via loadRoutesFrom() need Route::middleware('web')
  declared inside the module's route file.
- Module pages resolve as <Module>/<Page>: the file path doubles the
  module name (Pages/Identity/Auth/Login.tsx).
- Deptrac flattens traits: consumers of Platform's public traits are
  skipped per-class in deptrac.baseline.yaml (grow ONLY for that).
- DB::table() bypasses the tenant scope — Eloquent only for tenant data.
- ResolveTenant runs AFTER StartSession (tenant lives in the session;
  appendToPriorityList(after: StartSession)). Never pass `school` to
  Auth::validate(). Console-host /login (provider) must register before
  Identity's /login. End a logged-in session with no school via
  session invalidate + forgetUser(), never guard->logout().

## Events across modules

For module code, events other modules listen to live in the publisher's
Contracts/Events (that's what makes them legally importable).
app/Events is only for legacy code not yet extracted. Listeners live in
the listening module and register in that module's provider.

## New module authorization

Platform (Fase 1) and Identity are explicitly authorized. Creating any
other module (Attendance, Ppdb, ...) requires the user's go-ahead in its
phase. Extract one module per stage and stop between stages.

## Docs

The tracked, canonical version of this policy lives in
docs/architecture/modular-monolith.md — update it whenever the surface
or rules change (this AGENTS.md copy is intentionally gitignored; keep
them in sync).

## Billing and provider-console users (Fase 3)

Subscription billing (plans/subscriptions/invoices, trial vs subscribed) is
Platform-internal; payment is the always-true stub `PaymentGateway`. School
admins across tenants are managed by Identity's `Console/SchoolAdminController`
on the console host. New Platform contracts: `TenantDirectory`,
`TenantRoles::rolePermissions()`. Details: modules/Platform/CONTRACT.md and
docs/architecture/modular-monolith.md.

## Statistik & Laporan (Fase 7)

Core owns the pages (`/statistik-laporan/*`, behind `core.master.view`) and
Core's first contracts: `ReportRegistry` + `Report` and `StatisticsRegistry`
+ `StatisticsProvider` (DTOs in `Contracts/DTOs`). Every module, Core
included, registers its reports and figures from its own provider; a
feature module never gets imported by Core. Reports return a `ReportTable`
— Core renders CSV and the print view, nothing is stored. "Segera hadir"
placeholders are labels in `modules/Core/config/insight.php`; registering
the same key replaces one. Details: modules/Core/CONTRACT.md.

## Accounts for students and teachers (Fase 8)

`users` has an optional email and a `username` (unique per tenant); login
takes one field `login` (`@` = email, else username: NIS, NIP). Core makes
the accounts through Identity's `AccountProvisioner` and keeps only
`user_id`: students get NIS + birth date (`ddmmyyyy`), teachers NIP + a
random password shown once; both must change it at first login
(`RequirePasswordChange` middleware, `/ganti-kata-sandi`). A student who
leaves has the account deactivated; nothing is deleted. Role `siswa` has
no permissions yet. `php artisan roles:sync` brings existing schools in
line with `modules/Identity/config/roles.php`. Details:
modules/Identity/CONTRACT.md, modules/Core/CONTRACT.md,
docs/architecture/modular-monolith.md.

## WhatsApp per school (Fase 9)

Schools send WhatsApp through the provider's OpenWA gateway. Platform owns
the gateway side (`whatsapp_instances`, console page `/whatsapp`, contract
`WhatsappChannel`: `state`, `request`, `connect`, `disconnect`,
`sendText`); Core owns the school page Integrasi › WhatsApp (behind
`core.integration.manage`), the log `whatsapp_messages`, and the contracts
`NoticeRegistry` + `GuardianNotifier` a feature module uses to notify
guardians (kinds are off until the school switches them on). Flow: school
asks → provider approves (or provider setting `whatsapp.auto_approve`) →
school links by QR → messages go out through the queue. The per-school
session key is encrypted with `OPENWA_CREDENTIALS_KEY` and never reaches
a DTO, a page, a log or an error message; every gateway call is made by
the server. Env: `OPENWA_API_BASE_URL`, `OPENWA_ADMIN_API_KEY`,
`OPENWA_CREDENTIALS_KEY` (values in `.env` only). Tests fake the gateway
(`Http::fake` + `Http::preventStrayRequests`) and never call the real
one. Details: modules/Platform/CONTRACT.md, modules/Core/CONTRACT.md,
docs/architecture/modular-monolith.md.

## Attendance (Fase 10)

The first feature module with real data: gate in/out, daily status
(hadir, terlambat, sakit, izin, alpa) and attendance per lesson, by a
student's one-time QR or by hand. It reads Core only through
`StudentDirectory`, `ClassDirectory` and `BellSchedule` (DTOs; plain ids in
its own tables) and exposes nothing. It registers permissions
`attendance.*`, sidebar entries, four notice kinds (`attendance.gate-in`,
`.gate-out`, `.absent`, `.lesson-absent`), two reports and the attendance
figures through the registries; it never checks the WhatsApp switch. The
QR is a 60-second code in `TenantCache` (no table); the page needs
`attendance.qr.show` and an account linked to an active student. Days are
the school's own `Y-m-d` strings, timestamps go through
`SchoolClock::stored()`, months are grouped in PHP. No timetable (any
teacher may record any class; own classes are offered first) and no
scheduler (nothing marks absence automatically). After a release:
`php artisan migrate` and `php artisan roles:sync`. Details:
modules/Attendance/CONTRACT.md, modules/Core/CONTRACT.md,
docs/architecture/modular-monolith.md.

## PPDB (Fase 11)

Applicants have their own account, separate from every school user: a
central table `ppdb_accounts` (no tenant scope; guard `ppdb`; pages under
`/calon-siswa/*`) owned by Ppdb, with only name, email, password and the
one school joined (`tenant_id`, the sole FK). It is the second public
registration after Platform's school applicants; school users still have
none. An applicant joins a school with the school code (the one typed at
sign-in; link `/calon-siswa/gabung?school=<code>`), every refusal gives
the same message, and after the form is sent the account is locked there
(only the committee's `CancelApplication` frees it). Account pages never
rely on `ResolveTenant`: they run in `TenantContext::run($account->tenant_id)`
and read only the registration with the account's id. Tables
`ppdb_periods`, `ppdb_waves`, `ppdb_paths`, `ppdb_applicants` are tenant-scoped.
Core's `StudentAdmission::admit()` makes the student at re-registration
(no class, no account); permissions `ppdb.view`, `ppdb.applicants.manage`,
`ppdb.selection.manage`, `ppdb.settings.manage`. The decision stays hidden
on the applicant's page until the results are announced; document check
is an always-true stand-in (`DocumentCheck`); results go to the guardian
on WhatsApp through Core's `ContactNotifier` (kind `ppdb.result`, off until
the school switches it on); each period's registration form is built by the school
on PPDB › Formulir (`ppdb_form_fields`: the ten built-in fields, path/name/
gender locked, plus custom text, paragraph, number, date, select,
checkboxes, file and section fields; answers in `ppdb_applicant_answers`;
archiving keeps answers, delete only without answers; a new period copies
the latest; uploads live in `TenantStorage` and download as attachments);
`ppdb` is in no plan yet. After a release: `php artisan migrate` and
`php artisan roles:sync`. Details: modules/Ppdb/CONTRACT.md,
modules/Core/CONTRACT.md, docs/architecture/modular-monolith.md.

## Checklist persiapan sekolah (Fase 12)

Beranda admin sekolah (`core.master.manage`) menampilkan "Persiapan
sekolah": langkah Master Data menurut urutan dependensinya, satu ditandai
berikutnya. Internal Core (`Domain/Queries/SetupChecklist`), tanpa
kontrak dan tanpa tabel: status dihitung dari data sekolah tiap kunjungan
dan kartu hilang setelah semua langkah wajib selesai. Profil dianggap
selesai bila Tingkat sudah ada (di-seed saat profil disimpan); jurusan
wajib hanya untuk jenjang yang memakainya. Rincian:
modules/Core/CONTRACT.md, docs/architecture/modular-monolith.md.

## Kelas Saya guru (Fase 13)

Menu guru "Kelas Saya" (grup "Saya") kini satu pintu: Kelas Aktif,
Jadwal Hari Ini, Absensi Kelas dan Riwayat Absensi; "Absensi Saya" tetap
ada tapi kosong ("Segera hadir"). Kontrak publik baru Core
`TeacherSchedule` (`week`/`onDay`, DTO `ScheduleDay`/`ScheduleLesson`)
memberi Attendance jadwal pelajaran; halaman `/saya/kelas` lama dan entri
menunya dihapus (Jadwal Mengajar tetap). Jam pelajaran menentukan
segalanya: hanya jam yang sedang berlangsung yang bisa diisi di Absensi
Kelas, kartu Jadwal Hari Ini hanya bisa dicentang setelah jamnya selesai
(`lesson_checks`, ikut terisi saat absensi disimpan), dan koreksi di luar
jam lewat Riwayat Absensi. Halaman sekolah (Input harian, Jam Pelajaran
sekolah) kini milik admin/staf-tu; guru tetap bisa Pindai QR. Setelah
rilis: `php artisan migrate`. Rincian: modules/Attendance/CONTRACT.md,
modules/Core/CONTRACT.md, docs/architecture/modular-monolith.md.

## Kelas Saya siswa

Siswa kini punya menu "Kelas Saya" (grup "Saya", dimiliki Attendance): Info
Kelas, Jadwal Pelajaran, Mata Pelajaran & Guru, dan Absensi Saya sebagai anak
menu. Izin baru `attendance.class.view-own` (role `siswa`); kontrak Core baru
`ClassTimetable`. Setelah rilis: `php artisan roles:sync`. Rincian:
modules/Attendance/CONTRACT.md, modules/Core/CONTRACT.md.
