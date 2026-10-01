# Module: Core

## Owns

- Database tables (all tenant-scoped, no foreign keys except
  `tenant_id`; relations are plain indexed columns): `school_profiles`,
  `academic_years`, `semesters`, `grades`, `majors`, `rooms`, `subjects`,
  `classes`, `teachers`, `students`, `student_class_history`,
  `extracurriculars`, `extracurricular_members`.
- Core domain concepts: SIMAS master data (school profile, academic years
  and semesters, grades and majors, classes, subjects, rooms, teachers,
  students, extracurriculars), and the school landing record
  (`Beranda Sekolah`, route `home`).
- Permissions: `core.master.view` (read) and `core.master.manage`
  (create/update/delete), attached to the default roles through
  Identity's `config/roles.php` (names only).

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

- Master data, split into three sidebar entries:
  - **Master Data** (`/master/*`, database-backed; reads need
    `core.master.view`, writes `core.master.manage`) — base records that
    must exist: profil
    sekolah, tahun ajaran, tingkat & jurusan, kelas, mata pelajaran, guru
    & tendik, siswa, ruangan, ekstrakurikuler.
  - **Akademik** (`/akademik/*`, still mockup) — academic management on
    top of those records: penempatan siswa (naik/pindah/lulus), pengampu
    mapel, wali kelas, jam pelajaran, kalender akademik. Until built,
    `classes.homeroom_teacher_id` stays empty and nothing writes
    `student_class_history` except the student form.
  - **Impor Data** (`/kelola/impor`, still mockup) — bulk CSV import of
    base records.
  `MasterDataController` serves the remaining mockup pages from
  `Infrastructure/Mock/MasterMockData`, shaped by the school's real jenjang.
  Master data is one thin controller per entity over Domain Actions
  (`Save*`/`Delete*`), FormRequests gated by `core.master.manage`, and
  JsonResources that keep the page props the React pages were built on.
  The "Tambah tahun ajaran" form is pre-filled by `SuggestAcademicYear`
  (latest year + 1, or the running year for a school with none).
  Rules enforced in Actions: one active academic year per school (the
  previous one is archived), semesters stay inside their year and never
  overlap, grades follow the jenjang (`SyncDefaultGrades`, frozen once
  classes exist), a major/room/class/teacher still in use cannot be
  deleted. Teachers and students keep an optional plain `user_id` (no FK,
  no relation to Identity's User).
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
