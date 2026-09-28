# Module Folder Template

Copy this tree exactly for every new or extracted module. Replace `<Name>`
with a PascalCase module name (e.g. `Billing`, `Invoicing`, `Notifications`).

```
Modules/<Name>/
├── CONTRACT.md
├── app/
│   ├── Contracts/            # PUBLIC. Only namespace other modules may use.
│   │   ├── <Name>Contract.php
│   │   ├── DTOs/               # public DTOs returned by contracts
│   │   ├── Events/             # events OTHER modules listen to (public)
│   │   ├── Exceptions/         # public exceptions
│   │   └── Concerns/           # public traits (may delegate to internals)
│   ├── Domain/                 # Business logic. Private to this module.
│   │   ├── Models/
│   │   ├── Actions/            # or UseCases/ — one class per business operation
│   │   ├── Policies/
│   │   └── Events/             # INTERNAL events (never imported cross-module)
│   ├── Infrastructure/         # Private to this module.
│   │   ├── Repositories/
│   │   └── Providers/
│   │       └── <Name>ServiceProvider.php  # THE only module provider
│   └── Http/                   # Private to this module.
│       ├── Controllers/
│       ├── Requests/
│       └── Resources/
├── resources/
│   └── js/
│       ├── Pages/               # Inertia pages for this module only
│       └── Components/          # Components used only within this module
├── routes/
│   ├── web.php
│   └── api.php
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
└── tests/
    ├── Feature/
    └── Unit/
```

## Rules for this structure

- **Nothing outside `app/Contracts/` is importable by another module.** This
  is enforced by `deptrac.php` (Deptrac 4.x PHP config — there is no
  `deptrac.yaml`) plus Pest arch tests. Layers: `<Name>Public` collects
  `Modules\<Name>\App\Contracts\**`; `<Name>` collects the rest (Domain,
  Infrastructure, Http, routes, database, tests, resources).
- Contracts never expose Eloquent models from `Domain/` — return DTOs
  (`Contracts/DTOs`), scalars, or DTO collections. Public traits in
  `Contracts/Concerns/` may delegate to the module's own internals; they
  must never reach into another module's internals.
- Events other modules listen to live in `Contracts/Events/` (that is what
  makes them legally importable). Internal events stay in `Domain/Events/`.
  Listeners live in the listening module and register in that module's
  provider. `app/Events` (root) is only for legacy code not yet extracted.
- `<Name>ServiceProvider.php` under `app/Infrastructure/Providers/` is the
  only service provider of the module and the only place that wires it into
  the app (routes web+api, migration path, view namespace, contract
  bindings). Register it in `bootstrap/providers.php`. Do NOT create a
  root-level `Providers/` folder in the module.
- No cross-module foreign keys in migrations (sole exception:
  `tenant_id` → `tenants`, owned by Platform) and no Eloquent relations
  across modules. Reference other modules' data as plain columns.
- Namespaces follow the folders: `Modules\<Name>\App\...` for `app/`,
  `Modules\<Name>\Database\...` for `database/`, `Modules\<Name>\Tests\...`
  for `tests/` (mapped per-subnamespace in `composer.json`). Match the
  casing exactly — CI runs on Linux, which is case-sensitive.
- If you're using `nwidart/laravel-modules`, this tree matches what
  `php artisan module:make <Name>` scaffolds — prefer that command if the
  package is installed, then adjust to match this shape. Do not add the
  package just to scaffold.
- Shared code that legitimately belongs to no single module goes in
  `Modules/Shared/` using this same shape (it has no `Contracts/` restriction
  on the *consuming* side, but its own internals still shouldn't be reached
  into directly — treat `Modules/Shared/app/Domain` as its own contract
  surface for simplicity). Shared contents stay pure technical utilities:
  no business concepts, and code used by only one module belongs in that
  module instead.
