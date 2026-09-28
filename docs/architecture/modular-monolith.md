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
- **Platform** (`Modules\Platform`) — **reserved, empty**. Fase 1 will
  put tenancy, module registry, and permission contracts here. It is
  already registered as a Deptrac layer so Fase 1 needs no rework.
- **Identity** (`Modules\Identity`) — users, authentication scaffold:
  `User` model, factory, `users`/`password_reset_tokens`/`sessions`
  migrations, `ResolvesUsers` contract, `UserRecord` DTO, policy.
  (See “Folder shape” below: module code lives under the module's own
  `app/` tree, e.g. `Modules\Identity\App\Domain\Models\User`.)
- **Core** (`Modules\Core`) — master data (skeleton in Fase 0).
- **App / Database** — Laravel glue only: providers, config, root
  seeders. No business logic. (`DatabaseSeeder` creating the example
  user via `UserFactory` is the single documented exception.)

## Modules

| Module            | Layer      | Owns                                  | Public surface                                                   |
| ----------------- | ---------- | ------------------------------------- | ---------------------------------------------------------------- |
| Shared            | `Shared`   | Generic technical utilities           | Everything (by definition)                                       |
| Platform (Fase 1) | `Platform` | Tenancy, module registry, permissions | `Modules\Platform\App\Contracts`                                 |
| Identity          | `Identity` | User model, auth scaffold             | `Modules\Identity\App\Contracts` (`ResolvesUsers`, `UserRecord`) |
| Core              | `Core`     | Master data (future)                  | `Modules\Core\App\Contracts` (empty)                             |

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
3. **One-way dependencies.** `Shared` depends on nothing module-ish;
   `Core` may use `IdentityPublic` but not the reverse; feature modules
   (Fase 1+: `attendance`, `ppdb`) never depend on each other.
4. **Other modules must not import `User`.** Store `user_id` as a plain
   column and resolve users through `ResolvesUsers` → `UserRecord`.
5. **Shared stays clean.** All of Shared is importable by every module,
   but its contents stay pure technical utilities — no school business
   concepts (student, class, teacher, attendance, PPDB), nothing used
   by only one module. Business-meaningful code "accidentally used by
   two modules" is reported (candidate contract / Core module), never
   parked in Shared. See `modules/Shared/CONTRACT.md`.
6. **Module creation is authorized per phase.** Platform (Fase 1) and
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
another module's internals. No UI/UX change happens in Fase 0.

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

Deptrac 4 supports baselining existing violations. Policy: **no baseline
is used today** (the report is clean). A baseline may only be introduced
for a violation that genuinely cannot be fixed in its phase, must be
committed with a written justification, and must carry a target phase
for removal. Baselines must never hide violations that are cheap to fix.

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

## Fase 1 notes (reserved, not built yet)

- `modules/Platform` gets tenancy + `Modules\Platform\Contracts`
  (module registry, permission contracts, `HasTenantRoles` wrapper
  trait so modules never import Spatie directly).
- `users` gains `tenant_id` + `unique(tenant_id, email)`;
  `password_reset_tokens` becomes tenant-aware. Identity owns those
  migrations; login behaviour stays untouched until then.
- `ProviderUser` will live in Platform, not Identity.
