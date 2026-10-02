# Module: Core

## Owns

- Database tables (all tenant-scoped, no foreign keys except
  `tenant_id`; relations are plain indexed columns): `school_profiles`,
  `academic_years`, `semesters`, `grades`, `majors`, `rooms`, `subjects`,
  `classes`, `teachers`, `students`, `student_class_history`,
  `extracurriculars`, `extracurricular_members`, `teaching_assignments`,
  `period_slots`, `calendar_events`.
- Core domain concepts: SIMAS master data (school profile, academic years
  and semesters, grades and majors, classes, subjects, rooms, teachers,
  students, extracurriculars), academic management on top of it
  (homeroom teachers, teaching assignments, student placement, bell
  schedule, academic calendar), the CSV import of students and teachers,
  the school's statistics and report catalogue (`Statistik & Laporan`,
  the shell and the registries every module reports through), and the
  school landing record (`Beranda Sekolah`, route `home`).
- Permissions: `core.master.view` (read) and `core.master.manage`
  (create/update/delete) for master data; `core.academic.view` and
  `core.academic.manage` for academic management. Attached to the default
  roles through Identity's `config/roles.php` (names only): admin-sekolah
  holds all four, guru and staf-tu only the two `view` permissions.

## Public interface (Contracts/)

Statistik & Laporan — a module adds its own reports and figures from its
service provider; Core lists them, checks module flag and permission, and
renders them. Core registers its own the same way.

- `ReportRegistry::register(string $module, class-string<Report> $report)`
  — a report for the catalogue.
- `Report` — implemented by the owning module: `definition()` (key, group,
  name, description, optional permission, order) and
  `table(ReportPeriod $period)`. Core turns the table into the CSV download
  and the print view; a report never deals with file formats.
- `StatisticsRegistry::register(string $module, class-string<StatisticsProvider> $provider)`
  — figures and panels for the Statistik page.
- `StatisticsProvider` — `figures(?ReportPeriod $period)` and
  `panels(?ReportPeriod $period)`; the period is null for a school without
  an academic year.
- `DTOs/ReportDefinition`, `DTOs/ReportPeriod` (academic year id, name,
  start and end date as `Y-m-d` — the academic year model never leaves
  Core), `DTOs/ReportTable` (title, columns, rows of scalars),
  `DTOs/StatFigure`, `DTOs/StatPanel` (`bars` or `share`).

Reports and providers are resolved from the container and run inside the
tenant context of the request. Registering a report or figure under the
key of an announced entry (`config/insight.php`, e.g. `attendance-monthly`,
`ppdb-applicants`, figure `attendance-rate`, panel `attendance-trend`)
replaces its "Segera hadir" placeholder.

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

- Everything outside `app/Contracts`: models, actions, controllers, the
  registries' read side (`Infrastructure/Insight/Default*Registry` —
  catalogue, find, figures, panels) and the CSV reader/writer. Other
  modules register through the contracts and never read the catalogue.

## Notes for maintainers

- Core may depend on `IdentityPublic`, but never on feature modules
  (attendance, ppdb), and feature modules must never depend on Core's
  internals — only `CorePublic` once it exists.
- The landing page (`Core/Beranda`) reads only through contracts and
  never imports a model from another module:
  `TenantContext::currentOrFail()` for the school and its clock,
  `TenantRoles::names()` for the roles that exist, and
  `ResolvesUsers::currentTenantSummary()` for the account counts
  (which now include student accounts).
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

- Master data, split into four sidebar entries:
  - **Master Data** (`/master/*`, database-backed; reads need
    `core.master.view`, writes `core.master.manage`) — base records that
    must exist: profil
    sekolah, tahun ajaran, tingkat & jurusan, kelas, mata pelajaran,
    ruangan, ekstrakurikuler.
  - **Warga Sekolah** — the people: siswa and guru & tendik, with their
    login accounts. A sidebar entry of its own; the pages, URLs
    (`/master/siswa`, `/master/guru`) and permissions are those of Master
    Data.
  - **Akademik** (`/akademik/*`, database-backed; reads need
    `core.academic.view`, writes `core.academic.manage`) — academic
    management on top of those records: wali kelas
    (`classes.homeroom_teacher_id`), pengampu mapel (`teaching_assignments`,
    per class and therefore per academic year), penempatan siswa
    (naik/pindah/lulus, through `SaveStudent` so `student_class_history`
    stays right), jam pelajaran (`period_slots`, one template per school)
    and kalender akademik (`calendar_events`). Rules in Actions: homeroom
    and teaching assignments only for classes of the active academic year;
    saving a class's assignments replaces them (a subject left out loses
    its teacher); a placement is validated in full before the first
    student changes and runs in one transaction (promote needs a later
    year, move stays in the same year); bell slots end after they start and
    never overlap within a weekday; a teacher or subject still assigned
    cannot be deleted, and deleting a class removes its assignments.
  - **Impor Data** (`/kelola/impor`, database-backed; the page and its
    endpoints need `core.master.manage`) — students and teachers from CSV
    in three steps: upload, preview, confirm. Nothing is kept between the
    steps (no table, no stored file): the browser sends the file to
    `POST /kelola/impor/pratinjau` and again to `POST /kelola/impor`, both
    JSON endpoints called with `useHttp`, and the import checks every row
    anew. `Infrastructure/Csv/CsvReader` reads the file (`;` or `,`, BOM,
    `sep=` line, Windows-1252, at most 1,000 rows); `ImportTarget` holds
    the columns of each target and feeds the downloadable template.
    `CheckStudentImport` / `CheckTeacherImport` turn each row into
    `create`, `update` or `error`; `ImportStudents` / `ImportTeachers`
    write the good rows through `SaveStudent` / `SaveTeacher` in one
    transaction and skip the rest. Rules: a student is recognised by NIS
    and a teacher by NIP (a teacher without NIP is always new); in mode
    `add` a registered number is an error, in mode `upsert` it updates,
    and an empty cell never overwrites a stored value; the `kelas` column
    names a class of the active academic year; a student's status is never
    changed by an import.
  `MasterDataController` serves the remaining mockup page (WhatsApp)
  from `Infrastructure/Mock/IntegrationMockData`. Master data and academic management are one
  thin controller per page or entity over Domain Actions (`Save*`/`Delete*`),
  FormRequests gated by `core.master.manage` (master) or
  `core.academic.manage` (via `AcademicFormRequest`), and JsonResources
  that keep the page props the React pages were built on.
  The "Tambah tahun ajaran" form is pre-filled by `SuggestAcademicYear`
  (latest year + 1, or the running year for a school with none).
  Rules enforced in Actions: one active academic year per school (the
  previous one is archived), semesters stay inside their year and never
  overlap, grades follow the jenjang (`SyncDefaultGrades`, frozen once
  classes exist — and the jenjang itself is locked from then on by
  `SaveSchoolProfile`), a major/room/class/teacher still in use cannot be
  deleted. Teachers and students keep an optional plain `user_id` (no FK,
  no relation to Identity's User).
- **Login accounts of students and teachers** (Fase 8;
  `AccountController`, routes under `/master/...` behind
  `core.master.manage`, each action also gated by Identity's
  `identity.users.create` or, for a reset, `identity.users.sendReset`).
  Core never touches the User model: it calls Identity's
  `AccountProvisioner` and `ResolvesUsers` and stores the returned id in
  `user_id`.
  - Students (`CreateStudentAccounts`, per class or per student):
    username = NIS, first password = birth date as `ddmmyyyy`, role
    `siswa`, to be changed at the first login. A student who already has
    an account, is not active, has no birth date, or whose NIS is another
    account's username is skipped and named in the flash message.
  - Teachers (`CreateTeacherAccount`): username = NIP, role `guru` or
    `staf-tu`, a random 10-character first password that reaches the admin
    once through the session flash (`flash.password`). A teacher without a
    NIP is invited by email in `/users` and then linked
    (`LinkTeacherAccount`: the account with the teacher's email, unless
    another teacher already holds it).
  - `ResetLinkedPassword`: a student goes back to the birth date, a
    teacher gets a new random password.
  - The account follows the record: `SaveStudent` deactivates it when the
    student leaves `active` and reactivates it on return, and passes on a
    new name or NIS; `SaveTeacher` passes on a new name or NIP for an
    account that signs in by NIP (a linked email account is left alone);
    `DeleteStudent` / `DeleteTeacher` deactivate the account, never delete
    it (the school's last active admin keeps access).
  - The detail pages get `login: {account, can}` from
    `Http/Concerns/DescribesLinkedAccount`.
- `modules/Core/resources/js/Pages/Core/Beranda.tsx` — the school's
  landing record, phone-first, dated on the tenant's own clock. `me` is
  the student or teacher whose record carries the signed-in account; the
  account figures and role list are sent only to holders of
  `identity.users.view` (`accounts: null` for everyone else). Surface
  brief: `.impeccable/surfaces/modules-core-resources-js-pages-core-beranda-tsx.md`.
- **Statistik & Laporan** (`/statistik-laporan/*`, database-backed; the
  pages and the sidebar entry need `core.master.view`, and each report
  may ask for a permission of its own) — Core owns the shell and the
  registries; every module, Core included, registers its reports and
  figures through the contracts above, the same way modules register
  sidebar entries with Platform. Core never imports a feature module.
  - **Statistik** (`StatisticsController`): figures and panels of the
    active academic year from `Domain/Statistics/SchoolStatistics` —
    active students, teachers, classes of the year, active students per
    grade and by gender.
  - **Laporan** (`ReportController`): the catalogue by group, one academic
    year chosen on the page (`?tahun=<id>`; default the active year, then
    the latest). `GET laporan/{report}/unduh` streams a CSV
    (`Infrastructure/Csv/CsvWriter`: `sep=;` line, CRLF, text cells that
    start with `=`, `+`, `-` or `@` are defused) and
    `GET laporan/{report}/cetak` renders the print view
    (`Core/Insight/ReportPrint`, no shell; the browser's print dialog
    saves it as PDF). Nothing is stored — no history table, no file.
    Unknown, merely announced or disabled-module keys answer 404, a
    missing permission 403, a school without an academic year 404.
  - Core's reports (`Domain/Reports`): `student-list` and
    `student-mutation` read `student_class_history` of the chosen year (so
    a past year reports the classes as they were; a mutation is a row
    whose note is not `Kelas aktif`, dated by its `updated_at` on the
    school's clock — admissions are not recorded and not reported);
    `teaching-load` sums `teaching_assignments` per teacher and needs
    `core.academic.view`.
  - Announced, not built ("Segera hadir", labels in `config/insight.php`):
    Jadwal Pelajaran (no timetable table yet), the Kehadiran and PPDB
    reports, and the attendance figure and trend.
