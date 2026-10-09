# Module: Core

## Owns

- Database tables (all tenant-scoped, no foreign keys except
  `tenant_id`; relations are plain indexed columns): `school_profiles`,
  `academic_years`, `semesters`, `grades`, `majors`, `rooms`, `subjects`,
  `classes`, `teachers`, `students`, `student_class_history`,
  `extracurriculars`, `extracurricular_members`, `teaching_assignments`,
  `period_slots`, `calendar_events`, `whatsapp_messages` (the log of every
  WhatsApp message a school tried to send), `whatsapp_notice_settings`
  (a school's switch and wording per kind of notice).
- Core domain concepts: SIMAS master data (school profile, academic years
  and semesters, grades and majors, classes, subjects, rooms, teachers,
  students, extracurriculars), academic management on top of it
  (homeroom teachers, teaching assignments, student placement, bell
  schedule, academic calendar), the CSV import of students and teachers,
  the school's statistics and report catalogue (`Statistik & Laporan`,
  the shell and the registries every module reports through), the
  school's WhatsApp page with its notices to guardians and message log
  (the gateway itself is Platform's), and the school landing record
  (`Beranda Sekolah`, route `home`).
- Permissions: `core.master.view` (read) and `core.master.manage`
  (create/update/delete) for master data; `core.academic.view` and
  `core.academic.manage` for academic management. Attached to the default
  roles through Identity's `config/roles.php` (names only): admin-sekolah
  holds all four, guru and staf-tu only the two `view` permissions.
  `core.integration.manage` (Fase 9) gates Integrasi › WhatsApp and its
  sidebar entry; only admin-sekolah holds it.
  `core.me.view` gates the "Saya" pages (`/saya/*`, sidebar group "Saya");
  guru, staf-tu and siswa hold it. Profil Saya (`MeController`) is read
  only and takes no id: it shows the student or teacher whose `user_id` is
  the signed-in account (`Domain/Queries/SignedInPerson`), plus the sign-in
  and roles through Identity's `ResolvesUsers`. `core.teaching.view` (guru)
  gates the teacher's weekly lessons (`/saya/jadwal`, no menu entry since Fase 15: Attendance's Jadwal Saya shows the same week): the teacher's own weekly lessons
  through `Contracts/TeacherSchedule`. The Beranda "Kelas saya" list still
  reads `Domain/Queries/TeacherClasses` (the active year's classes the
  teacher is homeroom of or teaches in). Beranda quick links come from
  sidebar entries a module marks `shortcut: true` in `TenantNavigation`.
  The lesson timetable (`timetable_entries`: lesson slot + class + subject;
  the teacher is the one the class's teaching assignment names, so it is
  not stored) is edited on Akademik › Jadwal Pelajaran (`core.academic.manage`;
  read-only with `core.academic.view`; `SaveTimetableEntry` refuses a subject
  without a teacher in that class and a teacher in two classes at once),
  read by a teacher on Jadwal Mengajar, and read by Attendance through
  `TeacherSchedule` for the teacher's Kelas Saya pages (Fase 13). Deleting a
  slot or a class's subject assignment removes its lessons. Other modules
  add their own entries to the same group from their providers.

## Public interface (Contracts/)

Read access to school records (Fase 10) — a feature module never sees
Core's models; it asks these, always for the current school, and gets
DTOs.

- `StudentDirectory` — `find(id)`, `findByUserId(userId)` (the student
  whose record carries that login account), `many(ids)` (keyed by id),
  `ofClass(classId)` (active students, by name) and `search(term, limit)`
  (active students by name or NIS).
- `ClassDirectory` — `ofActiveYear()`, `ofYear(academicYearId)` (by name,
  natural order), `find(id)`, `subjectsOf(classId)` (from
  `teaching_assignments`) and `idsTaughtBy(userId)` (classes of the active
  year the teacher behind that account teaches or leads; empty when the
  account is no teacher's).
- `BellSchedule` — `slotsOn(day)` (1 = Senin ... 7 = Minggu, by start time)
  and `find(slotId)`.
- `TeacherSchedule` (Fase 13) — `week(userId)` (the teacher's whole week,
  only days with lessons, ordered by weekday and bell order) and
  `onDay(userId, day)` (that weekday's lessons in bell order); empty when
  the account is no teacher's or has no lesson in the active academic
  year. The teacher is the one the class's teaching assignment names, so
  a slot identifies the lesson: `SaveTimetableEntry` refuses a teacher in
  two classes at once. Attendance reads this for the teacher's Kelas Saya
  pages; Core's own Jadwal Mengajar reads it too.
- `ClassTimetable` — `week(classId)`: one class's whole week in the same
  `ScheduleDay`/`ScheduleLesson` shape, each lesson with the `teacherName`
  its class teaching assignment gives the subject (null for a teacher's own
  `TeacherSchedule`). Attendance reads it for the student's Kelas Saya.
- `DTOs/StudentRecord` (id, name, NIS, class id and name, active — no
  guardian details), `DTOs/ClassRecord` (id, name, academic year id,
  homeroom teacher's name, number of active students),
  `DTOs/ClassSubject` (subject, teacher, and the teacher's account id
  when there is one), `DTOs/BellSlot` (id, day, start and end as `H:i`,
  type, `isLesson`), `DTOs/ScheduleDay` (weekday number and name with its
  lessons), `DTOs/ScheduleLesson` (slot id, its order among the day's
  lesson slots, start and end as `H:i`, class id and name, subject id and
  name).

Admitting a new student (Fase 11) — a feature module asks Core to make a
student; Core makes it the way every other path does (`SaveStudent`).

- `StudentAdmission::admit(NewStudent): int` — the new student's id. The
  student is active, has no class (placing is Akademik / the student's
  form, or Akademik › Penempatan Siswa › "Belum ditempatkan", which lists
  active students without a class and gives them their first one) and no
  login account (fase 8). The caller decides who may ask;
  the contract checks no permission. PPDB uses it at re-registration.
- `DTOs/NewStudent` (name, NIS, gender `L`/`P`, optional NISN, birth date
  as `Y-m-d`, guardian name and phone).
- `Exceptions/StudentAdmissionRefusedException` — the NIS or NISN is
  already used in this school; `column` names which (`nis` / `nisn`) and
  the message is ready to show. Nothing is saved then.

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

Beranda — a module adds blocks to each person's landing page from its
service provider. Core asks the providers of the modules active for the
tenant (deferred, after the page shell), keeps the widgets the signed-in
user may see and groups them by slot; it names no role and no feature
module. A provider that throws is reported and skipped.

- `StudentActionRegistry::register(string $module, class-string<StudentActionProvider> $provider)`
  — links on the Siswa list; `StudentActionProvider::actions()` returns
  `DTOs\StudentAction` (`key`, `label`, `allUrl`, `studentUrl` to which the
  student's id is appended), run for the signed-in user only while the
  module is active. The page shows a "Cetak semua" button and an "Aksi"
  column when any come back (Attendance's static QR).
- `DashboardRegistry::register(string $module, class-string<DashboardWidgetProvider> $provider)`
  — blocks for the Beranda.
- `DashboardWidgetProvider` — `widgets()`, run for the signed-in user inside
  the tenant context; it returns what it could show and names the
  permission each widget needs.
- `DTOs/DashboardWidget` — `key`, `kind` (`stat`, `list`, `bars`, `status`,
  `action`), `slot` (`action`, `figures`, `attention`, `main`), `title`,
  `payload` of scalars (shape per kind is documented on the DTO), `order`,
  `href` and `permission` (a Gate ability; null means everyone signed in).

Reports and providers are resolved from the container and run inside the
tenant context of the request. Registering a report or figure under the
key of an announced entry (`config/insight.php`, e.g. `ppdb-applicants`)
replaces its "Segera hadir" placeholder. Attendance registers
`attendance-monthly`, `attendance-class`, the figure `attendance-rate` and
the panel `attendance-trend`. A registered report key stops being
announced for every school — a school without that module sees neither
the report nor a placeholder; an announced figure or panel stays "Segera
hadir" for a school whose module does not provide it. PPDB registers
`ppdb-applicants` and `ppdb-result` (fase 11), so only `schedule` is still
announced for reports; PPDB's figure (`ppdb-applicants-total`) and panel
(`ppdb-by-path`) were never announced and appear only for a school with
the module.

WhatsApp notices to guardians (Fase 9) — a module says what happened
about a student; whether a message goes out, and how it reads, is the
school's choice on Integrasi › WhatsApp.

- `NoticeRegistry::register(string $module, NoticeKind $kind)` — a kind of
  notice, registered from the owning module's provider. Offered only
  while its module is active for the school; every kind starts switched
  off. Registering the key of an announced kind (`config/notices.php`,
  now empty: every announced kind is built) replaces its "Segera hadir"
  placeholder. Attendance registers `attendance.gate-in`,
  `attendance.gate-out`, `attendance.absent` and `attendance.lesson-absent`;
  Ppdb registers `ppdb.result`.
- `GuardianNotifier::notify(GuardianNotice $notice): void` — sends the
  notice to the student's guardian from the school's number. Kind off →
  nothing happens and nothing is logged. Kind on → the message is logged
  and queued even when it cannot be delivered (no guardian number,
  WhatsApp not linked); the log says why. A student of another school is
  not found, so nothing is sent.
- `ContactNotifier::notify(ContactNotice $notice): void` — the same for
  someone with no student record (an admissions applicant's guardian): the
  caller gives the recipient's name and number and the name of who it is
  about (`{nama_wali}`, `{nama_siswa}`); the switch, wording, log and queue
  are the same and the log row has no student. Added for Ppdb's
  `ppdb.result` (fase 11).
- `DTOs/NoticeKind` (key, title, description, recipient label, default
  template, `variables`: the kind's own variable names with a sample
  value each) and `DTOs/GuardianNotice` (student id, kind key, values for
  the kind's variables). Templates use `{name}`; Core fills
  `{nama_siswa}`, `{nama_wali}` and `{nama_sekolah}` itself and a caller
  cannot replace them; a variable nobody filled stays as written.
- `Exceptions/UnknownNoticeKindException` — the kind is not registered,
  or its module is not active for the school.

## Registers into Platform

- `students` usage meter (`Platform\Contracts\UsageMeters`, Fase 17):
  every `Student` row of the school counts, whatever its status
  (`active`, `graduated`, `transferred`, `left`). Shown against the plan's
  `students` limit in the school's account panel and the provider console;
  nothing blocks when it is over.

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
  directory implementations (`Infrastructure/Directory/Eloquent*`), the
  registries' read side (`Infrastructure/Insight/Default*Registry` —
  catalogue, find, figures, panels) and the CSV reader/writer. Other
  modules register through the contracts and never read the catalogue.
- The WhatsApp internals: `WhatsappMessage`, `WhatsappNoticeSetting`,
  `Infrastructure/Whatsapp/*` (`QueueWhatsappMessage`, the
  `DeliverWhatsappMessage` job, `PhoneNumber`, `NoticeTemplate`, the
  registry's read side). A module sends only through `GuardianNotifier`.

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
  `/` by session state: console host → provider console; signed-in school
  user → `home`; applicant → onboarding; remembered school code → the
  school login; anyone else → Platform's public landing page. The
  school's own landing is Core's, at `GET /beranda`, named `home` so the
  auth redirect lands there.

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
  Master data and academic management are one
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
  `identity.users.view` (`accounts: null` for everyone else). `setup` is
  the school's setup checklist (`Domain/Queries/SetupChecklist`): the
  master-data steps in the order the data depends on itself, computed
  from the school's own records and stored nowhere. It goes only to
  holders of `core.master.manage` while a required step is still open
  (`setup: null` for everyone else and once the school is set up). A
  school's profile counts as done when its grades exist, because grades
  are only seeded when the profile is saved. Surface
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
    Jadwal Pelajaran (no timetable table yet). The Kehadiran reports and
    the attendance figure and trend are Attendance's (Fase 10) and the
    Penerimaan (PPDB) reports are Ppdb's (Fase 11);
    `config/insight.php` still lists them, but a registered report key is
    no longer announced for any school, and the announced attendance
    figure and trend stay "Segera hadir" on the Statistik page of a school
    without that module.
- **Integrasi › WhatsApp** (`/integrasi/whatsapp`, Fase 9; the page, its
  actions and the sidebar entry need `core.integration.manage`) —
  `WhatsappController` over Platform's `WhatsappChannel`. Core never sees
  a gateway key, and neither does the page.
  - **Request and link.** `POST ajukan` asks the provider; the panel
    shows waiting, rejected (with the provider's note, "Ajukan lagi"),
    disabled (with the note) or approved. `POST hubungkan` starts the
    link; the page re-reads only the `state` prop every 3 seconds while
    the gateway is initialising, showing a QR or authenticating, and
    shows the QR image the server passed on. `POST putuskan` unlinks.
    The page asks the gateway once per load while WhatsApp is approved.
  - **Sending.** Every message goes through
    `Infrastructure/Whatsapp/QueueWhatsappMessage`: the number is
    normalised (`PhoneNumber`: `0812…`, `+62 …`, `62…`, `812…` → `62…`;
    a number written with `+` keeps its country code), a row is written
    to `whatsapp_messages`, and the job `DeliverWhatsappMessage` is
    queued. Statuses: `pending` → `sent` (accepted by WhatsApp, not a
    delivery receipt) | `failed` | `unsent` (WhatsApp not linked) |
    `no_recipient` (no usable number; never queued). A gateway that is
    down or busy gets the message again (3 attempts, 30 then 120
    seconds; the `sync` queue driver has no later, so it fails at once);
    a job that runs twice sends once. A message Platform holds back on
    purpose (`throttled`: pacing, daily limit, paused) is put back in the
    queue for the seconds it names without using up an attempt
    (`$tries = 0`, `retryUntil()` 12 hours from `queuedAt`); past that it
    ends `unsent` ("Pengiriman tertunda terlalu lama."). Gateway failures
    are counted in `whatsapp_messages.error_attempts` (3, then `failed`).
  - **Wording variants (Fase 16).** `NoticeTemplate::fill` picks one
    option at random from each `{a|b|c}` group, then fills the
    `{variables}`, so a value containing `|` or `{` is never parsed.
    Groups cannot nest and need two or more non-empty options
    (`groupError`, enforced by `SaveNoticeSetting`). `preview()` always
    takes the first option. Names and status stay variables, never part of
    a group. `VariationPicker` (used by both notifiers) also keeps a group
    from showing the choice of the school's previous message of the same
    wording: one short string per wording in `TenantCache`.
  - **Reply line.** Per kind, `whatsapp_notice_settings.reply_footer` +
    `reply_footer_text` (null = `WhatsappNoticeSetting::DEFAULT_FOOTER`,
    asking the guardian to answer "OK"): `wording()` appends it after a
    blank line. A guardian who replies makes the school's number look like
    a person. The text may use `{a|b|c}` and the variables.
  - **High volume and the risk warning (Fase 16).** `NoticeKind::$highVolume`
    marks kinds sent for many students at once; `PUT pemberitahuan/{kind}`
    refuses to switch one on without `confirm_volume`. `POST
    setujui-risiko` records the school's acceptance of the unofficial
    WhatsApp warning; the page asks for it before the QR.
  - **Test message.** `POST uji` (`SendTestMessage`) sends one short
    message to a number the admin types; limited to 5 per minute per
    admin (`whatsapp-test:{tenant}:{user}:{ip}`).
  - **Notices.** The list shows the kinds registered through
    `NoticeRegistry` for the school's active modules, then the announced
    ones. `PUT pemberitahuan/{kind}` (`SaveNoticeSetting`) keeps the
    switch and the school's wording; wording that is empty or equal to
    the default is not stored. Attendance registers four kinds (Fase
    10); PPDB does in its own phase.
  - **History.** The page lists `whatsapp_messages` newest first, 15 per
    page, with the number masked (`62812••••7890`) and without the
    message body; it re-reads itself while a message is still queued.
  - A queue worker must run (`QUEUE_CONNECTION=database`), or messages
    stay "Dalam antrean".
