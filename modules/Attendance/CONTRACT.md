# Module: Attendance

## Owns

- Database tables (all tenant-scoped, no foreign keys except `tenant_id`;
  every other id is a plain indexed column): `attendance_settings` (one
  row per school: the cut-off for arriving on time, the gate hours and the
  lesson scan tolerance), `daily_attendances`
  (one row per student and day: the day's status and the gate times in
  and out), `lesson_sessions` (one class in one lesson slot of one day),
  `lesson_attendances` (one row per student of a session) and
  `static_qr_card_templates` (the school's student-card picture and the
  place of the static QR on it, one row per school) and
  `lesson_checks` (one teacher's own "done" mark per lesson and day: the
  todo checkbox on Jadwal Hari Ini; `user_id` is a plain account id).
- Core domain concepts: student attendance (Absensi) — at the school gate
  (in and out), per day (hadir, terlambat, sakit, izin, alpa) and per
  lesson; the student's one-time attendance QR; the daily and monthly
  recap; the attendance reports and figures on Statistik & Laporan; and
  the four WhatsApp notices to guardians.
- Permissions (names attached to the default roles through Identity's
  `config/roles.php`): `attendance.view` (recap and reports — admin,
  staf-tu), `attendance.daily.record` (gate and daily input for any
  class — admin, staf-tu),  `attendance.lesson.record` (lesson attendance
  for any class — admin), `attendance.class.record` (daily and lesson
  attendance, only for the classes the user teaches or leads — guru; the
  sidebar group "Saya" › "Kelas Saya" since Fase 13, with no gate mode
  and no school recap; every write asks `ClassChoices::mayRecord`),
  `attendance.settings.manage` (admin) and `attendance.qr.show` (a student's
  own QR — siswa), `attendance.mine.view`
  (Absensi Saya, `/absensi/saya`: a student's own daily history by month,
  the student found from the signed-in account, never from the URL — siswa),
  `attendance.class.view-own` (the student's Kelas Saya: `/absensi/kelasku`
  Info Kelas, `/jadwal`, `/mapel`; `MyClassController`, class found from the
  account, reads Core's `ClassDirectory`, `StudentDirectory`, `ClassTimetable`).
  Composite ability `attendance.class.lesson.use` = `attendance.class.record`
  AND the school's lesson switch; Fase 13's Absensi Kelas and Riwayat
  Absensi routes and sidebar children ask it.

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
  `module:attendance`), its permissions, the sidebar entries (the office's
  "Absensi", the teacher's "Kelas Saya" with its four pages and the kept
  "Absensi Saya" placeholder in group "Saya", the student's "QR Absensi"
  and "Kelas Saya" (Info Kelas, Jadwal Pelajaran, Mata Pelajaran & Guru,
  Absensi Saya); each child with its permission), four notice kinds
  with Core's `NoticeRegistry`, and two reports and one statistics
  provider with Core's `ReportRegistry` / `StatisticsRegistry`, and one
  dashboard provider (`Domain/Dashboard/AttendanceDashboard`) with Core's
  `DashboardRegistry`: the office's day and the classes still waiting, a
  teacher's lessons of the day, a student's own day and month. Each widget
  names its Gate ability; Core keeps what the signed-in person may see.
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
- **Scan times** (Fase 14, `Domain/Support/ScanWindow`): the gate (in and
  out) takes scans only between `attendance_settings.gate_opens_at` and
  `gate_closes_at` (default 05:00–18:00, the closing minute included); a
  lesson only from `lesson_scan_early_minutes` (default 5, 0–30) before it
  starts until it ends (`MarkLessonPresence`). A refusal answers like any
  scan refusal and points to the correction path (Riwayat Absensi, Input
  harian — neither is bound by these hours). Leaving before the end of the
  day's last lesson (`ScanWindow::lastLessonEnd`, none on a day without
  lessons) sets `daily_attendances.left_early`; the scanner shows "Pulang
  awal". A teacher without `attendance.lesson.school` scans only the own
  lesson that is running (`TeacherLessons`; scanner page and controller),
  and a student already recorded as gone home is refused a lesson scan.
  The office takes that record back on Absensi Gerbang ("Batalkan pulang",
  `DELETE /absensi/input/pulang`, `CancelGateCheckOut`, `attendance.daily.record`:
  admin and staf-tu): today only; clears the time, the method and
  `left_early`, keeps the arrival and the status, notifies nobody.
  Holidays are not known here: no refusal on them yet.
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
- **The static QR** (`Domain/Qr/StaticQrCodes`, `attendance_settings.static_qr_enabled`,
  off by default, switched on Pengaturan): a printed code per student,
  `simas-s1.{student_id}.{signature}`, signed (HMAC) with the app key and the
  school, never expiring and not used up by a scan. The scanner accepts it
  only while the switch is on (otherwise a refusal); a forged or foreign
  code is refused. `/absensi/qr-statis` (`StaticQrController`) prints the
  active students of the active year's classes, grouped by class, or one
  student with `?siswa={id}`. It has no menu entry: it is reached from
  Warga Sekolah › Siswa, where Core shows a "Cetak semua" button and an
  "Aksi" column once Attendance registers `Domain/Students/StaticQrStudentActions`
  with Core's `StudentActionRegistry`. The page asks the ability `attendance.static-qr.print` = `attendance.settings.manage`
  AND the switch (admin). Students without a class are not on the sheet.
  Rotating the app key invalidates every printed code.
  **Card template** (`/absensi/qr-statis/kartu`, `StaticQrCardController`, same
  ability): a PNG/JPG (max 5 MB, min 300x180, never SVG) kept through
  `TenantStorage` (module `attendance`) and served only by that controller; the QR
  box is stored as percentages of the picture (`qr_x`, `qr_y`, `qr_size` of the
  width, always square) plus the printed width in mm. The print page draws the
  card in the browser (`<img>` + `qrcode.react`, so it prints without background
  graphics); `?format=polos` forces the plain sheet and `?embed=1` drops the page
  header. Core opens the print URL in a dialog (iframe) on the Siswa list. A new
  picture resets the box to the middle; the template stays when the switch is off.
- **The scanner** (`/absensi/pindai`, `ScanController`): one JSON endpoint
  for a code (`token`) or a manual pick (`student_id`), in mode `gate-in`,
  `gate-out` or `lesson`. The mode decides the permission (gate → daily,
  lesson → lesson). A refusal answers 422 in the shape of a validation
  error (`errors.scan`). The camera uses `qr-scanner`, loaded on demand;
  it needs https (or localhost).
- **Lessons** (`SaveLessonAttendance`, `MarkLessonPresence`): a class of
  the active academic year, a slot of type `Pelajaran` on that day's
  weekday, an optional subject that must be taught in that class. One
  session per class, day and slot. The school-wide page
  (`/absensi/jam-pelajaran`) asks any class and needs
  `attendance.lesson.school` (admin); a teacher records their own lessons
  through Kelas Saya below. The scanner still offers a teacher the classes
  they teach or lead (`ClassDirectory::idsTaughtBy`), and every write asks
  `ClassChoices::mayRecord`. A lesson has no "terlambat".
- **Kelas Mengajar** (Fase 13, renamed in Fase 15; `/absensi/kelas-saya`,
  `/absensi/absen-kelas`, `/absensi/riwayat`) and **Jadwal Saya**
  (`/absensi/jadwal-saya`): the teacher's workshop in two sidebar entries.
  Kelas Mengajar has Kelas Aktif, Absensi Kelas (tabs Isi Absensi / Koreksi,
  the latter being the old Riwayat Absensi) and Pindai QR; the students
  keep the name Kelas Saya.
  Core's `TeacherSchedule` gives the lessons of the active year; one
  teacher cannot be in two classes in a slot, so a slot identifies the
  lesson and the pages never take a class id. **Kelas Aktif** lists the
  classes with lessons on the schedule (a teaching assignment alone is not
  enough); **Jadwal Saya** has a tab Hari Ini with today's lessons as todos and the
  range banner ("Jadwal Mengajar Hari Ini! Sebagai Berikut" before the
  first hour, "Pembelajaran Sedang Berlangsung" inside the range,
  "Sudah Selesai Jadwal Mengajar Hari ini" after the last) and a checkbox
  that can only be ticked once the lesson's hour is over — it is also
  written when the attendance is saved (`lesson_checks`); **Absensi Kelas**
  auto-opens the lesson running now and saves it only inside its own hour
  (`SaveOwnLessonAttendance` with `whileRunning: true`); the **Koreksi** tab
  lists a day's lessons with their saved recap and edits any of them at
  any time (`whileRunning: false`, never a future day). A slot not on the
  teacher's timetable, another school's ids and a class that is not theirs
  are refused; saving through `SaveOwnLessonAttendance` always goes through
  `SaveLessonAttendance`, so subject, notices and one-session rules are
  the same. The teacher's old pages are gone: daily input is
  `attendance.daily.record`'s (admin, staf-tu), the school-wide lesson page
  `attendance.lesson.school`'s (admin), and `Pindai QR` stays open to a
  teacher, as a Kelas Saya child (`attendance.scan.use`, home shortcut).
  Jadwal Saya's second tab, Minggu Ini, is the whole week from
  `TeacherSchedule::week()` (Core's `/saya/jadwal` stays reachable but has
  no menu entry). The teacher's empty "Absensi Saya" placeholder is gone.
- **Switches** (`/absensi/pengaturan`): `attendance_settings.gate_enabled`
  and `lesson_enabled` (both on by default) let a school turn the gate and
  the lesson attendance off. Routes, sidebar entries and the scanner ask
  the Gate abilities `attendance.gate.use`, `attendance.lesson.use`,
  `attendance.class.lesson.use`, `attendance.scan.use` (either one) and
  `attendance.qr.use` (a student's QR, while either is on): the permission
  **and** the switch. A school with the lesson switch off keeps Kelas
  Aktif and Jadwal Hari Ini; Absensi Kelas and Riwayat Absensi disappear.
  Recorded data stays; daily input, the recap and the reports are not
  affected.
- **Notices** (`Domain/Notifications/AttendanceNotices`):
  `attendance.gate-in`, `attendance.gate-out`, `attendance.absent`,
  `attendance.lesson-absent`. Attendance always calls
  `GuardianNotifier` and never looks at the school's switch — that is
  Core's. Only today is announced; a daily or lesson absence only when the
  status just became that; a lesson absence not when the day already says
  sakit, izin or alpa. Notices are sent after the database transaction.
  Default wording varies with `{a|b|c}` groups (greeting and verb phrase
  only; names and status are never in a group), and `gate-in`,
  `gate-out` and `lesson-absent` are `highVolume` (the school confirms
  before switching them on).
- **Recap and insight**: `Domain/Queries` (`DailyRecap`, `MonthlyRecap`,
  `AttendanceTally`); the rate is hadir + terlambat over all recorded
  days. Reports `attendance-monthly` and `attendance-class` and the figure
  `attendance-rate` / panel `attendance-trend` replace Core's announced
  entries.
- Sample data: `php artisan db:seed --class="Modules\Attendance\Database\Seeders\AttendanceDemoSeeder"`
  (after Core's demo seeder; schools with the module only).
