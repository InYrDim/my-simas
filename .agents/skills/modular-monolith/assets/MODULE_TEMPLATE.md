# Module Folder Template

Copy this tree exactly for every new or extracted module. Replace `<Name>`
with a PascalCase module name (e.g. `Billing`, `Invoicing`, `Notifications`).

```
Modules/<Name>/
├── CONTRACT.md
├── app/
│   ├── Contracts/            # PUBLIC. Only namespace other modules may use.
│   │   └── <Name>Contract.php
│   ├── Domain/                # Business logic. Private to this module.
│   │   ├── Models/
│   │   ├── Actions/            # or UseCases/ — one class per business operation
│   │   └── Events/
│   ├── Infrastructure/         # Private to this module.
│   │   ├── Repositories/
│   │   └── Providers/
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
├── tests/
│   ├── Feature/
│   └── Unit/
└── Providers/
    └── <Name>ServiceProvider.php   # registers routes, views, migrations
```

## Rules for this structure

- **Nothing outside `app/Contracts/` is importable by another module.** This
  is enforced by `deptrac.yaml` — see the root of the repo.
- `<Name>ServiceProvider.php` is the only place that wires this module into
  the app (route registration, migration path, view namespace). Register it
  in `config/app.php` or `bootstrap/providers.php`.
- If you're using `nwidart/laravel-modules`, this tree matches what
  `php artisan module:make <Name>` scaffolds — prefer that command if the
  package is installed, then adjust to match this shape.
- Shared code that legitimately belongs to no single module goes in
  `Modules/Shared/` using this same shape (it has no `Contracts/` restriction
  on the *consuming* side, but its own internals still shouldn't be reached
  into directly — treat `Modules/Shared/app/Domain` as its own contract
  surface for simplicity).
