# Module: Attendance

## Owns

- Database tables (all tenant-scoped, no foreign keys except `tenant_id`;
  every other id is a plain indexed column): `attendance_settings` (one
  row per school: the cut-off for arriving on time), `daily_attendances`
  (one row per student and day: the day's status and the gate times in
  and out), `lesson_sessions` (one class in one lesson slot of one day)
  and `lesson_attendances` (one row per student of a session).
- Core domain concepts: student attendance (Absensi) — at the school gate
  (in and out), per day (hadir, terlambat, sakit, izin, alpa) and per
  lesson; the student's one-time attendance QR; the daily and monthly
  recap; the attendance reports and figures on Statistik & Laporan; and
  the four WhatsApp notices to guardians.
- Permissions (names attached to the default roles through Identity's
  `config/roles.php`): `attendance.view` (recap and reports — admin,
  guru, staf-tu), `attendance.daily.record` (gate and daily input —
  admin, guru, staf-tu), `attendance.lesson.record` (lesson attendance —
  admin, guru), `attendance.settings.manage` (admin) and
  `attendance.qr.show` (a student's own QR — siswa).

## Public interface (Contracts/)

- None. `modules/Attendance/app/Contracts` does not exist: nothing in
  Attendance is used by another module. It registers with Platform and
  Core through their contracts instead.

## Allowed dependencies

- Modules/Shared
- Modules/Platform **App/Contracts only** (PlatformPublic)
- Modules/Identity **App/Contracts only** (IdentityPublic)
- Modules/Core **App/Contracts only** (CorePublic)
- Laravel/Vendor

Never another feature module (Ppdb), not even via its Public surface.

## Events published

- None.

## Events consumed

- None.

## Explicitly NOT exposed

- Everything: models, actions, the QR codes, the recap queries.

## Notes for maintainers

- **What it reads from Core.** Students, classes and the bell schedule
  come only through `StudentDirectory`, `ClassDirectory` and
  `BellSchedule` (DTOs, never Core's models). `student_id`, `class_id`,
  `period_slot_id`, `subject_id`, `teacher_id` and `recorded_by` are plain
  columns; a daily row keeps the class the student sat in when it was
  written.
- **What it registers** (`AttendanceServiceProvider`): the module key
  `attendance` (enabled per tenant; every route sits behind `auth` +
  `module:attendance`), its permissions, the sidebar entries (each child
  with its permission; a student sees only "QR Absensi"), four notice
  kinds with Core's `NoticeRegistry`, and two reports and one statistics
  provider with Core's `ReportRegistry` / `StatisticsRegistry`.
- **The school's clock.** Days and times of day are the tenant's
  (`Domain/Support/SchoolClock` over `TenantContext::timezone()`). `date`
  columns hold the school's day as a plain `Y-m-d` string, so they compare
  the same on every database; timestamps are stored in the application
  timezone (`SchoolClock::stored()` — Eloquent writes a date in the
  timezone the object carries). Months are grouped in PHP, not with SQL
  date functions.
- **Daily attendance** (`/absensi/input`, `SaveDailyAttendance`): one
  class and one day, never a day that has not come, only students of that
  class; saving again updates the rows and never touches the gate times.
  A student without a row is "belum diabsen" — nothing marks absence
  automatically (no scheduler: the app runs on shared hosting).
- **The gate** (`RecordGateCheckIn`, `RecordGateCheckOut`): arriving up to
  and including the cut-off minute of `attendance_settings.late_after`
  (default 07:00) is Hadir, later is Terlambat; arriving overrules a
  sakit/izin/alpa given earlier that day; a second arrival, leaving
  without having arrived and a second leaving are refused with the time
  already recorded.
- **The QR** (`Domain/Qr/QrTokens`, `/absensi/qr-saya`): a random
  40-character code kept in `TenantCache` for 60 seconds — no table, and
  nothing about the student in the code. One code lives per student: the
  page asks for a new one every 45 seconds and that kills the previous
  one; a code is used up by the scan, even a refused one. The page needs
  `attendance.qr.show` **and** an account linked to an active student
  (`StudentDirectory::findByUserId`); asking for codes is limited to 20 a
  minute per account (`attendance-qr:{tenant}:{user}`). The cache store
  has no atomic read-and-delete: two scanners reading one code at the same
  moment are stopped by the "already recorded" rule and the unique index.
- **The scanner** (`/absensi/pindai`, `ScanController`): one JSON endpoint
  for a code (`token`) or a manual pick (`student_id`), in mode `gate-in`,
  `gate-out` or `lesson`. The mode decides the permission (gate → daily,
  lesson → lesson). A refusal answers 422 in the shape of a validation
  error (`errors.scan`). The camera uses `qr-scanner`, loaded on demand;
  it needs https (or localhost).
- **Lessons** (`/absensi/jam-pelajaran`, `SaveLessonAttendance`,
  `MarkLessonPresence`): a class of the active academic year, a slot of
  type `Pelajaran` on that day's weekday, an optional subject that must be
  taught in that class. One session per class, day and slot. There is no
  timetable: a teacher may record any class, and the classes the teacher
  teaches or leads (`ClassDirectory::idsTaughtBy`) are offered first, with
  the teacher's own subject chosen. A lesson has no "terlambat".
- **Switches** (`/absensi/pengaturan`): `attendance_settings.gate_enabled`
  and `lesson_enabled` (both on by default) let a school turn the gate and
  the lesson attendance off. Routes, sidebar entries and the scanner ask
  the Gate abilities `attendance.gate.use`, `attendance.lesson.use`,
  `attendance.scan.use` (either one) and `attendance.qr.use` (a student's
  QR, while either is on): the permission **and** the switch. Recorded data
  stays; daily input, the recap and the reports are not affected.
- **Notices** (`Domain/Notifications/AttendanceNotices`):
  `attendance.gate-in`, `attendance.gate-out`, `attendance.absent`,
  `attendance.lesson-absent`. Attendance always calls
  `GuardianNotifier` and never looks at the school's switch — that is
  Core's. Only today is announced; a daily or lesson absence only when the
  status just became that; a lesson absence not when the day already says
  sakit, izin or alpa. Notices are sent after the database transaction.
- **Recap and insight**: `Domain/Queries` (`DailyRecap`, `MonthlyRecap`,
  `AttendanceTally`); the rate is hadir + terlambat over all recorded
  days. Reports `attendance-monthly` and `attendance-class` and the figure
  `attendance-rate` / panel `attendance-trend` replace Core's announced
  entries.
- Sample data: `php artisan db:seed --class="Modules\Attendance\Database\Seeders\AttendanceDemoSeeder"`
  (after Core's demo seeder; schools with the module only).
