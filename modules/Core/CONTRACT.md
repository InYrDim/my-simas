# Module: Core

## Owns
- Database tables: none yet (master-data tables arrive with the first
  Core feature)
- Core domain concepts: SIMAS master data (schools, academic years,
  classes, subjects — placeholder for Fase 1+).

## Public interface (Contracts/)
- None yet. `modules/Core/app/Contracts` exists but is empty; the
  Deptrac `CorePublic` layer is already wired for it.

## Allowed dependencies
- Modules/Shared
- Modules/Platform **App/Contracts only** (PlatformPublic)
- Modules/Identity **App/Contracts only** (IdentityPublic)
- Laravel/Vendor

## Events published
- None.

## Events consumed
- None.

## Explicitly NOT exposed
- Everything — until a real contract exists, nothing in Core may be
  imported by another module (Deptrac: nothing may depend on the
  internal `Core` layer anyway).

## Notes for maintainers
- Core may depend on `IdentityPublic`, but never on feature modules
  (attendance, ppdb), and feature modules must never depend on Core's
  internals — only `CorePublic` once it exists.
