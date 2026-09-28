# Modular Monolith — Architecture

SIMAS is developed as a **modular monolith**: one deployable Laravel
application whose internal boundaries are enforced by tooling, not by
convention alone. Two enforcement layers work together:

1. **Deptrac** (`deptrac.php`, run via `composer deptrac`) — class-level
   dependency rules between layers.
2. **Pest arch tests** (`tests/Architecture/ModularMonolithTest.php`) —
   rules that are awkward in Deptrac: import scans for non-class code,
   migration FK scanning, etc.

A change that crosses a boundary illegally fails `composer deptrac`,
`composer test`, and CI.

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
- **Core** (`Modules\Core`) — master data (skeleton in Fase 0).
- **App / Database** — Laravel glue only: providers, config, root
  seeders. No business logic. (`DatabaseSeeder` creating the example
  user via `UserFactory` is the single documented exception.)

## Modules

| Module | Layer | Owns | Public surface |
|---|---|---|---|
| Shared | `Shared` | Generic technical utilities | Everything (by definition) |
| Platform (Fase 1) | `Platform` | Tenancy, module registry, permissions | `Modules\Platform\Contracts` |
| Identity | `Identity` | User model, auth scaffold | `Modules\Identity\Contracts` (`ResolvesUsers`, `UserRecord`) |
| Core | `Core` | Master data (future) | `Modules\Core\Contracts` (empty) |

Each module folder follows the same shape:

```
modules/<Name>/
  Providers/<Name>ServiceProvider.php   # registered in bootstrap/providers.php
  routes/web.php
  Database/Migrations/                   # module-owned migrations
  Database/Factories/
  Database/Seeders/
  Models/ Services/ Policies/ Config/    # as needed
  Contracts/                             # PUBLIC surface — the only cross-module API
  Tests/Feature/ Tests/Unit/             # module-owned tests
  CONTRACT.md                            # documents the public surface
```

## Boundary rules

1. **Cross-module access only via Contracts.** Never import another
   module's `Models\`, `Services\`, `Http\`, or `Database\` namespaces.
   Deptrac layer `XPublic` contains only `Modules\X\Contracts\**`.
2. **No cross-module foreign keys.** References to other modules' data
   are plain columns (`user_id` etc.), no constraint, no Eloquent
   relation. The arch test scans every migration for `->foreign(`,
   `->constrained(`, `->foreignIdFor(`, `->foreignUuid(`.
3. **One-way dependencies.** `Shared` depends on nothing module-ish;
   `Core` may use `IdentityPublic` but not the reverse; feature modules
   (Fase 1+: `attendance`, `ppdb`) never depend on each other.
4. **Other modules must not import `User`.** Store `user_id` as a plain
   column and resolve users through `ResolvesUsers` → `UserRecord`.
5. **Shared stays clean.** No business concepts (student, class,
   teacher, attendance, PPDB), nothing used by only one module. See
   `modules/Shared/CONTRACT.md` for the full policy.

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
2. Add `<Name>ServiceProvider`, register it in `bootstrap/providers.php`.
3. Register the PSR-4 root once: `Modules\` → `modules/` is already in
   `composer.json` (no change needed), then `composer dump-autoload`.
4. Point the module's `ServiceProvider` at its own
   `Database/Migrations` and `routes/web.php`.
5. Add two Deptrac layers in `deptrac.php` — `<Name>` (internal,
   negative-lookahead pattern excluding `Contracts\`) and `<Name>Public`
   — then wire the ruleset following the one-way order:
   internal → `Shared`, `Laravel`, `Vendor`, `PlatformPublic`, and
   `Public` surfaces of *lower* modules only.
6. Add the module to the `$modules` list in
   `tests/Architecture/ModularMonolithTest.php`.
7. Write `CONTRACT.md` describing the public surface.
8. Run `composer dump-autoload && composer deptrac && vendor/bin/pest`.

## Fase 1 notes (reserved, not built yet)

- `modules/Platform` gets tenancy + `Modules\Platform\Contracts`
  (module registry, permission contracts, `HasTenantRoles` wrapper
  trait so modules never import Spatie directly).
- `users` gains `tenant_id` + `unique(tenant_id, email)`;
  `password_reset_tokens` becomes tenant-aware. Identity owns those
  migrations; login behaviour stays untouched until then.
- `ProviderUser` will live in Platform, not Identity.
