# Lampiran A — Draf plan Absensi (fase 10, disimpan untuk nanti)

> Disalin apa adanya ke `docs/ai/plan/fase-10/attendance-plan.md` di Tahap 0 fase 8. **Belum disetujui** dan harus direvisi sebelum dieksekusi:
> - Setelah fase 8: "akun siswa di luar cakupan" dihapus; halaman QR memakai peran `siswa`, mendapat izin dan entri sidebar.
>   Fase 8 selesai 2026-10-02 (`docs/ai/plan/fase-8/school-accounts-plan.md`): akun siswa (NIS) dan guru (NIP) ada, `students.user_id` / `teachers.user_id` terisi, peran `siswa` belum punya izin. Menu Absensi masih tampil untuk siswa karena belum bergate izin.
> - Setelah fase 9: Tahap 4 (kontrak + log WA di Core) pindah ke fase 9; sakelar pemicu dan templat pesan tinggal di Integrasi, bukan di pengaturan/config Absensi.
>   Fase 9 selesai 2026-10-02 (`docs/ai/plan/fase-9/whatsapp-integration-plan.md`). Yang berubah untuk draf ini:
>   - Pesan **dikirim sungguhan** lewat gateway OpenWA milik provider; tidak ada pengirim stub dan status "Belum dikirim" diganti `pending` / `sent` / `failed` / `unsent` / `no_recipient`.
>   - `GuardianNotifier`, `GuardianNotice`, `NoticeRegistry`, `NoticeKind`, tabel `whatsapp_messages`, job `DeliverWhatsappMessage`, dan riwayat di Integrasi › WhatsApp **sudah ada** di Core (`modules/Core/CONTRACT.md`). Tahap 4 di bawah tinggal: daftarkan empat `NoticeKind` dari `AttendanceServiceProvider` (kunci yang sudah diumumkan: `attendance.absent`, `attendance.gate`; dua lainnya ditambahkan) dan panggil `GuardianNotifier::notify()` di tiap pemicu.
>   - Kolom `notify_*` di `attendance_settings` dan sakelar di `Settings.tsx` **dihapus dari rancangan**: sakelar dan isi pesan per jenis diatur sekolah di Integrasi › WhatsApp (bawaan mati). Absensi tidak memeriksa sakelar; ia selalu memanggil notifier.
>   - Bagian `MasterDataController::whatsapp()` / `IntegrationMockData` tidak berlaku lagi: keduanya sudah dihapus, halamannya dilayani `WhatsappController`.
>   - Menu Integrasi sekarang di balik `core.integration.manage`; menu Absensi masih belum bergate.

## Absensi sekolah dari data nyata: gerbang, jam pelajaran, QR sekali pakai, dan log WA wali murid

> **Status dokumen:** Draf
> **Dibuat:** 2026-10-02 · **Diperbarui:** 2026-10-02 · **Branch:** `feat/attendance`

## Context
Modul **Attendance** masih mockup: `modules/Attendance/app/Http/Controllers/AttendanceController.php` mengirim data tetap dari `modules/Attendance/app/Infrastructure/Mock/AttendanceMockData.php` ke tiga halaman (`/absensi`, `/absensi/input`, `/absensi/rekap`). Tidak ada tabel, izin, atau kontrak (`modules/Attendance/CONTRACT.md`). Master Data, Akademik, Impor, dan Statistik & Laporan di Core sudah tersimpan di database (fase 3, 5, 6, 7).

User meminta fitur absensi dengan dua model — **absensi gerbang** (siswa masuk dan keluar sekolah) dan **absensi per jam pelajaran** (hanya slot bel bertipe `Pelajaran`) — dua cara mencatat (**QR** dan **manual**), serta **notifikasi WA ke wali murid** yang fungsinya disiapkan dan lognya tercatat, sedangkan pengiriman asli disambung belakangan.

Dua hal dari kode yang membentuk rencana ini:
- Attendance hanya boleh memakai `CorePublic` (`deptrac.php` baris 178–181), tetapi kontrak Core baru berisi registry laporan/statistik. Belum ada kontrak untuk membaca siswa, kelas, dan jam pelajaran, jadi fase ini menambahkannya di Core.
- Belum ada akun siswa (peran hanya `admin-sekolah`, `guru`, `staf-tu` di `modules/Identity/config/roles.php`; login memakai email). Kolom `students.user_id` sudah ada tetapi belum diisi siapa pun. User memutuskan akun siswa dikerjakan di fase terpisah.

## Goal
Setelah ini, petugas sekolah bisa mencatat siswa masuk dan pulang di gerbang dengan memindai QR sekali pakai dari HP siswa atau mencarinya secara manual; guru bisa mengabsen satu kelas per jam pelajaran; wali kelas/TU bisa menandai sakit, izin, alpa harian; admin melihat rekap hari ini, rekap bulanan, laporan, dan statistik kehadiran dari database; dan setiap kejadian yang dipilih menghasilkan baris log pesan WA untuk wali murid yang terlihat di Integrasi › WhatsApp. Banner "Tampilan contoh" hilang dari halaman Absensi.

## Batasan masalah

**Dalam cakupan**
- Kontrak baca Core untuk siswa, kelas, dan jam pelajaran; kontrak Core untuk pesan ke wali murid beserta tabel log dan pengirim stub.
- Absensi harian/gerbang: masuk, pulang, status Hadir/Terlambat/Sakit/Izin/Alpa; batas jam terlambat diatur sekolah.
- Absensi per jam pelajaran: kelas + tanggal + slot `Pelajaran`, mapel opsional dipilih guru dari pengampu kelas itu.
- QR sekali pakai: halaman "QR Absensi Saya" untuk akun yang tertaut ke siswa, dan halaman pemindai (kamera, scanner USB, atau cari manual).
- Empat pemicu notifikasi: masuk gerbang, pulang gerbang, tidak hadir harian, alpa di jam pelajaran — masing-masing bisa dimatikan sekolah.
- Riwayat pesan di Integrasi › WhatsApp membaca tabel log.
- Rekap hari ini, rekap bulanan, laporan `attendance-monthly` dan `attendance-class`, angka `attendance-rate`, panel `attendance-trend`.
- Izin baru, tes Pest (feature, browser), spec Playwright, pembersihan mock, dokumentasi.

**Di luar cakupan**
- Akun dan peran siswa, login dengan NIS, pembuatan akun massal, menu sidebar untuk siswa — fase terpisah (keputusan user). Halaman QR sudah siap dan diuji dengan menautkan `students.user_id` lewat factory.
- Kartu QR statis — user memilih cadangan manual saja.
- Pengiriman WA sungguhan, sambungan perangkat, kuota, templat pesan yang bisa diedit, sakelar di halaman Integrasi — tetap mock; fase Integrasi.
- Jadwal pelajaran (mapel per jam per kelas) — belum ada tabelnya; guru memilih mapel sendiri.
- Pembatasan "guru hanya kelas yang ia ampu" — tanpa jadwal tidak bisa ditegakkan dengan benar; izin berlaku untuk semua kelas.
- Alpa otomatis di akhir hari (scheduler) dan pengecualian hari libur dari `calendar_events` — siswa tanpa catatan tetap "Belum diabsen".
- Absensi guru/tendik.

**Asumsi**
- Siswa aktif selalu punya `class_id` pada rombel tahun ajaran aktif (`modules/Core/app/Domain/Models/Student.php`).
- `period_slots.day` 1–6 = Senin–Sabtu, tipe pelajaran = `PeriodSlot::LESSON` (`modules/Core/app/Domain/Models/PeriodSlot.php`). Hari Minggu tidak punya jam pelajaran.
- Cache aplikasi dibagi antar proses (database/redis) di produksi, sehingga token QR yang terbit di satu request terbaca di request pemindai.
- Tenant baru mendapat izin lewat `SeedDefaultRoles`; tenant lama tidak (belum ada perintah sinkron peran) — di dev pakai `php artisan migrate:fresh --seed`.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Urutan lapisan dan permukaan publik hanya `app/Contracts/**`; kontrak tidak membuka model Eloquent — DTO/skalar (sumber: `AGENTS.md` § Public surface, `docs/architecture/modular-monolith.md`).
- Tanpa FK selain `tenant_id` (juga antar tabel satu modul — tes arsitektur memindai `->foreign(`/`->constrained(`); relasi = kolom polos berindeks (sumber: `tests/Architecture/ModularMonolithTest.php`).
- Data tenant hanya lewat Eloquent + `BelongsToTenant`, bukan `DB::table()`; tanggal dan jam memakai zona waktu tenant `TenantContext::timezone()` (sumber: `AGENTS.md` § Tenancy traps).
- Model baru yang memakai `BelongsToTenant` ditambahkan ke `deptrac.baseline.yaml` (satu-satunya alasan baseline boleh tumbuh).
- Rate limit di controller dengan tenant id di kuncinya, bukan middleware `throttle:` (sumber: `AGENTS.md` § Identity).
- Tanpa Notification Laravel; pekerjaan antrean membawa tenant otomatis (`TenantQueueContext`).
- UI dasar dari `modules/Shared/resources/js/components/ui`; URL dari Wayfinder (sumber: `.ai/rules/js.md`). Aktifkan skill `modular-monolith`, `laravel-best-practices`, `inertia-react-development`, `wayfinder-development`, `writing-tests`, `testing-best-practices` saat menyentuh bagiannya.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-02 | QR = kode sekali pakai yang dibuat halaman siswa, dipindai pihak sekolah | user |
| 2026-10-02 | Akun/peran siswa di fase terpisah; fase ini menyiapkan halaman QR dan pemindai | user |
| 2026-10-02 | Cadangan tanpa HP = absensi manual saja, tanpa kartu statis | user |
| 2026-10-02 | Boleh menambah dua paket npm: pembuat QR dan pemindai kamera | user |
| 2026-10-02 | Pemicu WA: masuk gerbang, pulang gerbang, tidak hadir harian, alpa di jam pelajaran | user |
| 2026-10-02 | Ada status Terlambat; batas jam diatur sekolah | user |
| 2026-10-02 | Absensi jam = kelas + slot `Pelajaran`; mapel opsional dipilih guru dari pengampu kelas | user |
| 2026-10-02 | Log WA dan kontrak pengirim milik Core; Absensi memanggil kontrak | user |
| 2026-10-02 | Paket: `qrcode.react` (QR sebagai SVG) dan `qr-scanner` (kamera) | hasil desain |
| 2026-10-02 | Token QR disimpan di `TenantCache` (TTL 60 detik, satu token hidup per siswa, dihapus saat dipakai) — tanpa tabel | hasil desain |
| 2026-10-02 | Halaman QR digate oleh tautan `students.user_id`, bukan izin; tanpa entri sidebar sampai fase akun siswa | hasil desain |
| 2026-10-02 | Notifikasi hanya untuk catatan bertanggal hari ini (mengisi mundur tidak mengirim pesan); alpa jam pelajaran tidak dikirim bila status harian siswa sudah sakit/izin/alpa | hasil desain |
| 2026-10-02 | Pulang tanpa catatan masuk ditolak; masuk/pulang kedua kali ditolak dengan pesan jam yang sudah tercatat | hasil desain |
| 2026-10-02 | Sakelar empat pemicu disimpan di pengaturan Absensi, bawaan menyala | hasil desain |
| 2026-10-02 | Rekap dan laporan mengelompokkan per bulan di PHP (bukan fungsi tanggal SQL) agar sama di semua driver database | hasil desain |

## Desain

### Core — permukaan publik baru (`modules/Core/app/Contracts`)
- `StudentDirectory` — `find(int $id): ?StudentRecord`, `findByUserId(int $userId): ?StudentRecord`, `many(array $ids): array<int, StudentRecord>`, `ofClass(int $classId): list<StudentRecord>` (aktif, urut nama), `search(string $term, int $limit = 10): list<StudentRecord>` (aktif, nama atau NIS).
- `ClassDirectory` — `ofActiveYear(): list<ClassRecord>`, `ofYear(int $academicYearId): list<ClassRecord>`, `find(int $id): ?ClassRecord`, `subjectsOf(int $classId): list<ClassSubject>` (dari `teaching_assignments`).
- `BellSchedule` — `slotsOn(int $day): list<BellSlot>`, `find(int $slotId): ?BellSlot`.
- `GuardianNotifier` — `notify(GuardianNotice $notice): void`.
- `DTOs/StudentRecord` (`id`, `name`, `nis`, `?classId`, `?className`, `active`) — tanpa nomor wali; `DTOs/ClassRecord` (`id`, `name`, `academicYearId`, `?homeroomName`, `studentCount`); `DTOs/ClassSubject` (`subjectId`, `subjectName`, `teacherId`, `teacherName`); `DTOs/BellSlot` (`id`, `day`, `startsAt`, `endsAt` `H:i`, `type`, `isLesson`); `DTOs/GuardianNotice` (`studentId`, `kind`, `kindLabel`, `body`). Semua `final readonly`.

### Core — internal
- `Infrastructure/Directory/{EloquentStudentDirectory,EloquentClassDirectory,EloquentBellSchedule}.php` (baru), di-bind di `CoreServiceProvider::register()`.
- Tabel `whatsapp_messages` (migrasi baru `0004_01_01_000006_create_whatsapp_messages_table.php`): `tenant_id`, `student_id` (nullable, indeks), `recipient_name`, `phone` (nullable), `kind`, `kind_label`, `body`, `status`, `sent_at` (nullable), `error` (nullable), timestamps. Model `Domain/Models/WhatsappMessage.php` + factory.
- Status: `pending` (tercatat, menunggu), `unsent` (pengirim belum tersambung), `no_recipient` (wali tanpa nomor), dan `sent`/`failed` untuk pengirim asli kelak.
- `Infrastructure/Whatsapp/WhatsappGateway.php` (interface internal) + `LogOnlyWhatsappGateway.php` (stub, pola `AlwaysSucceedsPaymentGateway` di Platform): tidak mengirim, menandai `unsent`.
- `Infrastructure/Whatsapp/DefaultGuardianNotifier.php` — mengambil nama dan nomor wali dari `Student`, menulis baris log, lalu men-dispatch job `Infrastructure/Whatsapp/DeliverWhatsappMessage.php` (antrean) yang memanggil gateway. Menyambung pengiriman asli nanti = mengganti binding gateway.
- `MasterDataController::whatsapp()` — prop `history` dari 20 baris terakhir `whatsapp_messages` (nomor disamarkan); `connection`, `notifications`, `template` tetap dari `IntegrationMockData` (method `whatsappHistory()` dihapus). `Pages/Core/Integration/Whatsapp/Index.tsx`: tambah label status `pending`/`unsent`/`no_recipient` dan `EmptyState`.

### Attendance — tabel (`modules/Attendance/database/migrations`, semua baru, `tenantId()`)
- `attendance_settings` — satu baris per sekolah: `late_after` (time, bawaan `07:00`), `notify_gate_in`, `notify_gate_out`, `notify_daily_absence`, `notify_lesson_absence` (boolean, bawaan true); unique `tenant_id`.
- `daily_attendances` — `student_id`, `class_id` (kelas saat dicatat), `date`, `status`, `checked_in_at`, `checked_out_at` (nullable), `check_in_method`, `check_out_method` (nullable), `note` (nullable), `recorded_by` (user id); unique `(tenant_id, student_id, date)`, indeks `(tenant_id, date, class_id)`.
- `lesson_sessions` — `class_id`, `date`, `period_slot_id`, `start_time`, `end_time` (salinan slot), `subject_id`, `teacher_id` (nullable), `recorded_by`; unique `(tenant_id, class_id, date, period_slot_id)`.
- `lesson_attendances` — `lesson_session_id`, `student_id`, `status`, `method`, `scanned_at` (nullable); unique `(lesson_session_id, student_id)`.

### Attendance — internal (`modules/Attendance/app`)
- `Domain/Enums/AttendanceStatus` (`Present`, `Late`, `Sick`, `Permit`, `Absent`; `Late` hanya harian; `countsAsPresent()`), `Domain/Enums/RecordMethod` (`Qr`, `Manual`).
- `Domain/Models/{AttendanceSetting,DailyAttendance,LessonSession,LessonAttendance}.php` + factory. `AttendanceSetting::current()` membuat baris bawaan bila belum ada (pola `SchoolProfile::current()`).
- `Domain/Actions`:
  - `SaveDailyAttendance` — satu kelas, satu tanggal (tidak di masa depan), daftar `student_id → status, note`; hanya siswa kelas itu; upsert; tidak menghapus jam masuk/pulang yang sudah ada.
  - `RecordGateCheckIn` — siswa aktif; jam ≤ `late_after` → `Present`, selebihnya `Late`; menimpa status sakit/izin/alpa hari itu; masuk kedua kali → `AttendanceException`.
  - `RecordGateCheckOut` — butuh catatan masuk hari itu; pulang kedua kali ditolak.
  - `SaveLessonAttendance` — kelas tahun aktif, slot `isLesson` yang harinya cocok dengan tanggal, mapel (bila diisi) harus ada di `subjectsOf`; upsert sesi + baris siswa.
  - `MarkLessonPresence` — hasil pindai/manual satu siswa pada sesi; siswa harus anggota kelas itu; membuat sesi bila belum ada.
  - `SaveAttendanceSettings`.
- `Domain/Qr/QrTokens` — `issue(int $studentId): array{token, expiresIn}` dan `consume(string $token): ?int` di atas `TenantCache` (`modules/Platform/app/Contracts/TenantCache.php`); kunci memakai hash token; menerbitkan token baru mematikan token lama siswa itu; `consume` mengambil-lalu-menghapus dalam satu langkah (`repository()->pull()`).
- `Domain/Notifications/GuardianNotices` — menyusun teks dari `modules/Attendance/config/attendance.php` (baru; empat templat dengan `{nama_siswa}`, `{tanggal}`, `{jam}`, `{status}`, `{jam_pelajaran}`, `{mapel}`, `{nama_sekolah}`), memeriksa sakelar pengaturan dan aturan "hanya hari ini", lalu memanggil `GuardianNotifier` setelah transaksi commit. Jenis: `attendance.gate-in`, `attendance.gate-out`, `attendance.daily-absence`, `attendance.lesson-absence`. Tidak hadir harian dan alpa jam hanya dikirim saat status **berubah** menjadi nilai itu (menyimpan ulang tidak mengirim dua kali).
- `Domain/Reports/{MonthlyAttendanceReport,ClassAttendanceReport}.php` (key `attendance-monthly`, `attendance-class`, izin `attendance.view`) dan `Domain/Statistics/AttendanceStatistics.php` (figure `attendance-rate`, panel `attendance-trend` 6 bulan) — didaftarkan lewat `ReportRegistry`/`StatisticsRegistry`; key yang sama menggantikan placeholder di `modules/Core/config/insight.php`.
- Controller (satu per halaman, tipis di atas Action, FormRequest per tulis): `OverviewController`, `DailyInputController`, `LessonAttendanceController`, `ScanController`, `MonthlyRecapController`, `SettingsController`, `StudentQrController`. `AttendanceController` dan `AttendanceMockData` dihapus di Tahap 5.
- `AttendanceServiceProvider`: `loadMigrationsFrom`, `mergeConfigFrom` (`attendance`, `permission_labels`), izin lewat `PermissionRegistry`, laporan + statistik, menu.

**Izin** (`attendance.*`, nama ditambahkan ke `modules/Identity/config/roles.php`)
| Izin | Arti | admin-sekolah | guru | staf-tu |
| --- | --- | --- | --- | --- |
| `attendance.view` | rekap, laporan | ✓ | ✓ | ✓ |
| `attendance.daily.record` | gerbang (pindai/manual) dan input harian | ✓ | ✓ | ✓ |
| `attendance.lesson.record` | absensi jam pelajaran | ✓ | ✓ | |
| `attendance.settings.manage` | pengaturan absensi | ✓ | | |

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
| `GET qr-saya` | `my-qr` | akun tertaut ke siswa (selain itu 403) |
| `POST qr-saya/token` (JSON) | `my-qr.token` | sama; rate limit di controller `attendance-qr:{tenant_id}:{user_id}` |

`POST pindai` menerima `mode` (`gate-in` \| `gate-out` \| `lesson`), salah satu dari `token` atau `student_id`, dan untuk `lesson`: `class_id`, `period_slot_id`. Jawaban sukses: nama, NIS, kelas, jam, status. Token kedaluwarsa/terpakai/tak dikenal, siswa salah kelas, atau catatan ganda → 422 dengan pesan.

**Halaman** (`modules/Attendance/resources/js/Pages/Attendance/`)
- `Overview.tsx` (ada) — props tetap + kartu Terlambat; tanggal `?tanggal=`; kelas tahun aktif dari `ClassDirectory`.
- `Input.tsx` (ada) — pilih kelas dan tanggal (reload Inertia), status per siswa termasuk Terlambat dan catatan, simpan lewat `useForm`.
- `Lessons.tsx` (baru) — tanggal, kelas, slot pelajaran hari itu, mapel opsional; awalan status mengikuti status harian (sakit/izin/alpa), sisanya belum ditandai; tombol "Tandai sisanya hadir"; `EmptyState` bila hari itu tanpa slot pelajaran.
- `Scan.tsx` (baru) — pilih mode; kamera (`qr-scanner`), kolom input untuk scanner USB/tempel token, dan pencarian nama/NIS untuk manual; hasil terakhir dan daftar pindaian sesi ini; `useHttp`.
- `MyQr.tsx` (baru) — QR (`qrcode.react`) yang diperbarui otomatis sebelum kedaluwarsa, hitung mundur, status hari ini (jam masuk/pulang).
- `Monthly.tsx` (ada) — kelas + bulan nyata, kolom Terlambat; tombol "Unduh rekap" dihapus (unduhan ada di Statistik & Laporan).
- `Settings.tsx` (baru) — batas jam terlambat dan empat sakelar notifikasi.
- `Components/AttendancePage.tsx` — banner mock menjadi opsional (pola prop `mock` di `MasterPage`), dihapus di Tahap 5; `Components/status.ts` — tambah `late`.

**Dipakai ulang**
- Backend: `TenantContext`, `TenantCache`, `PermissionRegistry`, `TenantNavigation`, `ModuleRegistry` (Platform Contracts); `ReportRegistry`, `StatisticsRegistry`, DTO laporan (Core Contracts); pola registrasi di `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`; pola laporan `modules/Core/app/Domain/Reports/StudentListReport.php`; pola endpoint JSON `modules/Core/app/Http/Controllers/ImportController.php`.
- Frontend: `@shared/components/page-parts` (`StatCard`, `Panel`, `OptionSelect`, `DataTable`, `EmptyState`, `PageHeader`), `@shared/components/ui/*`, `TenantShell`.
- Tes: `schoolAs()`, `inSchool()`, `classIn()`, `yearWithSemesters()` di `modules/Core/tests/Feature/Support/helpers.php`; `attendanceTenant()` di `modules/Attendance/tests/Feature/AttendancePagesTest.php`; `schoolMemberSignsIn()`, `inTenant()` di `tests/Browser/Support/school.php`; factory Core (`StudentFactory`, `ClassGroupFactory`, `PeriodSlotFactory`, `TeachingAssignmentFactory`).

### Identity / Platform
- Identity: hanya `modules/Identity/config/roles.php` (nama izin baru). Platform: tidak berubah.

### Alur
1. Siswa (akun tertaut) membuka "QR Absensi Saya"; QR berganti sendiri tiap ±45 detik.
2. Petugas membuka Pindai, memilih **Masuk**, mengarahkan kamera ke HP siswa → nama, kelas, jam, dan Hadir/Terlambat muncul. Siswa tanpa HP dicari lewat nama/NIS di halaman yang sama.
3. Wali kelas/TU membuka Input Absensi, menandai siswa yang sakit/izin/alpa.
4. Guru membuka Jam Pelajaran, memilih kelas dan jam, memindai atau menandai, lalu menyimpan.
5. Saat pulang, petugas memilih **Pulang** dan memindai.
6. Tiap kejadian yang sakelarnya menyala menambah baris di Integrasi › WhatsApp berstatus "Belum dikirim".
7. Admin melihat Rekap Hari Ini, Rekap Bulanan, serta laporan dan angka kehadiran di Statistik & Laporan.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Fondasi: dokumen plan, branch, kontrak baca Core, izin Absensi, gate route | ⬜ | |
| 1 | Absensi harian manual: pengaturan, Input, Rekap Hari Ini | ⬜ | |
| 2 | Gerbang + QR sekali pakai: halaman QR siswa, pemindai, masuk/pulang | ⬜ | |
| 3 | Absensi per jam pelajaran (manual + pindai) | ⬜ | |
| 4 | Notifikasi WA: kontrak + log di Core, empat pemicu, riwayat di Integrasi | ⬜ | |
| 5 | Rekap bulanan, laporan + statistik, tes browser, pembersihan mock, dokumentasi | ⬜ | |

## Tasks

### Tahap 0 — Fondasi
- [ ] Buat branch `feat/attendance`; perbarui `docs/ai/plan/fase-10/attendance-plan.md` (sudah disalin di fase 8) sesuai revisi di atas.
- [ ] Kontrak + DTO — `modules/Core/app/Contracts/{StudentDirectory,ClassDirectory,BellSchedule}.php`, `modules/Core/app/Contracts/DTOs/{StudentRecord,ClassRecord,ClassSubject,BellSlot}.php` (baru).
- [ ] Implementasi + binding — `modules/Core/app/Infrastructure/Directory/` (baru), `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`.
- [ ] Izin `attendance.*` + label — `modules/Attendance/app/Infrastructure/Providers/AttendanceServiceProvider.php`, `modules/Attendance/config/permission_labels.php` (baru), `modules/Identity/config/roles.php`.
- [ ] Gate route dan menu yang ada dengan `attendance.view` / `attendance.daily.record` (halaman masih mock) — `modules/Attendance/routes/web.php`, provider.
- [ ] Tes — `modules/Core/tests/Feature/SchoolDirectoryTest.php` (baru): hanya siswa aktif, urutan, pencarian nama/NIS, `findByUserId`, kelas tahun aktif vs tahun lain, `subjectsOf`, `slotsOn` + `isLesson`, isolasi tenant. `modules/Attendance/tests/Feature/AttendanceAccessTest.php` (baru): tiap peran per halaman, tanpa peran 403, modul mati 403. Pindahkan `attendanceTenant()` ke `modules/Attendance/tests/Feature/Support/helpers.php` (baru; periksa cara berkas Support dimuat di `tests/Pest.php`).

**Selesai bila:** Attendance bisa membaca siswa, kelas, dan jam lewat kontrak Core, dan halaman Absensi mengikuti izin. Bukti: `php artisan test --compact modules/Core modules/Attendance tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 1 — Absensi harian manual
- [ ] Migrasi `attendance_settings`, `daily_attendances`; `loadMigrationsFrom` — `modules/Attendance/database/migrations/` (baru).
- [ ] Enum, model, factory; entri `deptrac.baseline.yaml` — `modules/Attendance/app/Domain/{Enums,Models}/`, `modules/Attendance/database/factories/` (baru).
- [ ] `SaveDailyAttendance`, `SaveAttendanceSettings` + FormRequest — `modules/Attendance/app/Domain/Actions/`, `app/Http/Requests/` (baru).
- [ ] `OverviewController`, `DailyInputController`, `SettingsController`; route `input.update`, `settings`, `settings.update`; menu "Pengaturan".
- [ ] Halaman `Overview.tsx`, `Input.tsx` nyata; `Settings.tsx` (baru); `status.ts` + `late`; banner mock opsional.
- [ ] Tes — `DailyAttendanceTest.php` (baru): simpan dan simpan ulang (upsert), siswa kelas lain ditolak, tanggal masa depan ditolak, angka Rekap Hari Ini dan "Belum diabsen", jam masuk tidak terhapus oleh simpan ulang, izin, isolasi tenant. `AttendanceSettingsTest.php` (baru).

**Selesai bila:** status harian satu kelas tersimpan dan Rekap Hari Ini menghitungnya dari database. Bukti: `php artisan test --compact modules/Attendance` → hijau; `npx tsc --noEmit` → bersih.

### Tahap 2 — Gerbang + QR sekali pakai
- [ ] `npm install qrcode.react qr-scanner` — `package.json`.
- [ ] `QrTokens` — `modules/Attendance/app/Domain/Qr/QrTokens.php` (baru).
- [ ] `RecordGateCheckIn`, `RecordGateCheckOut`, `AttendanceException` — `app/Domain/Actions/`, `app/Domain/Exceptions/` (baru).
- [ ] `StudentQrController` (`my-qr`, `my-qr.token`), `ScanController` (`scan`, `scan.store` mode gerbang, `scan.students`) + `ScanRequest`; menu "Pindai QR".
- [ ] Halaman `MyQr.tsx`, `Scan.tsx` (baru).
- [ ] Tes — `QrTokenTest.php` (baru): token dipakai sekali, kedaluwarsa setelah 60 detik (`travel`), token baru mematikan token lama, token sekolah lain tidak dikenal. `GateAttendanceTest.php` (baru): masuk tepat waktu vs terlambat (`travelTo` pada zona tenant), masuk menimpa alpa, masuk ganda 422, pulang tanpa masuk 422, pulang ganda 422, manual lewat `student_id`, siswa nonaktif ditolak, akun tak tertaut 403 di `qr-saya`, rate limit token, izin.

**Selesai bila:** satu token dari halaman QR mencatat masuk tepat satu kali, dan pulang tercatat setelahnya. Bukti: `php artisan test --compact modules/Attendance` → hijau; `npm run build` → sukses.

### Tahap 3 — Absensi per jam pelajaran
- [ ] Migrasi `lesson_sessions`, `lesson_attendances`; model + factory; baseline.
- [ ] `SaveLessonAttendance`, `MarkLessonPresence` — `app/Domain/Actions/` (baru).
- [ ] `LessonAttendanceController` (`lessons`, `lessons.update`); mode `lesson` di `ScanController`; menu "Jam Pelajaran".
- [ ] Halaman `Lessons.tsx` (baru); mode jam di `Scan.tsx`.
- [ ] Tes — `LessonAttendanceTest.php` (baru): hanya slot `isLesson`, slot hari lain ditolak, kelas tahun lama ditolak, mapel di luar pengampu ditolak, sesi unik per kelas+tanggal+slot (simpan ulang memperbarui), awalan dari status harian, pindai siswa kelas lain 422, pindai membuat sesi, izin (`staf-tu` 403), isolasi tenant.

**Selesai bila:** guru menyimpan absensi satu kelas untuk satu jam pelajaran, lewat daftar maupun pindai. Bukti: `php artisan test --compact modules/Attendance` → hijau.

### Tahap 4 — Notifikasi WA
- [ ] Kontrak `GuardianNotifier` + `DTOs/GuardianNotice` — `modules/Core/app/Contracts/` (baru).
- [ ] Migrasi `whatsapp_messages`, model, factory, baseline; gateway stub, notifier, job; binding — `modules/Core/database/migrations/0004_01_01_000006_create_whatsapp_messages_table.php`, `modules/Core/app/Infrastructure/Whatsapp/` (baru), `CoreServiceProvider.php`.
- [ ] Riwayat nyata di Integrasi — `modules/Core/app/Http/Controllers/MasterDataController.php`, `modules/Core/app/Infrastructure/Mock/IntegrationMockData.php`, `modules/Core/resources/js/Pages/Core/Integration/Whatsapp/Index.tsx`.
- [ ] `GuardianNotices` + templat — `modules/Attendance/app/Domain/Notifications/GuardianNotices.php`, `modules/Attendance/config/attendance.php` (baru); panggil dari empat Action.
- [ ] Tes — `modules/Core/tests/Feature/GuardianNotifierTest.php` (baru): baris log berisi nama dan nomor wali, wali tanpa nomor → `no_recipient`, job stub → `unsent` (ingat `TenantQueueContext::register()` di setup), riwayat Integrasi menyamarkan nomor, isolasi tenant. `modules/Attendance/tests/Feature/AttendanceNotificationTest.php` (baru): tiap pemicu membuat satu baris, sakelar mati tidak membuat baris, tanggal lampau tidak, simpan ulang tidak menggandakan, alpa jam dilewati bila harian sudah tidak hadir.

**Selesai bila:** tiap kejadian yang dipilih menambah satu baris di riwayat Integrasi › WhatsApp. Bukti: `php artisan test --compact modules/Core modules/Attendance tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 5 — Rekap, laporan, penutup
- [ ] `MonthlyRecapController` + `Monthly.tsx` nyata (`?kelas=`, `?bulan=YYYY-MM`).
- [ ] `MonthlyAttendanceReport`, `ClassAttendanceReport`, `AttendanceStatistics` + pendaftaran — `modules/Attendance/app/Domain/{Reports,Statistics}/` (baru), provider.
- [ ] Seeder contoh — `modules/Attendance/database/seeders/AttendanceDemoSeeder.php` (baru; tanpa `WithoutModelEvents`).
- [ ] Tes — `MonthlyRecapTest.php`, `AttendanceInsightTest.php` (baru): hitungan per status, persen dengan Terlambat dihitung hadir, bulan kosong, placeholder "Segera hadir" hilang, laporan tidak ada bila modul mati, isolasi tenant.
- [ ] Tes browser — `tests/Browser/AttendanceTest.php` (baru; pola `tests/Browser/InsightTest.php`): input harian tersimpan dan muncul di rekap; token dari `QrTokens` ditempel ke kolom input pemindai → nama siswa muncul; `assertNoJavaScriptErrors()`. Spec `tests/E2E/attendance.spec.ts` (baru): halaman QR menampilkan gambar QR dan menggantinya.
- [ ] Bersihkan: hapus `AttendanceController.php`, `AttendanceMockData.php`, banner mock; `npm run build`.
- [ ] Dokumentasi: `modules/Attendance/CONTRACT.md`, `modules/Core/CONTRACT.md`, `docs/architecture/modular-monolith.md`, salinan `AGENTS.md`, memori `project_mockup_first.md`.

**Selesai bila:** rekap, laporan, dan angka kehadiran berasal dari database dan tidak ada sisa mock Absensi. Bukti: `php artisan test --compact modules/Attendance modules/Core tests/Architecture` → hijau; `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/AttendanceTest.php` → hijau; `composer deptrac` → 0 pelanggaran; `npm run build` → sukses.

## Batas tindakan
**Selalu**
- `vendor/bin/pint --dirty --format agent` dan tes modul yang disentuh sebelum menandai tahap ✅.
- Perbarui tabel status, centang task, catat penyimpangan di Log keputusan.
- Berhenti setelah tiap tahap dan tunggu persetujuan.

**Tanya dulu**
- Menambah dependensi selain `qrcode.react` dan `qr-scanner`, atau menggantinya.
- Mengubah signature kontrak Core setelah Tahap 0, atau menambah nomor wali ke DTO.
- Mengubah `deptrac.php`, kode Platform, atau kode Identity selain `config/roles.php`.
- Membuat peran `siswa` atau cara login baru.

**Jangan**
- Mengimpor model Core atau `User` dari Attendance; mengimpor Attendance dari Core.
- FK selain `tenant_id`; `DB::table()` untuk data tenant; `WithoutModelEvents` di seeder.
- Menaruh NIS/nama di isi QR; menyimpan token di database.
- Mengirim WA sungguhan; menghapus tes; mengubah nama route `attendance.overview`/`input`/`monthly`; commit/push tanpa diminta.

## Pengujian
- **Tahap 0:** kontrak direktori Core; akses per peran dan flag modul.
- **Tahap 1:** simpan harian, validasi, rekap hari ini, pengaturan, isolasi tenant.
- **Tahap 2:** siklus hidup token; masuk/pulang, terlambat, ganda, manual.
- **Tahap 3:** aturan slot/kelas/mapel, sesi unik, pindai mode jam.
- **Tahap 4:** log, status, sakelar, anti-ganda, job antrean.
- **Tahap 5:** rekap, laporan, statistik; satu alur browser; satu spec Playwright.

Factory untuk semua data; jam diuji dengan `travelTo` pada zona waktu tenant. Kamera tidak diuji otomatis — kolom input pemindai adalah jalur ujinya. Setelah Tahap 5, minta user menjalankan suite penuh `php artisan test --compact`.

## Verifikasi end-to-end
1. `php artisan migrate:fresh --seed`, lalu `php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDemoSeeder"`.
2. Tautkan satu akun ke satu siswa (isi `students.user_id`), login sebagai akun itu, buka `/absensi/qr-saya` (URL dari tool `get-absolute-url`) → QR tampil dan berganti.
3. Di perangkat lain login sebagai admin → Absensi › Pindai, mode Masuk, pindai QR dengan kamera → nama dan status muncul; pindai QR yang sama lagi → ditolak.
4. Cari siswa lain lewat nama → tercatat manual. Mode Pulang → jam pulang terisi.
5. Input Absensi: tandai satu siswa sakit; Jam Pelajaran: simpan satu sesi dengan satu alpa.
6. Integrasi › WhatsApp → baris masuk, pulang, sakit, dan alpa jam berstatus "Belum dikirim". Matikan satu sakelar di Pengaturan → kejadian itu tidak lagi menambah baris.
7. Rekap Hari Ini, Rekap Bulanan, Statistik & Laporan → angka cocok; CSV "Rekap Kehadiran Bulanan" terunduh.
8. Login sebagai guru: Pengaturan 403. Sekolah lain: data terpisah. `composer deptrac` → bersih.

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- (kosong)

### Temuan
- Risiko yang perlu dicek di Tahap 2: `qr-scanner` memuat worker lewat dynamic import — pastikan `npm run build` (Vite 8) membundelnya; bila tidak, tanya user sebelum mengganti paket. Kamera butuh HTTPS atau `localhost`.
- `TenantCache::repository()` disebut "already prefixed" — pastikan `pull()` memakai kunci yang benar sebelum mengandalkannya.

### Hasil akhir
(diisi saat selesai)
