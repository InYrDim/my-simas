# Module: Core

## Owns

- Database tables: none yet (master-data tables arrive with the first
  Core feature)
- Core domain concepts: SIMAS master data (schools, academic years,
  classes, subjects — placeholder for Fase 1+), and the school landing
  record (`Beranda Sekolah`, route `home`).

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
- The landing page (`Core/Beranda`) reads only through contracts and
  never imports a model from another module:
  `TenantContext::currentOrFail()` for the school and its clock,
  `TenantRoles::names()` for the roles that exist, and
  `ResolvesUsers::currentTenantSummary()` for the account counts.
  Permission checks go through `Gate` (`identity.users.view` /
  `identity.users.create`) so the page never touches `UserPolicy`.
  Role **labels** come from `config('roles')` — machine names never
  reach the UI.
- `app/Http/Controllers/EntryController.php` (the app shell) dispatches
  `/` by tenancy state: central host → provider console or the public
  application, tenant host → `home` or the tenant login. The landing
  page itself is Core's, at `GET /beranda`, named `home` so the auth
  redirect lands there.

## Surfaces

- `modules/Core/resources/js/Pages/Core/Beranda.tsx` — the school's
  landing record, phone-first, dated on the tenant's own clock. Surface
  brief: `.impeccable/surfaces/modules-core-resources-js-pages-core-beranda-tsx.md`.
