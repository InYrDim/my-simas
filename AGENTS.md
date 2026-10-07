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
column (no FK, no Eloquent relation); use Identity's Contracts for user
data, Auth/Gate for the logged-in user. users is tenant-scoped (tenant_id
+ unique(tenant_id, email)); User uses BelongsToTenant + HasTenantRoles
from PlatformPublic, never Spatie directly. NO public registration.
password_reset_tokens is tenant-scoped, ONE shared table for reset +
provisioning + invitations; tokens are minted via the ambient-context
repository (fail closed). Rate limiting lives in controllers keyed
{flow}:{tenant_id}:{email}:{ip} (throttle: middleware has no tenant
context). Mails are queued Mailables with URLs from TenantUrl; views via
the Identity:: namespace (dot-path into modules/ does NOT work); no
Notification machinery (locked). Anti-enumeration: generic responses on
login/forgot/set-password. Roles are machine names from
modules/Identity/config/roles.php; UserPolicy = permission gate +
same-tenant re-assert + deactivated-actor deny. Deactivation keeps rows
and roles; DeactivateUser guards anti-lockout (no self, last ACTIVE admin).
Details: modules/Identity/CONTRACT.md.

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

## Feature modules — pointers (details live in CONTRACT.md + docs)

Read the module's CONTRACT.md and docs/architecture/modular-monolith.md
before touching these areas; only the traps are kept here.

- **Billing / provider console (Fase 3)**: Platform-internal; payment is
  the always-true stub `PaymentGateway`. Contracts `TenantDirectory`,
  `TenantRoles::rolePermissions()`. School admins across tenants:
  Identity `Console/SchoolAdminController`.
- **Statistik & Laporan (Fase 7)**: Core owns the pages and
  `ReportRegistry`/`StatisticsRegistry`; every module registers from its
  own provider, Core never imports a feature module. Reports return a
  `ReportTable`; placeholders in `modules/Core/config/insight.php`.
- **Beranda per role (Fase 13+)**: Core owns the page and
  `DashboardRegistry`/`DashboardWidgetProvider`; modules register widgets
  (scalar payloads, `slot`, `kind`, a Gate `permission`) from their own
  provider and Core filters per signed-in user. Core names no role. Widgets
  are a deferred Inertia prop; a provider that throws is skipped.
- **Accounts for students/teachers (Fase 8)**: made via Identity's
  `AccountProvisioner`, Core keeps only `user_id`; login field `login`
  (`@` = email, else username); `RequirePasswordChange` forces a change
  at first login. A leaving student is deactivated, never deleted.
- **WhatsApp (Fase 9)**: Platform owns the gateway (`WhatsappChannel`),
  Core owns the school page, `NoticeRegistry`, `GuardianNotifier`. The
  per-school session key (encrypted with `OPENWA_CREDENTIALS_KEY`) never
  reaches a DTO, page, log or error message. Env `OPENWA_*` only in `.env`.
  Tests: `Http::fake` + `Http::preventStrayRequests`, never the real gateway.
- **Attendance (Fase 10, 13–15)**: reads Core only via `StudentDirectory`,
  `ClassDirectory`, `BellSchedule`, `TeacherSchedule`, `ClassTimetable`
  (DTOs, plain ids); exposes nothing; never checks the WhatsApp switch.
  Days are `Y-m-d` strings, timestamps via `SchoolClock::stored()`. No
  scheduler: nothing marks absence automatically. Scan times:
  `Domain/Support/ScanWindow` (internal); manual input and history are the
  correction path. Menus: teacher "Jadwal Saya" + "Kelas Mengajar",
  student "Kelas Saya".
- **PPDB (Fase 11)**: applicants have a central `ppdb_accounts` table (no
  tenant scope, guard `ppdb`, only FK is `tenant_id`); account pages run in
  `TenantContext::run($account->tenant_id)`; refusals to join always give
  the same message. Core's `StudentAdmission::admit()` creates the student;
  results go through `ContactNotifier` (kind `ppdb.result`, off by default).
  `ppdb` is in no plan yet.
- **School setup checklist (Fase 12)**: Core-internal `SetupChecklist`,
  computed per visit, no table, no contract.

After a release run `php artisan migrate` and `php artisan roles:sync`
(roles/permissions changed in Fase 10, 11, 13, 15 and "Kelas Saya siswa").
