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

- Master data (mockup phase), split into three sidebar entries:
  - **Master Data** (`/master/*`) — base records that must exist: profil
    sekolah, tahun ajaran, tingkat & jurusan, kelas, mata pelajaran, guru
    & tendik, siswa, ruangan, ekstrakurikuler.
  - **Akademik** (`/akademik/*`) — academic management on top of those
    records: penempatan siswa (naik/pindah/lulus), pengampu mapel, wali
    kelas, jam pelajaran, kalender akademik.
  - **Impor Data** (`/kelola/impor`) — bulk CSV import of base records.
  `MasterDataController` reads static data from
  `Infrastructure/Mock/MasterMockData`; nothing is persisted and the
  jenjang can be previewed with `?jenjang=sd|smp|sma|smk`. The DB phase
  replaces the mock behind the controller (teachers/students keep an
  optional plain `user_id` column, no FK to Identity).
- `modules/Core/resources/js/Pages/Core/Beranda.tsx` — the school's
  landing record, phone-first, dated on the tenant's own clock. Surface
  brief: `.impeccable/surfaces/modules-core-resources-js-pages-core-beranda-tsx.md`.
- **Statistik & Laporan** (`/statistik-laporan/*`, mockup) — school-wide
  figures and a downloadable report catalogue, from
  `Infrastructure/Mock/InsightMockData`. Decided direction for the DB
  phase: Core owns the shell and the registry, and feature modules
  (Attendance, Ppdb) register their own reports and figures through a
  Core contract, the same way modules register sidebar entries with
  Platform. Core never imports a feature module. Not built yet: the
  registry contract arrives after the mockup phase.
