# CONTRACT — Modules\Shared

Pure technical utilities usable by every module: base classes, generic
traits, helpers, generic value objects, base exceptions, generic UI.

## Rules (hard)

- **Allowed in:** code that knows nothing about school business domains
  (student, class, teacher, attendance, PPDB) and no other module's
  domain concepts.
- **Not allowed in:** models/tables/enums/business rules; code used by a
  single module (put it inside that module); business-meaningful code
  merely shared by two modules (report it instead — candidate for the
  owning module's public surface or a future Core module).
- **Dependencies:** Shared may depend only on `Laravel`/`Vendor`.
  It must never import `Modules\*` namespaces.

## Public surface

Everything under `Modules\Shared\` is public to all modules by design.
