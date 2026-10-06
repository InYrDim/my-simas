# Absensi hanya bisa dipindai pada waktunya: jam pelajaran, jendela gerbang, dan pulang awal

> **Status dokumen:** Berjalan
> **Dibuat:** 2026-10-06 · **Diperbarui:** 2026-10-06 · **Branch:** `feat/attendance-time-rules`

## Context
Modul **Attendance** (`modules/Attendance`) sudah berjalan dengan data nyata (Fase 10, 13). Pemindaian QR/manual lewat `ScanController::store()` (`/absensi/pindai`) memanggil `RecordGateCheckIn`, `RecordGateCheckOut` dan `MarkLessonPresence`. Ketiganya hanya memeriksa **hari**, bukan **jam**:

- `MarkLessonPresence` → `LessonSlots::resolve()` hanya memeriksa kelas, tanggal tidak di masa depan, slot bertipe `Pelajaran`, dan hari slot sama dengan hari ini. Scan jam 22.00 untuk pelajaran 08.00–09.00 **diterima**. Padahal menu Absensi Kelas guru sudah dibatasi jamnya lewat `SaveOwnLessonAttendance` (`whileRunning: true`, `LessonState::Running`).
- `RecordGateCheckIn` menerima scan masuk kapan saja; lewat `late_after` hanya jadi Terlambat. Scan masuk jam 22.00 tercatat "Terlambat".
- `RecordGateCheckOut` menerima scan pulang kapan saja setelah masuk; pulang jam 07.05 tidak ditandai apa pun.
- Scanner (`Scan.tsx`) menawarkan semua kelas yang diampu guru dan semua slot hari itu, tidak terkunci ke pelajaran yang sedang berjalan.
- Siswa yang sudah tercatat pulang masih bisa dipindai hadir di pelajaran.

Aturan waktu yang sama sudah ada di sisi guru (`TeacherLessons::state()`), jadi tahap pertama cukup memakainya ulang. Aturan hari libur dan alpa otomatis butuh kontrak kalender baru di Core dan sengaja tidak termasuk (lihat Di luar cakupan).

## Goal
Setelah ini, pemindaian dan pencatatan absensi hanya diterima pada waktunya: scan pelajaran hanya di dalam jam pelajaran itu (dengan toleransi awal yang diatur sekolah), scan gerbang hanya di dalam jendela buka–tutup gerbang sekolah, dan siswa yang pulang sebelum jam pelajaran terakhir selesai ditandai **Pulang awal**. Petugas melihat alasan penolakan yang jelas di scanner; koreksi di luar waktu tetap lewat Riwayat Absensi / input harian.

## Batasan masalah

**Dalam cakupan**
- Scan pelajaran (QR dan manual) ditolak di luar `[mulai − toleransi, selesai)` jam pelajaran itu.
- Guru (tanpa izin sekolah-luas) hanya bisa memindai pelajaran miliknya yang sedang berjalan; admin/staf tetap bebas memilih kelas tetapi tetap terikat jendela waktu.
- Jendela gerbang `gate_opens_at`–`gate_closes_at` untuk scan masuk dan pulang.
- Pulang awal: scan pulang sebelum akhir jam pelajaran terakhir hari itu ditandai `left_early`.
- Scan pelajaran ditolak untuk siswa yang sudah tercatat pulang hari itu.
- Pengaturan baru di `/absensi/pengaturan` dan pesan penolakan berbahasa Indonesia.

**Di luar cakupan**
- Hari libur / hari tanpa jam pelajaran ditolak — butuh kontrak Core baru atas `calendar_events` (`SchoolCalendar`); jadi fase tersendiri.
- Alpa otomatis dan penyebut persentase memakai hari efektif — bergantung pada kalender di atas dan keputusan scheduler.
- Alasan wajib untuk pulang awal, izin pulang, dan pemberitahuan WA khusus pulang awal — hanya penanda dulu; notice `attendance.gate-out` tetap.
- Batas hari koreksi di Riwayat Absensi, jejak perubahan (audit), status Terlambat di pelajaran, peringatan pola kehadiran.
- Input harian (`SaveDailyAttendance`, `/absensi/input`) dan Riwayat Absensi tidak diubah: itulah jalur koreksi yang sah.

**Asumsi**
- Jam bel sekolah sama untuk semua kelas pada satu hari (`BellSchedule::slotsOn()`), jadi "jam pelajaran terakhir" adalah satu nilai per hari.
- Tes lama yang memindai di luar jam akan gagal dan diperbarui (membekukan waktu), bukan dilonggarkan aturannya.
- Nilai bawaan: `gate_opens_at` 05:00, `gate_closes_at` 18:00, `lesson_scan_early_minutes` 5. Sekolah yang sudah memakai modul tidak boleh terkejut: bawaan dipilih longgar.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Attendance hanya boleh memakai `CorePublic` (`BellSchedule`, `TeacherSchedule`, …); tabel tanpa FK selain `tenant_id` (sumber: `AGENTS.md` › Modular Monolith, `modules/Attendance/CONTRACT.md`).
- Waktu selalu pada jam sekolah lewat `Domain/Support/SchoolClock`, kolom `date` string `Y-m-d`, cap waktu lewat `SchoolClock::stored()` (sumber: `modules/Attendance/CONTRACT.md` › The school's clock).
- Tidak ada scheduler: semua aturan dihitung saat permintaan (sumber: `CONTRACT.md` › Daily attendance).
- Tes feature Pest di `modules/Attendance/tests/Feature`; baca skill `writing-tests` dan `testing-best-practices` sebelum menulis tes.
- UI: komponen dari `modules/Shared`, tidak banyak input terlihat sekaligus (sumber: `.ai/rules/js.md`).
- Setelah mengubah PHP: `vendor/bin/pint --dirty --format agent`.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-06 | Cakupan fase ini hanya rekomendasi P1 (waktu scan); kalender/alpa otomatis ditunda | user |
| 2026-10-06 | Aturan waktu hidup di aksi domain (bukan di controller) agar semua pemanggil terikat | hasil baca kode |
| 2026-10-06 | Nomor fase 14 (Fase 13 = Kelas Saya guru, belum punya berkas plan) | user |

## Desain

### Attendance (`modules/Attendance`, tanpa kontrak publik baru)
- **`attendance_settings`** bertambah: `lesson_scan_early_minutes` (unsigned tinyint, bawaan 5), `gate_opens_at` (time, bawaan `05:00:00`), `gate_closes_at` (time, bawaan `18:00:00`). Model `AttendanceSetting` (`Domain/Models/AttendanceSetting.php`) menambah `#[Fillable]`, default di `current()` dan pembaca `gateOpensAt()` / `gateClosesAt()` dalam `H:i`.
- **`daily_attendances`** bertambah `left_early` (boolean, bawaan false) — penanda dari scan pulang.
- **Pemeriksa waktu baru** `Domain/Support/ScanWindow.php` (baru), bergantung pada `SchoolClock`, `AttendanceSetting`, `LessonSlots`:
  - `assertLessonOpen(BellSlot $slot)`: lolos bila `mulai − early ≤ sekarang < selesai`; gagal → `AttendanceException` "Jam pelajaran 08.00–09.00 belum dimulai / sudah selesai. Koreksi lewat Riwayat Absensi."
  - `assertGateOpen()`: lolos bila `gate_opens_at ≤ sekarang ≤ gate_closes_at`; gagal → "Gerbang dibuka pukul 05.00–18.00."
  - `lastLessonEnd(): ?string`: akhir slot `Pelajaran` terakhir hari ini dari `LessonSlots::on($today)` (null bila tidak ada).
- **Aksi**: `MarkLessonPresence` memanggil `assertLessonOpen($slot)` setelah `resolve()`, dan menolak siswa yang `DailyAttendance.checked_out_at`-nya sudah terisi hari ini. `RecordGateCheckIn` / `RecordGateCheckOut` memanggil `assertGateOpen()` di awal. `RecordGateCheckOut` mengisi `left_early = now < lastLessonEnd()`.
- **Scanner guru**: `ScanController::store()` untuk pengguna tanpa `attendance.lesson.school` mensyaratkan `TeacherLessons::find($userId, today, $slotId)` dengan `classId` yang sama (jika tidak: "Itu bukan jadwal mengajar Anda."). `ScanController::index()` untuk guru hanya mengirim slot/kelas pelajaran yang sedang berjalan miliknya; tidak ada pelajaran berjalan → halaman menampilkan keadaan kosong untuk mode pelajaran (mode gerbang tetap bila izinnya ada).
- **Pengaturan**: `SaveAttendanceSettings`, `SettingsController`, `Settings.tsx` memuat tiga field baru. Bila input terlihat > ±6, pindahkan editor ke modal (`.ai/rules/js.md`).
- Respons scan pulang: status `Pulang awal` bila `left_early`, selain itu `Pulang` (`ScanController::store()`).

### Alur
1. Siswa menampilkan QR; guru membuka Kelas Saya › Pindai QR pada jam pelajarannya → scanner langsung memuat pelajaran yang berjalan → scan berhasil.
2. Guru memindai sebelum toleransi atau setelah jam selesai → 422 `errors.scan` dengan pesan jam yang jelas; QR terpakai (aturan lama: kode hangus walau ditolak).
3. Petugas gerbang memindai masuk jam 22.00 → ditolak "Gerbang dibuka pukul 05.00–18.00."
4. Siswa scan pulang jam 10.00 padahal pelajaran terakhir selesai 14.30 → tercatat pulang dengan penanda **Pulang awal**.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 1 | Scan pelajaran hanya dalam jamnya + scanner guru terkunci ke pelajaran miliknya | ✅ | `ScanWindow::assertLessonOpen()` dipakai `MarkLessonPresence`; scanner guru hanya memuat pelajaran yang sedang berjalan; 208 tes Attendance hijau |
| 2 | Jendela gerbang + penanda pulang awal | ⬜ | |
| 3 | Konsistensi gerbang–kelas (tolak scan pelajaran setelah pulang) + dokumentasi | ⬜ | |

## Tasks

### Tahap 1 — Scan pelajaran hanya dalam jamnya
- [x] Baca `modules/Attendance/app/Domain/Queries/ClassChoices.php` (`mayRecord`) dan `modules/Attendance/resources/js/Pages/Attendance/Scan.tsx` sebelum mengubah; pastikan aturan guru baru tidak bentrok dengan `mayRecord`.
- [x] Migrasi kolom `lesson_scan_early_minutes` — `modules/Attendance/database/migrations/0007_01_01_000004_add_scan_window_to_attendance_settings.php` (baru; contoh bentuk: `0007_01_01_000002_add_feature_switches_to_attendance_settings.php`)
- [x] Model + aksi pengaturan — `modules/Attendance/app/Domain/Models/AttendanceSetting.php`, `modules/Attendance/app/Domain/Actions/SaveAttendanceSettings.php`
- [x] `ScanWindow::assertLessonOpen()` — `modules/Attendance/app/Domain/Support/ScanWindow.php` (baru)
- [x] Pakai di `MarkLessonPresence` — `modules/Attendance/app/Domain/Actions/MarkLessonPresence.php`
- [x] Kunci scanner guru ke pelajarannya yang berjalan — `modules/Attendance/app/Http/Controllers/ScanController.php` (`index`, `store`), `modules/Attendance/resources/js/Pages/Attendance/Scan.tsx`
- [x] Field pengaturan + request — `modules/Attendance/app/Http/Controllers/SettingsController.php`, `modules/Attendance/resources/js/Pages/Attendance/Settings.tsx`
- [x] Tes: scan di dalam jam (diterima), di dalam toleransi awal (diterima), sebelum toleransi dan sesudah selesai (ditolak, pesan jam), guru memindai kelas/slot orang lain (ditolak), admin memindai kelas bebas dalam jam (diterima), isolasi tenant — `modules/Attendance/tests/Feature/LessonAttendanceTest.php` (perbarui tes lama dengan membekukan waktu) dan `modules/Attendance/tests/Feature/ScanWindowTest.php` (baru)

**Selesai bila:** scan pelajaran 08.00–09.00 pada pukul 22.00 ditolak dengan pesan jam, pada pukul 08.30 diterima; guru tidak bisa memindai pelajaran guru lain. Bukti: `php artisan test --compact modules/Attendance` → hijau.

### Tahap 2 — Jendela gerbang dan pulang awal
- [ ] Migrasi: `gate_opens_at`, `gate_closes_at` di `attendance_settings`, `left_early` di `daily_attendances` — `modules/Attendance/database/migrations/0007_01_01_000005_add_gate_window_and_left_early.php` (baru)
- [ ] Model: `AttendanceSetting` (pembaca jam), `DailyAttendance` (`left_early` cast boolean) — `modules/Attendance/app/Domain/Models/AttendanceSetting.php`, `modules/Attendance/app/Domain/Models/DailyAttendance.php`
- [ ] `ScanWindow::assertGateOpen()` dan `lastLessonEnd()` — `modules/Attendance/app/Domain/Support/ScanWindow.php`
- [ ] Pakai di `RecordGateCheckIn` dan `RecordGateCheckOut` (isi `left_early`) — `modules/Attendance/app/Domain/Actions/RecordGateCheckIn.php`, `RecordGateCheckOut.php`
- [ ] Status `Pulang awal` di respons scan — `modules/Attendance/app/Http/Controllers/ScanController.php`
- [ ] Field pengaturan jendela gerbang — `SaveAttendanceSettings.php`, `SettingsController.php`, `Settings.tsx`
- [ ] Tes: masuk/pulang di dalam dan di luar jendela, pulang sebelum dan sesudah jam pelajaran terakhir, hari tanpa jam pelajaran (tidak ditandai awal), isolasi tenant — `modules/Attendance/tests/Feature/GateAttendanceTest.php` (perbarui) dan `ScanWindowTest.php`

**Selesai bila:** scan masuk 22.00 ditolak, pulang 10.00 tercatat `left_early`, pulang setelah jam terakhir tidak. Bukti: `php artisan test --compact modules/Attendance/tests/Feature/GateAttendanceTest.php` → hijau.

### Tahap 3 — Konsistensi gerbang–kelas dan dokumentasi
- [ ] Tolak scan pelajaran bila siswa sudah tercatat pulang hari ini — `modules/Attendance/app/Domain/Actions/MarkLessonPresence.php`
- [ ] Tes: scan pelajaran setelah pulang ditolak, siswa tanpa baris harian tetap boleh dipindai (perilaku lama) — `modules/Attendance/tests/Feature/LessonAttendanceTest.php`
- [ ] Perbarui `modules/Attendance/CONTRACT.md` (Switches/Settings, The gate, Lessons, scanner) dan `docs/architecture/modular-monolith.md`; ringkasan singkat di `AGENTS.md` (salinan lokal yang di-gitignore, jaga tetap sinkron)
- [ ] Format: `vendor/bin/pint --dirty --format agent`

**Selesai bila:** CONTRACT dan docs menjelaskan aturan waktu; scan pelajaran setelah pulang ditolak. Bukti: `php artisan test --compact modules/Attendance` → hijau; `vendor/bin/deptrac` → tanpa pelanggaran baru.

## Batas tindakan
**Selalu**
- Jalankan Pint dan `php artisan test --compact modules/Attendance` sebelum menandai tahap selesai.
- Bekukan waktu di tes (zona waktu sekolah lewat `SchoolClock`), jangan bergantung pada jam mesin.
- Perbarui status, "Log keputusan" dan "Temuan" di dokumen ini saat bekerja.

**Tanya dulu**
- Mengubah nilai bawaan jendela (05:00 / 18:00 / 5 menit) setelah disetujui.
- Menambah kontrak Core (mis. `SchoolCalendar`) atau menyentuh modul lain.
- Menghapus atau melonggarkan tes lama yang gagal akibat aturan baru.

**Jangan**
- Menambah FK selain `tenant_id` atau mengimpor internal modul lain.
- Menaruh aturan waktu hanya di controller atau React (harus di aksi domain).
- Mengubah `SaveDailyAttendance` / Riwayat Absensi menjadi terikat waktu scan.
- Commit atau push tanpa diminta.

## Pengujian
- **Tahap 1:** dalam jam, dalam toleransi, sebelum/sesudah jam; guru vs jadwal orang lain; admin bebas kelas; token QR tetap hangus walau ditolak; isolasi tenant.
- **Tahap 2:** di dalam/di luar jendela gerbang; `left_early` benar/salah; hari tanpa slot; scan manual dan QR sama-sama terikat.
- **Tahap 3:** scan pelajaran setelah pulang ditolak; siswa belum masuk gerbang tetap bisa hadir di pelajaran (tidak berubah).

## Verifikasi end-to-end
1. `php artisan test --compact modules/Attendance` → semua hijau.
2. `vendor/bin/deptrac` → tanpa pelanggaran baru.
3. Di aplikasi (sekolah demo, `AttendanceDemoSeeder`): sebagai guru buka Pindai QR di luar jam pelajarannya → mode pelajaran menampilkan keadaan kosong/penolakan berpesan jam; pada jamnya scan berhasil.
4. Sebagai petugas gerbang, atur `gate_closes_at` ke jam yang sudah lewat di Pengaturan → scan masuk ditolak dengan pesan jendela.

Setelah rilis: `php artisan migrate` (tidak ada izin baru, `roles:sync` tidak perlu).

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-06 — Plan dibuat dari rekomendasi bisnis P1; cakupan P1 dan fase 14 disetujui user.
- 2026-10-06 — Tahap 1: `lesson_scan_early_minutes` dibuat opsional (`sometimes`) di validasi pengaturan, bukan wajib — klien/tes lama yang tidak mengirimnya tetap menyimpan nilai sekarang. `SaveAttendanceSettings::handle()` mendapat parameter ke-4.
- 2026-10-06 — Tahap 1: pemeriksaan "jadwal milik guru" (`assertOwnLesson`) ada di `ScanController`, bukan di aksi domain, karena itu soal izin peran; aturan waktu tetap di `ScanWindow`/`MarkLessonPresence`.
- 2026-10-06 — Tahap 1: dua tes lama guru (`LessonAttendanceTest`) diperbarui: pelajaran guru kini harus ada di jadwal (`attendanceTeach`), bukan sekadar mengampu mapel.

### Temuan
- Guru yang memindai kelas yang tidak ia ampu tetap ditolak lebih dulu oleh `recordableClassRule()` (kunci `class_id`), bukan oleh pemeriksaan jadwal; pesan "bukan jadwal mengajar Anda" hanya muncul untuk kelas miliknya di slot yang bukan miliknya.
- Absensi Kelas (`SaveOwnLessonAttendance`) sudah terikat jam, tetapi scan (`MarkLessonPresence`) tidak: dua jalur untuk hal yang sama dengan aturan berbeda.

### Hasil akhir
Diisi saat semua tahap selesai.
