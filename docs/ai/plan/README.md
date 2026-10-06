# Daftar Plan per Fase

Ringkasan fase yang dulu ada di `AGENTS.md` dipindahkan ke sini supaya `AGENTS.md` tetap ramping. Rincian lengkap: `modules/<Modul>/CONTRACT.md` dan `docs/architecture/modular-monolith.md`.

| Fase | Topik | Folder plan | Rincian |
| --- | --- | --- | --- |
| 3 | Billing & pengguna konsol provider | `fase-3/` | Platform CONTRACT |
| 7 | Statistik & Laporan | `fase-7/` | Core CONTRACT |
| 8 | Akun siswa & guru | `fase-8/` | Identity + Core CONTRACT |
| 9 | WhatsApp per sekolah | `fase-9/` | Platform + Core CONTRACT |
| 10 | Absensi | `fase-10/` | Attendance CONTRACT |
| 11 | PPDB | `fase-11/` | Ppdb CONTRACT |
| 12 | Checklist persiapan sekolah | `fase-12/` | Core CONTRACT |
| 13 | Kelas Saya guru | (tanpa folder) | Attendance + Core CONTRACT |
| — | Kelas Saya siswa | (tanpa folder) | Attendance + Core CONTRACT |
| 14 | Waktu pindai absensi | `fase-14/` | Attendance CONTRACT |
| 15 | Menu guru | `fase-15/` | docs/architecture |

## Arsip: teks asli dari AGENTS.md

### Billing and provider-console users (Fase 3)

Subscription billing (plans/subscriptions/invoices, trial vs subscribed) is
Platform-internal; payment is the always-true stub `PaymentGateway`. School
admins across tenants are managed by Identity's `Console/SchoolAdminController`
on the console host. New Platform contracts: `TenantDirectory`,
`TenantRoles::rolePermissions()`. Details: modules/Platform/CONTRACT.md and
docs/architecture/modular-monolith.md.

### Statistik & Laporan (Fase 7)

Core owns the pages (`/statistik-laporan/*`, behind `core.master.view`) and
Core's first contracts: `ReportRegistry` + `Report` and `StatisticsRegistry`
+ `StatisticsProvider` (DTOs in `Contracts/DTOs`). Every module, Core
included, registers its reports and figures from its own provider; a
feature module never gets imported by Core. Reports return a `ReportTable`
— Core renders CSV and the print view, nothing is stored. "Segera hadir"
placeholders are labels in `modules/Core/config/insight.php`; registering
the same key replaces one. Details: modules/Core/CONTRACT.md.

### Accounts for students and teachers (Fase 8)

`users` has an optional email and a `username` (unique per tenant); login
takes one field `login` (`@` = email, else username: NIS, NIP). Core makes
the accounts through Identity's `AccountProvisioner` and keeps only
`user_id`: students get NIS + birth date (`ddmmyyyy`), teachers NIP + a
random password shown once; both must change it at first login
(`RequirePasswordChange` middleware, `/ganti-kata-sandi`). A student who
leaves has the account deactivated; nothing is deleted. Role `siswa` has
no permissions yet. `php artisan roles:sync` brings existing schools in
line with `modules/Identity/config/roles.php`. Details:
modules/Identity/CONTRACT.md, modules/Core/CONTRACT.md,
docs/architecture/modular-monolith.md.

### WhatsApp per school (Fase 9)

Schools send WhatsApp through the provider's OpenWA gateway. Platform owns
the gateway side (`whatsapp_instances`, console page `/whatsapp`, contract
`WhatsappChannel`: `state`, `request`, `connect`, `disconnect`,
`sendText`); Core owns the school page Integrasi › WhatsApp (behind
`core.integration.manage`), the log `whatsapp_messages`, and the contracts
`NoticeRegistry` + `GuardianNotifier` a feature module uses to notify
guardians (kinds are off until the school switches them on). Flow: school
asks → provider approves (or provider setting `whatsapp.auto_approve`) →
school links by QR → messages go out through the queue. The per-school
session key is encrypted with `OPENWA_CREDENTIALS_KEY` and never reaches
a DTO, a page, a log or an error message; every gateway call is made by
the server. Env: `OPENWA_API_BASE_URL`, `OPENWA_ADMIN_API_KEY`,
`OPENWA_CREDENTIALS_KEY` (values in `.env` only). Tests fake the gateway
(`Http::fake` + `Http::preventStrayRequests`) and never call the real
one. Details: modules/Platform/CONTRACT.md, modules/Core/CONTRACT.md,
docs/architecture/modular-monolith.md.

### Attendance (Fase 10)

The first feature module with real data: gate in/out, daily status
(hadir, terlambat, sakit, izin, alpa) and attendance per lesson, by a
student's one-time QR or by hand. It reads Core only through
`StudentDirectory`, `ClassDirectory` and `BellSchedule` (DTOs; plain ids in
its own tables) and exposes nothing. It registers permissions
`attendance.*`, sidebar entries, four notice kinds (`attendance.gate-in`,
`.gate-out`, `.absent`, `.lesson-absent`), two reports and the attendance
figures through the registries; it never checks the WhatsApp switch. The
QR is a 60-second code in `TenantCache` (no table); the page needs
`attendance.qr.show` and an account linked to an active student. Days are
the school's own `Y-m-d` strings, timestamps go through
`SchoolClock::stored()`, months are grouped in PHP. No timetable (any
teacher may record any class; own classes are offered first) and no
scheduler (nothing marks absence automatically). After a release:
`php artisan migrate` and `php artisan roles:sync`. Details:
modules/Attendance/CONTRACT.md, modules/Core/CONTRACT.md,
docs/architecture/modular-monolith.md.

### PPDB (Fase 11)

Applicants have their own account, separate from every school user: a
central table `ppdb_accounts` (no tenant scope; guard `ppdb`; pages under
`/calon-siswa/*`) owned by Ppdb, with only name, email, password and the
one school joined (`tenant_id`, the sole FK). It is the second public
registration after Platform's school applicants; school users still have
none. An applicant joins a school with the school code (the one typed at
sign-in; link `/calon-siswa/gabung?school=<code>`), every refusal gives
the same message, and after the form is sent the account is locked there
(only the committee's `CancelApplication` frees it). Account pages never
rely on `ResolveTenant`: they run in `TenantContext::run($account->tenant_id)`
and read only the registration with the account's id. Tables
`ppdb_periods`, `ppdb_waves`, `ppdb_paths`, `ppdb_applicants` are tenant-scoped.
Core's `StudentAdmission::admit()` makes the student at re-registration
(no class, no account); permissions `ppdb.view`, `ppdb.applicants.manage`,
`ppdb.selection.manage`, `ppdb.settings.manage`. The decision stays hidden
on the applicant's page until the results are announced; document check
is an always-true stand-in (`DocumentCheck`); results go to the guardian
on WhatsApp through Core's `ContactNotifier` (kind `ppdb.result`, off until
the school switches it on); each period's registration form is built by the school
on PPDB › Formulir (`ppdb_form_fields`: the ten built-in fields, path/name/
gender locked, plus custom text, paragraph, number, date, select,
checkboxes, file and section fields; answers in `ppdb_applicant_answers`;
archiving keeps answers, delete only without answers; a new period copies
the latest; uploads live in `TenantStorage` and download as attachments);
`ppdb` is in no plan yet. After a release: `php artisan migrate` and
`php artisan roles:sync`. Details: modules/Ppdb/CONTRACT.md,
modules/Core/CONTRACT.md, docs/architecture/modular-monolith.md.

### Checklist persiapan sekolah (Fase 12)

Beranda admin sekolah (`core.master.manage`) menampilkan "Persiapan
sekolah": langkah Master Data menurut urutan dependensinya, satu ditandai
berikutnya. Internal Core (`Domain/Queries/SetupChecklist`), tanpa
kontrak dan tanpa tabel: status dihitung dari data sekolah tiap kunjungan
dan kartu hilang setelah semua langkah wajib selesai. Profil dianggap
selesai bila Tingkat sudah ada (di-seed saat profil disimpan); jurusan
wajib hanya untuk jenjang yang memakainya. Rincian:
modules/Core/CONTRACT.md, docs/architecture/modular-monolith.md.

### Kelas Saya guru (Fase 13)

Menu guru "Kelas Saya" (grup "Saya") kini satu pintu: Kelas Aktif,
Jadwal Hari Ini, Absensi Kelas dan Riwayat Absensi; "Absensi Saya" tetap
ada tapi kosong ("Segera hadir"). Kontrak publik baru Core
`TeacherSchedule` (`week`/`onDay`, DTO `ScheduleDay`/`ScheduleLesson`)
memberi Attendance jadwal pelajaran; halaman `/saya/kelas` lama dan entri
menunya dihapus (Jadwal Mengajar tetap). Jam pelajaran menentukan
segalanya: hanya jam yang sedang berlangsung yang bisa diisi di Absensi
Kelas, kartu Jadwal Hari Ini hanya bisa dicentang setelah jamnya selesai
(`lesson_checks`, ikut terisi saat absensi disimpan), dan koreksi di luar
jam lewat Riwayat Absensi. Halaman sekolah (Input harian, Jam Pelajaran
sekolah) kini milik admin/staf-tu; guru tetap bisa Pindai QR. Setelah
rilis: `php artisan migrate`. Rincian: modules/Attendance/CONTRACT.md,
modules/Core/CONTRACT.md, docs/architecture/modular-monolith.md.

### Kelas Saya siswa

Siswa kini punya menu "Kelas Saya" (grup "Saya", dimiliki Attendance): Info
Kelas, Jadwal Pelajaran, Mata Pelajaran & Guru, dan Absensi Saya sebagai anak
menu. Izin baru `attendance.class.view-own` (role `siswa`); kontrak Core baru
`ClassTimetable`. Setelah rilis: `php artisan roles:sync`. Rincian:
modules/Attendance/CONTRACT.md, modules/Core/CONTRACT.md.

### Waktu pindai absensi (Fase 14)

Pindai hanya diterima pada waktunya, menurut jam sekolah (`Domain/Support/ScanWindow`,
internal Attendance, tanpa kontrak). Pelajaran: dari `lesson_scan_early_minutes`
(bawaan 5) sebelum mulai sampai selesai; guru tanpa `attendance.lesson.school`
hanya memindai pelajarannya sendiri yang sedang berjalan. Gerbang (masuk dan
pulang): antara `gate_opens_at` dan `gate_closes_at` (bawaan 05:00-18:00);
pulang sebelum jam pelajaran terakhir selesai = `left_early` ("Pulang awal").
Siswa yang sudah tercatat pulang tidak bisa dipindai ke pelajaran; admin/staf-tu
membatalkan catatan pulang (hari ini saja) di Input Absensi. Input harian
dan Riwayat Absensi tidak terikat jam ini: itu jalur koreksi. Hari libur belum
dikenali. Setelah rilis: `php artisan migrate`. Rincian:
modules/Attendance/CONTRACT.md, docs/architecture/modular-monolith.md,
docs/ai/plan/fase-14/.

### Menu guru (Fase 15)

Menu guru kini: Beranda, Profil Saya, Jadwal Saya (tab Hari Ini dan Minggu
Ini, milik Attendance, `/absensi/jadwal-saya`), Kelas Mengajar (Kelas Aktif,
Absensi Kelas dengan tab Isi Absensi / Koreksi, Pindai QR). "Absensi Saya"
kosong milik guru dan "Jadwal Mengajar" Core dihapus dari menu; menu siswa
tetap "Kelas Saya". Role `guru` tidak lagi punya `core.master.view` dan
`core.academic.view` (tanpa Data Induk dan Statistik sekolah). Setelah
rilis: `php artisan roles:sync`. Rincian: docs/ai/plan/fase-15/,
docs/architecture/modular-monolith.md.
