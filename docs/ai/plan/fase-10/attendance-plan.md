# Absensi sekolah dari data nyata: gerbang, jam pelajaran, QR sekali pakai, dan pemberitahuan WA wali murid

> **Status dokumen:** Selesai
> **Dibuat:** 2026-10-02 · **Diperbarui:** 2026-10-02 · **Branch:** `feat/attendance`

## Context
Modul **Attendance** masih mockup: `modules/Attendance/app/Http/Controllers/AttendanceController.php` mengirim data tetap dari `modules/Attendance/app/Infrastructure/Mock/AttendanceMockData.php` ke tiga halaman (`/absensi`, `/absensi/input`, `/absensi/rekap`). Tidak ada tabel, izin, atau kontrak; route hanya di balik `auth` + `module:attendance`, sehingga menu Absensi ikut tampil untuk siswa.

User meminta dua model absensi — **gerbang** (masuk dan pulang) dan **per jam pelajaran** (hanya slot bel bertipe `Pelajaran`) — dua cara mencatat (**QR sekali pakai** dan **manual**), serta **pemberitahuan WA ke wali murid**.

Dua fase pendahulu sudah selesai dan mengubah draf lama:
- **Fase 8** (`docs/ai/plan/fase-8/school-accounts-plan.md`): siswa login dengan NIS, guru dengan NIP; `students.user_id` / `teachers.user_id` terisi; peran `siswa` ada tanpa izin; `php artisan roles:sync` membawa izin baru ke sekolah lama.
- **Fase 9** (`docs/ai/plan/fase-9/whatsapp-integration-plan.md`): pesan WA dikirim sungguhan lewat antrean. Core sudah punya `NoticeRegistry`, `GuardianNotifier`, tabel `whatsapp_messages`, dan sakelar + isi pesan per jenis di Integrasi › WhatsApp (bawaan mati). Absensi tinggal mendaftarkan jenis dan memanggil notifier.

Yang masih kurang di Core: Attendance hanya boleh memakai `CorePublic` (`deptrac.php`), tetapi belum ada kontrak untuk membaca siswa, kelas, dan jam pelajaran. Fase ini menambahkannya.

## Goal
Setelah ini, siswa membuka "QR Absensi" di HP-nya; petugas memindainya (atau mencari nama/NIS) untuk mencatat masuk dan pulang; guru mengabsen satu kelas per jam pelajaran dengan kelas yang ia ampu ditawarkan lebih dulu; wali kelas/TU menandai sakit, izin, alpa; admin melihat rekap hari ini, rekap bulanan, laporan, dan angka kehadiran dari database; dan tiap kejadian memicu pemberitahuan WA ke wali murid bila sekolah menyalakan jenisnya di Integrasi › WhatsApp. Banner "Tampilan contoh" hilang dari halaman Absensi.

## Batasan masalah

**Dalam cakupan**
- Kontrak baca Core: siswa, kelas (termasuk pengampu dan kelas milik seorang guru), jam pelajaran.
- Absensi harian/gerbang: masuk, pulang, status Hadir/Terlambat/Sakit/Izin/Alpa; batas jam terlambat diatur sekolah.
- Absensi per jam pelajaran: kelas + tanggal + slot `Pelajaran`; mapel opsional.
- QR sekali pakai: halaman "QR Absensi" untuk peran `siswa` (dengan status hari ini), dan halaman pemindai (kamera, scanner USB/tempel token, cari manual).
- Empat jenis pemberitahuan WA terdaftar di `NoticeRegistry`; pemicunya di empat aksi.
- Rekap hari ini, rekap bulanan, laporan `attendance-monthly` dan `attendance-class`, angka `attendance-rate`, panel `attendance-trend`.
- Izin `attendance.*`, gate route dan menu, tes Pest (feature, browser), spec Playwright, pembersihan mock, dokumentasi.

**Di luar cakupan**
- Riwayat kehadiran di halaman siswa — keputusan user: QR + status hari ini saja.
- Kartu QR statis — cadangan tanpa HP adalah input manual.
- Jadwal pelajaran (mapel per jam per kelas) — tidak ada tabelnya; "kelas yang diampu" dibaca dari `teaching_assignments` dan wali kelas, bukan dari jadwal.
- Membatasi guru ke kelasnya sendiri — keputusan user: semua kelas boleh, kelas yang diampu hanya ditawarkan lebih dulu.
- Alpa otomatis di akhir hari dan pengecualian hari libur (`calendar_events`) — target deploy adalah shared hosting tanpa scheduler; siswa tanpa catatan tetap "Belum diabsen".
- Absensi guru/tendik; akun wali murid.
- Mengubah kontrak atau perilaku WhatsApp (sakelar, templat, antrean) — milik fase 9.

**Asumsi**
- Siswa aktif selalu punya `class_id` pada rombel tahun ajaran aktif (`modules/Core/app/Domain/Models/Student.php`).
- `period_slots.day` 1–6 = Senin–Sabtu, tipe pelajaran = `PeriodSlot::LESSON`; hari Minggu tanpa jam pelajaran.
- Cache dibagi antar proses (`CACHE_STORE=database` di `.env.example`), jadi token QR dari request siswa terbaca di request pemindai.
- Kamera pemindai butuh HTTPS atau `localhost`.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Feature module hanya memakai `CorePublic`, `IdentityPublic`, `PlatformPublic`; kontrak tidak membuka model Eloquent — DTO/skalar (sumber: `AGENTS.md` § Public surface, `deptrac.php`).
- Tanpa FK selain `tenant_id`, juga antar tabel satu modul; relasi = kolom polos berindeks (sumber: `tests/Architecture/ModularMonolithTest.php`).
- Data tenant lewat Eloquent + `BelongsToTenant`, bukan `DB::table()`; tanggal dan jam memakai `TenantContext::timezone()`; seeder tanpa `WithoutModelEvents` (sumber: `AGENTS.md` § Tenancy traps).
- Model baru pemakai `BelongsToTenant` ditambahkan ke `deptrac.baseline.yaml` (satu-satunya alasan baseline tumbuh).
- Rate limit di controller dengan tenant id di kuncinya, bukan middleware `throttle:` (sumber: `AGENTS.md` § Identity).
- Modul lain tidak mengimpor `User`; `recorded_by` kolom polos (sumber: `modules/Identity/CONTRACT.md`).
- Tes yang memicu pemberitahuan memakai `Http::fake()` + `Http::preventStrayRequests()`; gateway sungguhan tidak dipanggil (sumber: `AGENTS.md` § WhatsApp per school).
- UI dasar dari `modules/Shared/resources/js/components/ui`; URL dari Wayfinder (sumber: `.ai/rules/js.md`). `npx tsc --noEmit` tidak mencakup halaman modul — periksa dengan konfigurasi sementara yang memuat `modules/**/resources/js/**/*`.
- Aktifkan skill `modular-monolith`, `laravel-best-practices`, `inertia-react-development`, `wayfinder-development`, `writing-tests`, `testing-best-practices` saat menyentuh bagiannya.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-02 | QR = kode sekali pakai yang dibuat halaman siswa, dipindai pihak sekolah | user |
| 2026-10-02 | Cadangan tanpa HP = absensi manual saja, tanpa kartu statis | user |
| 2026-10-02 | Boleh menambah dua paket npm: `qrcode.react` (QR sebagai SVG) dan `qr-scanner` (kamera) | user |
| 2026-10-02 | Pemicu WA: masuk gerbang, pulang gerbang, tidak hadir harian, alpa di jam pelajaran — masing-masing bisa dimatikan sekolah | user |
| 2026-10-02 | Ada status Terlambat; batas jam diatur sekolah | user |
| 2026-10-02 | Absensi jam = kelas + slot `Pelajaran`; mapel opsional | user |
| 2026-10-02 | Guru boleh mengabsen semua kelas; kelas yang ia ampu ditawarkan lebih dulu dan mapelnya terisi otomatis | user (revisi) |
| 2026-10-02 | Halaman siswa: QR + status hari ini, tanpa riwayat | user (revisi) |
| 2026-10-02 | Semua tahap dikerjakan berurutan tanpa berhenti di antaranya | user (revisi) |
| 2026-10-02 | Empat jenis pemberitahuan: `attendance.gate-in`, `attendance.gate-out`, `attendance.absent`, `attendance.lesson-absent`. Sakelar dan isi pesan diatur sekolah di Integrasi › WhatsApp; Absensi tidak punya sakelar sendiri dan selalu memanggil notifier | hasil fase 9 |
| 2026-10-02 | Halaman QR digate izin baru `attendance.qr.show` (peran `siswa`) **dan** akun harus tertaut ke siswa aktif; mendapat entri sidebar sendiri | hasil fase 8 |
| 2026-10-02 | Token QR di `TenantCache` (TTL 60 detik, satu token hidup per siswa, dihapus saat dipakai) — tanpa tabel | hasil desain |
| 2026-10-02 | Pemberitahuan hanya untuk catatan bertanggal hari ini; tidak hadir harian dan alpa jam hanya saat status **berubah** ke nilai itu; alpa jam dilewati bila status harian sudah sakit/izin/alpa | hasil desain |
| 2026-10-02 | Pulang tanpa catatan masuk ditolak; masuk/pulang kedua kali ditolak dengan jam yang sudah tercatat | hasil desain |
| 2026-10-02 | Rekap dan laporan mengelompokkan per bulan di PHP (bukan fungsi tanggal SQL) agar sama di semua driver | hasil desain |

## Desain

### Core — permukaan publik baru (`modules/Core/app/Contracts`)
- `StudentDirectory` — `find(int $id): ?StudentRecord`, `findByUserId(int $userId): ?StudentRecord`, `many(array $ids): array<int, StudentRecord>`, `ofClass(int $classId): list<StudentRecord>` (aktif, urut nama), `search(string $term, int $limit = 10): list<StudentRecord>` (aktif; nama atau NIS).
- `ClassDirectory` — `ofActiveYear(): list<ClassRecord>`, `ofYear(int $academicYearId): list<ClassRecord>`, `find(int $id): ?ClassRecord`, `subjectsOf(int $classId): list<ClassSubject>` (dari `teaching_assignments`), `idsTaughtBy(int $userId): list<int>` (kelas tahun aktif tempat guru yang akunnya `$userId` menjadi pengampu atau wali kelas; kosong bila akun tidak tertaut ke guru).
- `BellSchedule` — `slotsOn(int $day): list<BellSlot>`, `find(int $slotId): ?BellSlot`.
- `DTOs/StudentRecord` (`id`, `name`, `nis`, `?classId`, `?className`, `active`) — tanpa nomor wali; `DTOs/ClassRecord` (`id`, `name`, `academicYearId`, `?homeroomName`, `studentCount`); `DTOs/ClassSubject` (`subjectId`, `subjectName`, `teacherId`, `teacherName`, `?teacherUserId`); `DTOs/BellSlot` (`id`, `day`, `startsAt`, `endsAt` sebagai `H:i`, `type`, `isLesson`). Semua `final readonly`.
- `GuardianNotifier`, `NoticeRegistry`, `ReportRegistry`, `StatisticsRegistry` (sudah ada) dipakai apa adanya.

### Core — internal
- `Infrastructure/Directory/{EloquentStudentDirectory,EloquentClassDirectory,EloquentBellSchedule}.php` (baru), di-bind di `Infrastructure/Providers/CoreServiceProvider.php::register()`.
- `config/notices.php` — hapus dua entri `attendance.*` (jenisnya kini didaftarkan Attendance; `attendance.gate` diganti dua kunci baru). `upcoming()` di `Infrastructure/Whatsapp/DefaultNoticeRegistry.php` menyaring menurut kunci yang terdaftar secara global, jadi entri lama akan tampil selamanya bila dibiarkan.
- Tes Core yang memakai kunci `attendance.absent` sebagai jenis tiruan dan menegaskan daftar "Segera hadir" (`modules/Core/tests/Feature/GuardianNotifierTest.php`, `tests/Feature/Support/notices.php`) dipindah ke kunci netral (`uji.absent`) dan ekspektasinya disesuaikan — perubahan perilaku yang disengaja.

### Attendance — tabel (`modules/Attendance/database/migrations`, semua baru, `tenant_id`)
- `attendance_settings` — satu baris per sekolah: `late_after` (time, bawaan `07:00`); unique `tenant_id`. Migrasi: `0007_01_01_000000_create_daily_attendance_tables.php` dan `0007_01_01_000001_create_lesson_attendance_tables.php`.
- `daily_attendances` — `student_id`, `class_id` (kelas saat dicatat), `date`, `status`, `checked_in_at`, `checked_out_at` (nullable), `check_in_method`, `check_out_method` (nullable), `note` (nullable), `recorded_by`; unique `(tenant_id, student_id, date)`, indeks `(tenant_id, date, class_id)`.
- `lesson_sessions` — `class_id`, `date`, `period_slot_id`, `start_time`, `end_time` (salinan slot), `subject_id` dan `teacher_id` (nullable), `recorded_by`; unique `(tenant_id, class_id, date, period_slot_id)`.
- `lesson_attendances` — `lesson_session_id`, `student_id`, `status`, `method`, `scanned_at` (nullable); unique `(lesson_session_id, student_id)`.

### Attendance — internal (`modules/Attendance/app`)
- `Domain/Enums/AttendanceStatus` (`Present`, `Late`, `Sick`, `Permit`, `Absent`; `Late` hanya harian; `countsAsPresent()`), `Domain/Enums/RecordMethod` (`Qr`, `Manual`).
- `Domain/Models/{AttendanceSetting,DailyAttendance,LessonSession,LessonAttendance}.php` + factory. `AttendanceSetting::current()` membuat baris bawaan bila belum ada (pola `SchoolProfile::current()` di Core).
- `Domain/Actions`:
  - `SaveDailyAttendance` — satu kelas, satu tanggal (tidak di masa depan), `student_id → status, note`; hanya siswa kelas itu; upsert; tidak menghapus jam masuk/pulang.
  - `RecordGateCheckIn` — siswa aktif; jam ≤ `late_after` → `Present`, selebihnya `Late`; menimpa sakit/izin/alpa hari itu; masuk kedua kali → `AttendanceException`.
  - `RecordGateCheckOut` — butuh catatan masuk hari itu; pulang kedua kali ditolak.
  - `SaveLessonAttendance` — kelas tahun aktif, slot `isLesson` yang harinya cocok dengan tanggal, mapel (bila diisi) harus ada di `subjectsOf`; upsert sesi + baris siswa.
  - `MarkLessonPresence` — satu siswa hasil pindai/manual; harus anggota kelas itu; membuat sesi bila belum ada.
  - `SaveAttendanceSettings`.
- `Domain/Qr/QrTokens` — `issue(int $studentId): array{token, expiresIn}` dan `consume(string $token): ?int` di atas `modules/Platform/app/Contracts/TenantCache.php`. Dua kunci: hash token → id siswa, dan id siswa → hash token yang berlaku; menerbitkan token baru mematikan yang lama; `consume` membaca, menghapus, lalu memastikan hash itu masih milik siswa tersebut. Store `database` tidak punya ambil-hapus atomik: pindai ganda yang berbarengan tetap ditolak oleh aturan "masuk/pulang kedua kali" dan indeks unik.
- `Domain/Notifications/AttendanceNotices` — `kinds(): list<NoticeKind>` (empat jenis di bawah) dan satu method per pemicu yang memeriksa aturan "hanya hari ini / hanya saat berubah" lalu memanggil `GuardianNotifier::notify()` setelah transaksi commit (`DB::afterCommit`). Tidak memeriksa sakelar; itu tugas Core.

| Kunci | Judul | Variabel jenis (contoh) |
| --- | --- | --- |
| `attendance.gate-in` | Siswa masuk sekolah | `tanggal`, `jam`, `status` (Hadir/Terlambat) |
| `attendance.gate-out` | Siswa pulang sekolah | `tanggal`, `jam` |
| `attendance.absent` | Siswa tidak hadir | `tanggal`, `status` (Sakit/Izin/Alpa), `keterangan` |
| `attendance.lesson-absent` | Siswa alpa di jam pelajaran | `tanggal`, `jam_pelajaran`, `mapel` |

  `{nama_siswa}`, `{nama_wali}`, `{nama_sekolah}` diisi Core. Templat bawaan satu kalimat per jenis, ditulis di `AttendanceNotices`.
- `Domain/Support/{SchoolClock,LessonSlots}.php` (jam sekolah; aturan kelas/hari/slot yang dipakai dua aksi jam pelajaran) dan `Domain/Queries/{DailyRecap,MonthlyRecap,AttendanceTally,ClassChoices}.php` (rekap dan pilihan kelas).
- `Domain/Reports/{MonthlyAttendanceReport,ClassAttendanceReport}.php` (key `attendance-monthly`, `attendance-class`, izin `attendance.view`) dan `Domain/Statistics/AttendanceStatistics.php` (figure `attendance-rate`, panel `attendance-trend` 6 bulan) — key yang sama menggantikan placeholder di `modules/Core/config/insight.php`. Pola: `modules/Core/app/Domain/Reports/StudentListReport.php`.
- Controller, satu per halaman, tipis di atas Action, FormRequest per tulis: `OverviewController`, `DailyInputController`, `LessonAttendanceController`, `ScanController`, `MonthlyRecapController`, `SettingsController`, `StudentQrController`. `AttendanceController` dan `AttendanceMockData` dihapus di Tahap 5.
- `Infrastructure/Providers/AttendanceServiceProvider.php`: `loadMigrationsFrom`, `mergeConfigFrom` (`permission_labels`), izin lewat `PermissionRegistry`, empat `NoticeKind`, laporan + statistik, menu.

**Izin** (`attendance.*`; nama ditambahkan ke `modules/Identity/config/roles.php`; sekolah lama lewat `php artisan roles:sync`)
| Izin | Arti | admin-sekolah | guru | staf-tu | siswa |
| --- | --- | --- | --- | --- | --- |
| `attendance.view` | rekap, laporan | ✓ | ✓ | ✓ | |
| `attendance.daily.record` | gerbang (pindai/manual) dan input harian | ✓ | ✓ | ✓ | |
| `attendance.lesson.record` | absensi jam pelajaran | ✓ | ✓ | | |
| `attendance.settings.manage` | pengaturan absensi | ✓ | | | |
| `attendance.qr.show` | QR absensi milik sendiri | | | | ✓ |

**Menu** (`TenantNavigation`, izin per anak): "Absensi" → Rekap Hari Ini (`view`), Input Absensi (`daily.record`), Jam Pelajaran (`lesson.record`), Pindai QR (`daily.record`), Rekap Bulanan (`view`), Pengaturan (`settings.manage`). Entri terpisah "QR Absensi" (`attendance.qr.show`, route `attendance.my-qr`) — satu-satunya entri Absensi yang dilihat siswa.

**Route** — `modules/Attendance/routes/web.php`, grup `web` + `auth` + `module:attendance`, prefix `absensi`, nama `attendance.`
| Method & URL | Nama | Izin |
| --- | --- | --- |
| `GET /` | `overview` (tetap) | `attendance.view` |
| `GET input`, `PUT input` | `input` (tetap), `input.update` | `attendance.daily.record` |
| `GET jam-pelajaran`, `PUT jam-pelajaran` | `lessons`, `lessons.update` | `attendance.lesson.record` |
| `GET pindai` | `scan` | salah satu dari dua izin catat |
| `POST pindai` (JSON) | `scan.store` | menurut mode: gerbang → `daily.record`, jam → `lesson.record` |
| `GET pindai/siswa?q=` (JSON) | `scan.students` | salah satu dari dua izin catat |
| `GET rekap` | `monthly` (tetap) | `attendance.view` |
| `GET pengaturan`, `PUT pengaturan` | `settings`, `settings.update` | `attendance.settings.manage` |
| `GET qr-saya` | `my-qr` | `attendance.qr.show` + akun tertaut ke siswa aktif (selain itu 403) |
| `POST qr-saya/token` (JSON) | `my-qr.token` | sama; rate limit di controller `attendance-qr:{tenant_id}:{user_id}` |

`POST pindai` menerima `mode` (`gate-in` \| `gate-out` \| `lesson`), salah satu dari `token` atau `student_id`, dan untuk `lesson`: `class_id`, `period_slot_id`. Sukses: nama, NIS, kelas, jam, status. Token kedaluwarsa/terpakai/tak dikenal, siswa salah kelas, atau catatan ganda → 422 dengan pesan.

**Halaman** (`modules/Attendance/resources/js/Pages/Attendance/`)
- `Overview.tsx` (ada) — props tetap + kartu Terlambat; `?tanggal=`; kelas tahun aktif dari `ClassDirectory`.
- `Input.tsx` (ada) — kelas dan tanggal (reload Inertia), status per siswa termasuk Terlambat dan catatan, simpan lewat `useForm`.
- `Lessons.tsx` (baru) — tanggal, kelas, slot pelajaran hari itu, mapel opsional. Pilihan kelas dibagi dua kelompok: "Kelas Anda" (`idsTaughtBy(Auth::id())`) lalu "Kelas lain"; kelas pertama milik guru terpilih otomatis, dan mapel terisi dari `subjectsOf` yang `teacherUserId`-nya sama dengan akun itu. Awalan status mengikuti status harian (sakit/izin/alpa); tombol "Tandai sisanya hadir"; `EmptyState` bila hari itu tanpa slot pelajaran.
- `Scan.tsx` (baru) — pilih mode; kamera (`qr-scanner`), kolom input untuk scanner USB/tempel token, pencarian nama/NIS; hasil terakhir dan daftar pindaian sesi ini; `useHttp`. Mode jam memakai pilihan kelas yang sama dengan `Lessons.tsx`.
- `MyQr.tsx` (baru) — QR (`qrcode.react`) yang diperbarui otomatis sebelum kedaluwarsa, hitung mundur, status hari ini (jam masuk/pulang). Isi QR hanya token.
- `Monthly.tsx` (ada) — kelas + bulan nyata (`?kelas=`, `?bulan=YYYY-MM`), kolom Terlambat; tombol "Unduh rekap" dihapus (unduhan ada di Statistik & Laporan).
- `Settings.tsx` (baru) — batas jam terlambat, dan tautan ke Integrasi › WhatsApp untuk pemberitahuan.
- `Components/AttendancePage.tsx` — banner mock dihapus di Tahap 5; `Components/status.ts` — tambah `late`.

**Dipakai ulang**
- Platform Contracts: `TenantContext`, `TenantCache`, `PermissionRegistry`, `TenantNavigation`, `ModuleRegistry`. Core Contracts: `ReportRegistry`, `StatisticsRegistry`, `NoticeRegistry`, `GuardianNotifier` + DTO-nya. Pola registrasi: `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`. Pola endpoint JSON + `useHttp`: `modules/Core/app/Http/Controllers/ImportController.php`.
- Frontend: `@shared/components/page-parts` (`StatCard`, `Panel`, `OptionSelect`, `DataTable`, `EmptyState`, `PageHeader`), `@shared/components/ui/*`, `TenantShell`.
- Tes: `schoolAs()`, `inSchool()`, `classIn()`, `yearWithSemesters()` di `modules/Core/tests/Feature/Support/helpers.php` (dimuat dengan `require_once`); `attendanceTenant()` di `modules/Attendance/tests/Feature/AttendancePagesTest.php`; `schoolMemberSignsIn()` di `tests/Browser/Support/school.php`; factory Core (`StudentFactory`, `ClassGroupFactory`, `PeriodSlotFactory`, `TeachingAssignmentFactory`, `TeacherFactory`).

### Identity / Platform
- Identity: hanya `modules/Identity/config/roles.php` (nama izin baru). Platform: tidak berubah.

### Alur
1. Siswa login (NIS) → menu "QR Absensi"; QR berganti sendiri tiap ±45 detik.
2. Petugas membuka Pindai, mode **Masuk**, mengarahkan kamera ke HP siswa → nama, kelas, jam, Hadir/Terlambat. Siswa tanpa HP dicari lewat nama/NIS.
3. Wali kelas/TU membuka Input Absensi, menandai yang sakit/izin/alpa.
4. Guru membuka Jam Pelajaran: kelasnya sudah terpilih dan mapelnya terisi; ia memilih jam, menandai atau memindai, lalu menyimpan. Ia tetap bisa memilih kelas lain.
5. Saat pulang, petugas memilih **Pulang** dan memindai.
6. Bila admin menyalakan jenisnya di Integrasi › WhatsApp, tiap kejadian mengirim pesan ke wali murid dan tercatat di riwayat.
7. Admin melihat Rekap Hari Ini, Rekap Bulanan, serta laporan dan angka kehadiran di Statistik & Laporan.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Keputusan user: semua tahap dikerjakan berurutan tanpa berhenti; tiap tahap tetap ditandai ✅ hanya setelah buktinya lulus.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Fondasi: dokumen plan, branch, kontrak baca Core, izin Absensi, gate route dan menu | ✅ | Kontrak `StudentDirectory`, `ClassDirectory`, `BellSchedule` + empat DTO dan implementasi Eloquent di Core; lima izin `attendance.*` dengan label; semua route Absensi bergate izin; menu per anak dan entri "QR Absensi" untuk siswa. `SchoolDirectoryTest` (9 kasus), `AttendanceAccessTest` (29 kasus). |
| 1 | Absensi harian manual: pengaturan, Input, Rekap Hari Ini | ✅ | Tabel `attendance_settings` + `daily_attendances`; `SaveDailyAttendance`, `SaveAttendanceSettings`; halaman Input, Rekap Hari Ini (dengan pilihan tanggal) dan Pengaturan dari database. `DailyAttendanceTest` (21 kasus), `AttendanceSettingsTest` (9 kasus). |
| 2 | Gerbang + QR sekali pakai: halaman QR siswa, pemindai, masuk/pulang | ✅ | `QrTokens` di `TenantCache`; `RecordGateCheckIn` / `RecordGateCheckOut`; halaman "QR Absensi" dan "Pindai QR" (kamera, kolom kode, cari nama/NIS). `qr-scanner` dan worker-nya ikut terbundel oleh `npm run build`. `QrTokenTest` (4 kasus), `GateAttendanceTest` (28 kasus). Kamera belum dicoba di perangkat sungguhan. |
| 3 | Absensi per jam pelajaran (manual + pindai), kelas yang diampu ditawarkan dulu | ✅ | Tabel `lesson_sessions` + `lesson_attendances`; `SaveLessonAttendance`, `MarkLessonPresence`; halaman Jam Pelajaran dengan "Kelas Anda" lebih dulu dan mapel guru terpilih; mode jam pelajaran di pemindai. `LessonAttendanceTest` (22 kasus). |
| 4 | Pemberitahuan WA: empat jenis terdaftar, empat pemicu | ✅ | `AttendanceNotices` mendaftarkan `attendance.gate-in`, `.gate-out`, `.absent`, `.lesson-absent` dan dipanggil dari aksi setelah transaksi. Entri `attendance.*` dihapus dari `modules/Core/config/notices.php`; jenis tiruan tes Core pindah ke `uji.absent`. `AttendanceNotificationTest` (11 kasus). Belum dicoba dengan bot sungguhan. |
| 5 | Rekap bulanan, laporan + statistik, tes browser, pembersihan mock, dokumentasi | ✅ | Rekap Bulanan nyata; laporan `attendance-monthly` dan `attendance-class`; angka `attendance-rate` dan panel `attendance-trend`; `AttendanceDemoSeeder`; `tests/Browser/AttendanceTest.php` (3 skenario); `tests/E2E/student-qr.spec.ts`; mock dan banner dihapus; CONTRACT.md Attendance, Core, Identity, dokumen arsitektur, `AGENTS.md`. `MonthlyRecapTest` (7 kasus), `AttendanceInsightTest` (9 kasus), `AttendanceDemoSeederTest`. |

## Tasks

### Tahap 0 — Fondasi
- [x] Buat branch `feat/attendance` dari `main`; ganti isi `docs/ai/plan/fase-10/attendance-plan.md` dengan dokumen ini, status "Berjalan".
- [x] Kontrak + DTO — `modules/Core/app/Contracts/{StudentDirectory,ClassDirectory,BellSchedule}.php`, `modules/Core/app/Contracts/DTOs/{StudentRecord,ClassRecord,ClassSubject,BellSlot}.php` (baru).
- [x] Implementasi + binding — `modules/Core/app/Infrastructure/Directory/` (baru), `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`.
- [x] Izin `attendance.*` + label — `modules/Attendance/app/Infrastructure/Providers/AttendanceServiceProvider.php`, `modules/Attendance/config/permission_labels.php` (baru), `modules/Identity/config/roles.php`.
- [x] Gate route dan menu yang ada (halaman masih mock) — `modules/Attendance/routes/web.php`, provider.
- [x] Tes — `modules/Core/tests/Feature/SchoolDirectoryTest.php` (baru): hanya siswa aktif, urutan, pencarian nama/NIS, `findByUserId`, kelas tahun aktif vs tahun lain, `subjectsOf` dengan `teacherUserId`, `idsTaughtBy` (pengampu, wali kelas, akun tak tertaut → kosong, kelas tahun lama tidak ikut), `slotsOn` + `isLesson`, isolasi tenant. `modules/Attendance/tests/Feature/AttendanceAccessTest.php` (baru): tiap peran per halaman, siswa 403 dan tanpa menu Absensi, modul mati 403. Pindahkan `attendanceTenant()` ke `modules/Attendance/tests/Feature/Support/helpers.php` (baru). Sesuaikan ekspektasi izin peran di `modules/Identity/tests/Feature/DefaultRolesTest.php` dan tes navigasi yang ada.

**Selesai bila:** Attendance bisa membaca siswa, kelas, dan jam lewat kontrak Core, dan halaman Absensi mengikuti izin. Bukti: `php artisan test --compact modules/Core modules/Attendance modules/Identity tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 1 — Absensi harian manual
- [x] Migrasi `attendance_settings`, `daily_attendances`; `loadMigrationsFrom` — `modules/Attendance/database/migrations/` (baru).
- [x] Enum, model, factory; entri `deptrac.baseline.yaml` — `modules/Attendance/app/Domain/{Enums,Models}/`, `modules/Attendance/database/factories/` (baru).
- [x] `SaveDailyAttendance`, `SaveAttendanceSettings` + FormRequest — `modules/Attendance/app/Domain/Actions/`, `modules/Attendance/app/Http/Requests/` (baru).
- [x] `OverviewController`, `DailyInputController`, `SettingsController`; route `input.update`, `settings`, `settings.update`; menu "Pengaturan".
- [x] Halaman `Overview.tsx`, `Input.tsx` nyata; `Settings.tsx` (baru); `status.ts` + `late`.
- [x] Tes — `modules/Attendance/tests/Feature/DailyAttendanceTest.php` (baru): simpan dan simpan ulang (upsert), siswa kelas lain ditolak, tanggal masa depan ditolak, angka Rekap Hari Ini dan "Belum diabsen", jam masuk tidak terhapus oleh simpan ulang, izin, isolasi tenant. `AttendanceSettingsTest.php` (baru).

**Selesai bila:** status harian satu kelas tersimpan dan Rekap Hari Ini menghitungnya dari database. Bukti: `php artisan test --compact modules/Attendance` → hijau.

### Tahap 2 — Gerbang + QR sekali pakai
- [x] `npm install qrcode.react qr-scanner` — `package.json`.
- [x] `QrTokens` — `modules/Attendance/app/Domain/Qr/QrTokens.php` (baru).
- [x] `RecordGateCheckIn`, `RecordGateCheckOut`, `AttendanceException` — `app/Domain/Actions/`, `app/Domain/Exceptions/` (baru).
- [x] `StudentQrController` (`my-qr`, `my-qr.token`), `ScanController` (`scan`, `scan.store` mode gerbang, `scan.students`) + `ScanRequest`; menu "Pindai QR" dan entri "QR Absensi".
- [x] Halaman `MyQr.tsx`, `Scan.tsx` (baru).
- [x] Tes — `QrTokenTest.php` (baru): token dipakai sekali, kedaluwarsa setelah 60 detik (`travel`), token baru mematikan token lama, token sekolah lain tidak dikenal. `GateAttendanceTest.php` (baru): masuk tepat waktu vs terlambat (`travelTo` pada zona tenant), masuk menimpa alpa, masuk ganda 422, pulang tanpa masuk 422, pulang ganda 422, manual lewat `student_id`, siswa nonaktif ditolak; `qr-saya`: siswa berakun 200, admin (tanpa izin) 403, akun `siswa` yang siswanya tidak aktif 403, rate limit token, entri sidebar "QR Absensi" hanya untuk siswa.

**Selesai bila:** satu token dari halaman QR mencatat masuk tepat satu kali, dan pulang tercatat setelahnya. Bukti: `php artisan test --compact modules/Attendance` → hijau; `npm run build` → sukses.

### Tahap 3 — Absensi per jam pelajaran
- [x] Migrasi `lesson_sessions`, `lesson_attendances`; model + factory; baseline.
- [x] `SaveLessonAttendance`, `MarkLessonPresence` — `app/Domain/Actions/` (baru).
- [x] `LessonAttendanceController` (`lessons`, `lessons.update`; props `classes` dengan penanda `mine`, mapel bawaan); mode `lesson` di `ScanController`; menu "Jam Pelajaran".
- [x] Halaman `Lessons.tsx` (baru); mode jam di `Scan.tsx`.
- [x] Tes — `LessonAttendanceTest.php` (baru): hanya slot `isLesson`, slot hari lain ditolak, kelas tahun lama ditolak, mapel di luar pengampu ditolak, sesi unik per kelas+tanggal+slot (simpan ulang memperbarui), awalan dari status harian, pindai siswa kelas lain 422, pindai membuat sesi, guru berakun tertaut melihat kelasnya bertanda `mine` dan mapelnya terpilih, guru tetap bisa menyimpan kelas yang bukan miliknya, admin tanpa tautan guru tidak punya kelas `mine`, izin (`staf-tu` 403), isolasi tenant.

**Selesai bila:** guru menyimpan absensi satu kelas untuk satu jam pelajaran, lewat daftar maupun pindai, dengan kelasnya ditawarkan lebih dulu. Bukti: `php artisan test --compact modules/Attendance` → hijau.

### Tahap 4 — Pemberitahuan WA
- [x] `AttendanceNotices` (empat `NoticeKind` + pemicu) — `modules/Attendance/app/Domain/Notifications/AttendanceNotices.php` (baru); daftarkan jenis di provider; panggil dari `RecordGateCheckIn`, `RecordGateCheckOut`, `SaveDailyAttendance`, `SaveLessonAttendance`, `MarkLessonPresence`.
- [x] Hapus entri `attendance.*` — `modules/Core/config/notices.php`.
- [x] Pindahkan jenis tiruan tes Core ke kunci netral dan sesuaikan ekspektasi daftar — `modules/Core/tests/Feature/GuardianNotifierTest.php`, `modules/Core/tests/Feature/Support/notices.php`.
- [x] Tes — `modules/Attendance/tests/Feature/AttendanceNotificationTest.php` (baru; `Http::fake()` + `Http::preventStrayRequests()`, sakelar dinyalakan lewat factory/`SaveNoticeSetting` Core): empat jenis tampil di Integrasi › WhatsApp untuk sekolah bermodul Absensi dan tidak untuk yang tanpa modul; tiap pemicu membuat satu baris `whatsapp_messages` dengan variabel terisi; jenis mati → tanpa baris; tanggal lampau tidak; simpan ulang tidak menggandakan; alpa jam dilewati bila harian sudah tidak hadir; siswa tanpa nomor wali → `no_recipient`.

**Selesai bila:** dengan jenisnya menyala, tiap kejadian menambah satu baris di riwayat Integrasi › WhatsApp. Bukti: `php artisan test --compact modules/Core modules/Attendance tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 5 — Rekap, laporan, penutup
- [x] `MonthlyRecapController` + `Monthly.tsx` nyata.
- [x] `MonthlyAttendanceReport`, `ClassAttendanceReport`, `AttendanceStatistics` + pendaftaran — `modules/Attendance/app/Domain/{Reports,Statistics}/` (baru), provider.
- [x] Seeder contoh — `modules/Attendance/database/seeders/AttendanceDemoSeeder.php` (baru; tanpa `WithoutModelEvents`).
- [x] Tes — `MonthlyRecapTest.php`, `AttendanceInsightTest.php`, `AttendanceDemoSeederTest.php` (baru): hitungan per status, persen dengan Terlambat dihitung hadir, bulan kosong, placeholder "Segera hadir" hilang, laporan tidak ada bila modul mati, isolasi tenant.
- [x] Tes browser — `tests/Browser/AttendanceTest.php` (baru; pola `tests/Browser/InsightTest.php`): input harian tersimpan dan muncul di rekap; token dari `QrTokens` ditempel ke kolom input pemindai → nama siswa muncul; guru melihat kelasnya terpilih di Jam Pelajaran; `assertNoJavaScriptErrors()`. Spec `tests/E2E/student-qr.spec.ts` (baru; pola `tests/E2E/school-accounts.spec.ts`): siswa login, halaman QR menampilkan gambar QR dan menggantinya, lalu admin di sesi lain mencatat kode itu tepat sekali.
- [x] Bersihkan: hapus `AttendanceController.php`, `AttendanceMockData.php`, banner mock; `npm run build`.
- [x] Dokumentasi: `modules/Attendance/CONTRACT.md`, `modules/Core/CONTRACT.md`, `modules/Identity/CONTRACT.md` (peran `siswa` kini punya satu izin), `docs/architecture/modular-monolith.md`, salinan `AGENTS.md`, memori `project_mockup_first.md`.

**Selesai bila:** rekap, laporan, dan angka kehadiran berasal dari database dan tidak ada sisa mock Absensi. Bukti: `php artisan test --compact modules/Attendance modules/Core tests/Architecture` → hijau; `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/AttendanceTest.php` → hijau; `npx playwright test student-qr` → hijau; `composer deptrac` → 0 pelanggaran; `npm run build` → sukses.

## Batas tindakan
**Selalu**
- `vendor/bin/pint --dirty --format agent` dan tes modul yang disentuh sebelum menandai tahap ✅.
- Perbarui tabel status, centang task, catat penyimpangan di Log keputusan.
- `Http::preventStrayRequests()` di tes yang memicu pemberitahuan.

**Tanya dulu**
- Menambah dependensi selain `qrcode.react` dan `qr-scanner`, atau menggantinya.
- Mengubah signature kontrak Core yang sudah ada (`GuardianNotifier`, `NoticeRegistry`, registry Insight), atau menambah nomor wali ke DTO.
- Mengubah `deptrac.php`, kode Platform, atau kode Identity selain `config/roles.php`.
- Menambah scheduler/cron baru (target deploy: shared hosting, hanya cron antrean).

**Jangan**
- Mengimpor model Core atau `User` dari Attendance; mengimpor Attendance dari Core.
- FK selain `tenant_id`; `DB::table()` untuk data tenant; `WithoutModelEvents` di seeder.
- Menaruh NIS/nama di isi QR; menyimpan token di database.
- Memanggil gateway WhatsApp sungguhan; memeriksa sakelar pemberitahuan dari Attendance.
- Menghapus tes; mengubah nama route `attendance.overview`/`input`/`monthly`; commit/push tanpa diminta.

## Pengujian
- **Tahap 0:** kontrak direktori Core; akses per peran dan flag modul.
- **Tahap 1:** simpan harian, validasi, rekap hari ini, pengaturan, isolasi tenant.
- **Tahap 2:** siklus hidup token; masuk/pulang, terlambat, ganda, manual; akses halaman QR.
- **Tahap 3:** aturan slot/kelas/mapel, sesi unik, pindai mode jam, kelas yang ditawarkan.
- **Tahap 4:** pendaftaran jenis, variabel, anti-ganda, aturan hari ini.
- **Tahap 5:** rekap, laporan, statistik; satu alur browser; satu spec Playwright.

Factory untuk semua data; jam diuji dengan `travelTo` pada zona waktu tenant. Kamera tidak diuji otomatis — kolom input pemindai adalah jalur ujinya. Setelah Tahap 5, minta user menjalankan suite penuh `php artisan test --compact`.

## Verifikasi end-to-end
1. `php artisan migrate`, `php artisan roles:sync`; worker antrean berjalan (`composer run dev`).
2. Login sebagai siswa (NIS) → hanya menu Beranda dan "QR Absensi"; `/absensi/qr-saya` (URL dari tool `get-absolute-url`) menampilkan QR yang berganti; `/absensi` → 403.
3. Perangkat lain, login admin → Absensi › Pindai QR, mode Masuk, pindai QR → nama dan status muncul; pindai QR yang sama lagi → ditolak.
4. Cari siswa lain lewat nama → tercatat manual. Mode Pulang → jam pulang terisi; halaman QR siswa menampilkan jam masuk dan pulang.
5. Login sebagai guru (NIP) → Jam Pelajaran: kelasnya terpilih, mapel terisi; simpan satu sesi dengan satu alpa. Pengaturan → 403.
6. Admin → Integrasi › WhatsApp: empat jenis Absensi tampil dan mati. Nyalakan "Siswa masuk sekolah", ulangi langkah 3 untuk siswa lain → satu baris di riwayat.
7. Rekap Hari Ini, Rekap Bulanan, Statistik & Laporan → angka cocok; CSV "Rekap Kehadiran Bulanan" terunduh.
8. Sekolah lain: data terpisah. `composer deptrac` → bersih.

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-02 (setelah selesai) — Keputusan user: untuk sementara **semua sekolah mendapat Absensi**; pemilahan per paket belakangan. Paket bawaan di `modules/Platform/database/seeders/BillingMasterDataSeeder.php` kini memuat `attendance`, dan migrasi `modules/Platform/database/migrations/0008_01_01_000000_include_attendance_for_every_school.php` menambahkannya ke paket dan sekolah yang sudah ada (paket mematikan modul yang tidak didaftarnya, jadi menyalakan per sekolah saja tidak cukup). `modules/Platform/tests/Feature/AttendanceForEverySchoolTest.php` (3 kasus). Sekolah dev `sekolah-b` di `PlatformDevSeeder` sengaja tetap hanya `core`.
- 2026-10-02 (setelah selesai) — `tests/E2E/onboarding.spec.ts` diberi batas waktu 90 detik: spec itu memang berjalan 26–30 detik, tepat di batas bawaan 30 detik, dan sesekali gagal di baris penutup setelah semua pemeriksaannya lulus.
- 2026-10-02 (Tahap 5) — Spec Playwright bernama `student-qr.spec.ts`, bukan `attendance.spec.ts`: spec berjalan menurut abjad dan `import.spec.ts` butuh `sekolah-a` tanpa kelas, sedangkan spec ini mengisi data contoh. Spec juga membuktikan hal yang tidak bisa dibuktikan suite browser satu-proses: kode yang diminta halaman siswa terbaca oleh pemindai di sesi dan request lain, dan hanya berlaku sekali.
- 2026-10-02 (Tahap 5) — Tes Core tentang entri "Segera hadir" dipindah ke kunci yang masih diumumkan (`ppdb-applicants`, `ppdb-result`) karena kunci laporan kehadiran kini terdaftar: `InsightRegistryTest`, `InsightReportsTest`, kelas tiruan di `tests/Feature/Support/insight.php`; `tests/Browser/InsightTest.php` dan `tests/E2E/insight.spec.ts` ikut disesuaikan. Perubahan perilaku yang disengaja.
- 2026-10-02 (Tahap 5) — Persen kehadiran = (hadir + terlambat) dibagi seluruh catatan harian; tanpa catatan ditampilkan "—", bukan 0%. Tidak ada tabel hari sekolah, jadi hari yang tidak diabsen tidak masuk hitungan.
- 2026-10-02 (Tahap 5) — `AttendanceDemoSeeder` hanya mengisi sekolah yang modul Absensinya aktif dan belum punya catatan; 20 hari sekolah terakhir sebelum hari ini, hasilnya sama di tiap database (tanpa angka acak).
- 2026-10-02 (Tahap 4) — Pemberitahuan dipanggil setelah transaksi selesai (kode biasa sesudah `DB::transaction`), bukan `DB::afterCommit`; hasilnya sama dan tidak bergantung pada cara tes membungkus transaksi. Templat bawaan ditulis di `AttendanceNotices::kinds()`.
- 2026-10-02 (Tahap 4) — Pendaftaran jenis pemberitahuan dilakukan sejak Tahap 1: aksi harian dan gerbang sudah memanggil notifier, dan jenis yang tidak terdaftar ditolak Core. Tes pemberitahuannya tetap ditulis di Tahap 4.
- 2026-10-02 (Tahap 3) — `lesson_sessions.subject_id` nullable (mapel opsional). Simpan daftar setelah pindai tidak mengubah baris hasil pindai yang statusnya tetap hadir. Pindai siswa yang sudah hadir ditolak supaya pindai ganda terlihat.
- 2026-10-02 (Tahap 3) — Jam yang dibuka: yang diminta, lalu jam yang sedang berjalan (hanya hari ini), lalu jam pertama. Kelas yang dibuka: yang diminta, lalu kelas pertama milik guru, lalu kelas pertama.
- 2026-10-02 (Tahap 2) — Penolakan pemindai dijawab 422 berbentuk galat validasi (`errors.scan`), supaya halaman membacanya dengan cara yang sama seperti galat isian. Kode QR tetap hangus walau pencatatannya ditolak.
- 2026-10-02 (Tahap 2) — Permintaan kode dibatasi 20 per menit per akun (halaman meminta tiap 45 detik). Kode berupa 40 karakter acak; yang disimpan di cache adalah hash-nya.
- 2026-10-02 (Tahap 2) — Menu "Pindai QR" di balik `attendance.daily.record`; halamannya sendiri terbuka bagi pemegang salah satu izin catat, dan mode yang tampil mengikuti izin.
- 2026-10-02 (Tahap 1) — Kolom `date` disimpan dan dibaca sebagai teks `Y-m-d` (tanpa cast tanggal): cast Eloquent menulis `Y-m-d 00:00:00` di SQLite sehingga `where('date', ...)` berbeda antar driver. Cap waktu gerbang diubah ke zona waktu aplikasi sebelum disimpan (`SchoolClock::stored()`).
- 2026-10-02 (Tahap 1) — Di Input Absensi, siswa tanpa catatan ditampilkan sebagai Hadir dan ikut tersimpan saat tombol simpan ditekan (perilaku mockup dipertahankan); halaman menyebut jumlahnya. Rekap Hari Ini menandai kelas "Sudah diabsen" bila tidak ada siswa aktif tanpa catatan.
- 2026-10-02 (Tahap 0) — Tes "modul mati" dipindah dari `AttendancePagesTest` ke `AttendanceAccessTest` (tidak dihapus); `AttendancePagesTest` kini memeriksa enam halaman. Ekspektasi izin peran diubah di `DefaultRolesTest`, `RolesSyncTest`, `RolesPageTest` (Identity): keempat peran bawaan mendapat izin Absensi.
- 2026-10-02 (Tahap 0) — Halaman React, controller, dan aksi semua tahap ditulis berurutan dalam satu sesi (keputusan user: tanpa berhenti); tiap tahap ditandai ✅ setelah tes tahap itu lulus.
- 2026-10-02 — Draf direvisi setelah fase 8 dan 9: bagian log/pengirim WA dan sakelar `notify_*` dibuang (sudah milik Core); halaman QR memakai peran `siswa` dengan izin dan entri sidebar; guru mendapat kelas yang diampu sebagai pilihan pertama; halaman siswa tanpa riwayat; tahap dijalankan tanpa berhenti.

### Temuan
- (Tahap 2) `qr-scanner` terbundel dengan benar: `npm run build` menghasilkan `qr-scanner.min-*.js` dan `qr-scanner-worker.min-*.js`. Paket dimuat lewat `import()` di dalam efek, jadi tidak ikut render sisi server.
- (Tahap 2) Kunci `TenantCache` memang per sekolah: kode sekolah lain tidak dikenal dan mencobanya di sekolah yang salah tidak menghanguskannya (`QrTokenTest`).
- (Tahap 2) Model di aplikasi ini mengembalikan tanggal sebagai `CarbonImmutable`, bukan `Illuminate\Support\Carbon`: parameter bertipe `Carbon` gagal saat dijalankan. Pakai `CarbonInterface`.
- (Tahap 4) Jenis pemberitahuan dan kunci laporan terdaftar global saat boot. Akibatnya, laporan yang kuncinya sudah didaftarkan modul tidak lagi diumumkan untuk sekolah mana pun: sekolah tanpa modul Absensi tidak melihat kelompok "Kehadiran" sama sekali di Laporan. Angka dan panel berperilaku lain: tetap "Segera hadir" bagi sekolah tanpa modul. Perilaku Core yang sudah ada; dicatat di `modules/Core/CONTRACT.md`.
- (Tahap 5) Paket langganan semula hanya memuat `core` + `identity`, sehingga sekolah baru tidak mendapat Absensi. Sudah diputuskan user: semua paket memuatnya untuk sementara (lihat Log keputusan).
- (Setelah selesai) Bendera modul di-cache di partisi pembacanya: dibaca dari request sekolah → partisi sekolah, dibaca tanpa konteks → partisi pusat. `ModuleFlagManager::enable()` hanya menghapus partisi tempat ia dipanggil. Migrasi di atas memanggilnya dua kali (tanpa dan dengan konteks sekolah) supaya kedua salinan hilang; bug dasarnya di Platform tetap ada (mengubah modul dari konsol provider baru terlihat sekolah paling lama 5 menit kemudian).
- (Setelah selesai) Satu kali jalan suite Playwright gagal di `import.spec.ts` (batas 30 detik saat mengisi form pertama) dan lulus di jalan berikutnya tanpa perubahan; server Vite dev sedang berjalan saat itu. Tidak berulang.
- (Tahap 5) Menu PPDB (masih mockup, belum bergate izin) tetap tampil untuk siswa; dibereskan di fase PPDB.
- (Tahap 5) Saat `npm run dev` berjalan, halaman di suite Playwright dimuat dari server Vite dev. Setelah paket npm baru dipasang, server itu mengoptimalkan ulang dependensi dan satu kali jalan gagal dengan halaman kosong; jalan berikutnya lulus.
- (Tahap 5) `npm run check` melaporkan masalah format di 215 berkas frontend, termasuk berkas baru fase ini; keadaan ini sudah ada sebelumnya (lihat panduan Playwright) dan tidak diubah.

### Hasil akhir
Selesai 2026-10-02 di branch `feat/attendance`, belum di-commit.

- **Yang jadi:** siswa membuka "QR Absensi" dan mendapat kode sekali pakai yang berganti tiap 45 detik; petugas mencatat masuk dan pulang lewat kamera, alat pemindai, atau pencarian nama/NIS; wali kelas/TU mengisi status harian per kelas; guru mengabsen per jam pelajaran dengan kelasnya sendiri ditawarkan lebih dulu; admin mengatur batas jam terlambat dan melihat Rekap Hari Ini, Rekap Bulanan, dua laporan (CSV dan cetak), serta angka dan tren kehadiran. Empat jenis pemberitahuan WA tersedia di Integrasi › WhatsApp (mati sampai sekolah menyalakannya). Tidak ada sisa mock di modul Absensi.
- **Bukti:** `php artisan test --compact` → 1.046 lulus (155 baru); `vendor/bin/pest -c phpunit.e2e.xml` → 23 lulus (3 baru); `npx playwright test` → 8 lulus (1 baru); `vendor/bin/deptrac analyse` → 0 pelanggaran; PHPStan 29 galat, sama dengan sebelum fase ini (tidak ada di berkas baru); pemeriksaan tipe halaman Absensi dengan konfigurasi sementara bersih; `npm run build` sukses.
- **Belum dibuktikan:** kamera pemindai di HP/laptop sungguhan (butuh https); pemberitahuan dengan bot WhatsApp sungguhan (tes memakai antrean dan HTTP tiruan).
- **Langkah user sebelum mencoba:** `php artisan migrate`, `php artisan roles:sync`; untuk data contoh `php artisan db:seed --class="Modules\Attendance\Database\Seeders\AttendanceDemoSeeder"` setelah seeder contoh Core; worker antrean berjalan bila pemberitahuan dinyalakan.
- **Sisa untuk nanti:** memilah paket langganan mana yang tetap memuat Absensi (sekarang semua); alpa otomatis dan hari libur; riwayat kehadiran di halaman siswa; jadwal pelajaran; menu PPDB untuk siswa; berikutnya fase PPDB.
