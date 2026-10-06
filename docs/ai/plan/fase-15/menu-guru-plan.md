# Menu guru yang jelas: satu jadwal, satu pintu absensi, tanpa menu admin

> **Status dokumen:** Selesai
> **Dibuat:** 2026-10-07 · **Diperbarui:** 2026-10-07 · **Branch:** `staging`

## Context
Sidebar guru (diisi `CoreServiceProvider::registerNavigation()` dan `AttendanceServiceProvider::registerNavigation()`) rancu. Hasil analisis 2026-10-07:

1. Tiga "jadwal": **Jadwal Mengajar** (Core, mingguan), **Jadwal Hari Ini** (Attendance, di dalam Kelas Saya) dan **Jadwal Pelajaran** (Core › Akademik, seluruh sekolah).
2. **Absensi Saya** untuk guru adalah halaman kosong "Segera hadir" (`SoonController`), dan namanya bentrok dengan Absensi Saya milik siswa.
3. Mencatat absensi punya tiga pintu di Kelas Saya: Absensi Kelas, Pindai QR, Riwayat Absensi (jalur koreksi yang namanya terdengar seperti laporan).
4. "Kelas Saya" dipakai guru dan siswa dengan isi berbeda total, dan dekat dengan "Kelas" (Master Data) serta "Siswa" (Warga Sekolah).
5. Guru punya `core.master.view` dan `core.academic.view` sehingga melihat 12 halaman admin baca-saja (Master Data, Warga Sekolah, Akademik termasuk Jam Pelajaran) dan Statistik & Laporan seluruh sekolah.

## Goal
Setelah ini, guru melihat menu pendek: Beranda, Profil Saya, Jadwal Saya (tab Hari Ini dan Minggu Ini), Kelas Mengajar (Kelas Aktif, Absensi Kelas dengan tab Isi/Koreksi, Pindai QR). Tidak ada halaman kosong, tidak ada Data Induk atau Statistik sekolah. Siswa tetap melihat "Kelas Saya".

## Batasan masalah

**Dalam cakupan**
- Hapus placeholder "Absensi Saya" guru (route `attendance.soon`, `SoonController`, `Soon.tsx`).
- Satukan jadwal guru jadi satu halaman Attendance "Jadwal Saya" dengan tab Hari Ini / Minggu Ini; hapus entri menu "Jadwal Mengajar" Core.
- Kelas Mengajar: anak menu menjadi Kelas Aktif, Absensi Kelas, Pindai QR; Riwayat Absensi menjadi tab "Koreksi" di halaman Absensi Kelas.
- Ganti nama menu guru menjadi "Kelas Mengajar"; menu siswa tetap "Kelas Saya".
- Cabut `core.master.view` dan `core.academic.view` dari role `guru` (`roles.php`).

**Di luar cakupan**
- Menggabungkan isi halaman Absensi Kelas dan Riwayat jadi satu halaman: hanya tab-bar tautan, URL lama tetap.
- Halaman daftar siswa/guru baca-saja khusus guru: Kelas Aktif sudah memuat siswa kelasnya.
- Menghapus halaman Core `/saya/jadwal-mengajar` dan tesnya: tetap ada (tanpa tautan menu) sebagai cadangan; hapus di fase lain.
- Statistik/laporan per kelas untuk guru.

**Asumsi**
- Absensi ada di setiap paket, jadi guru selalu punya Jadwal Saya.
- Sekolah yang sudah berjalan memakai `php artisan roles:sync` (`syncPermissions` mencabut izin yang dibuang).

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Attendance memakai Core hanya lewat kontrak publik (`TeacherSchedule::week/onDay`); Core tidak mengimpor Attendance (sumber: `AGENTS.md` › Modular Monolith).
- Menu mengikuti izin: tautan ke halaman yang 403 tidak boleh tampil (sumber: `.ai/rules/js.md` › UX: UI mengikuti izin peran).
- Pakai komponen `@shared/components/ui`, jangan buat komponen dasar (sumber: `.ai/rules/js.md`).
- Setelah mengubah PHP: `vendor/bin/pint --dirty --format agent`.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-07 | Selesaikan semua temuan rancu sekaligus dalam satu fase | user |
| 2026-10-07 | Jadwal Saya dimiliki Attendance (butuh daftar centang hari ini); Core hanya menyisakan route lama tanpa menu | hasil baca kode |
| 2026-10-07 | Guru kehilangan Data Induk dan Statistik & Laporan sekolah | user (minta semua diselesaikan) |

## Desain

### Attendance
- `TodayController` → `MyScheduleController` (route `attendance.schedule`, `GET /absensi/jadwal-saya`), halaman `Attendance/MySchedule`: props lama (`date`, `banner`, `lessons`) ditambah `week` (hasil `TeacherSchedule::week()` sebagai array). Tab "Hari Ini" = isi `Today.tsx` lama; tab "Minggu Ini" = daftar per hari seperti `Core/Me/Timetable.tsx`. Centang jadi `attendance.schedule.check`.
- Menu: `Jadwal Saya` (group Saya, order 13, izin `attendance.class.record`, ikon `calendar-clock`). `Kelas Mengajar` (order 14): Kelas Aktif, Absensi Kelas, Pindai QR. Menu siswa tetap `Kelas Saya`.
- Komponen baru `resources/js/Components/ClassRollTabs.tsx` (tab tautan Isi Absensi / Koreksi) dipakai di halaman Absensi Kelas dan Riwayat.
- Hapus `SoonController`, `Soon.tsx`, route `attendance.soon`, menu "Absensi Saya" guru.

### Core
- `CoreServiceProvider`: hapus entri "Jadwal Mengajar". Route `core.me.timetable` tetap.

### Identity
- `modules/Identity/config/roles.php`: `guru` = `core.me.view`, `core.teaching.view`, `attendance.class.record`.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 1 | Hapus Absensi Saya kosong, Kelas Mengajar + tab Isi/Koreksi | ✅ | Tab memakai `Tabs` + `Link`; pesan penolakan scan kini menyebut tab Koreksi |
| 2 | Jadwal Saya satu halaman (Hari Ini + Minggu Ini) | ✅ | Route `attendance.schedule`, `/absensi/jadwal-saya` |
| 3 | Guru tanpa Data Induk/Statistik + dokumentasi | ✅ | Tes Core/Ppdb yang memakai guru sebagai "bisa lihat, tak bisa ubah" dialihkan ke `staf-tu` |

## Tasks

### Tahap 1 — Pintu absensi guru
- [x] Hapus placeholder — `modules/Attendance/app/Http/Controllers/SoonController.php`, `modules/Attendance/resources/js/Pages/Attendance/Soon.tsx`, route `soon` di `modules/Attendance/routes/web.php`
- [x] Menu: ganti nama jadi Kelas Mengajar, anak menu Kelas Aktif / Absensi Kelas / Pindai QR — `modules/Attendance/app/Infrastructure/Providers/AttendanceServiceProvider.php`
- [x] Tab Isi/Koreksi — `modules/Attendance/resources/js/Components/ClassRollTabs.tsx` (baru), halaman Absensi Kelas dan Riwayat di `modules/Attendance/resources/js/Pages/Attendance/`
- [x] Tes: perbarui menu guru dan halaman yang dibuka — `modules/Attendance/tests/Feature/AttendanceAccessTest.php`

**Selesai bila:** guru tidak punya "Absensi Saya"; Kelas Mengajar berisi tiga anak menu; `/absensi/segera-hadir` 404. Bukti: `php artisan test --compact modules/Attendance` → hijau.

### Tahap 2 — Jadwal Saya
- [x] Controller dan halaman gabungan — `MyScheduleController.php` (baru, ganti `TodayController.php`), `Pages/Attendance/MySchedule.tsx` (baru, ganti `Today.tsx`)
- [x] Route `jadwal-saya` dan nama `attendance.schedule*` — `modules/Attendance/routes/web.php`
- [x] Hapus entri "Jadwal Mengajar" — `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`
- [x] Tes: tab minggu memuat jadwal, centang tetap jalan — `modules/Attendance/tests/Feature/MyClassesTest.php`, `modules/Core/tests/Feature/TenantNavigationTest.php`

**Selesai bila:** satu menu Jadwal Saya menampilkan hari ini dan minggu ini. Bukti: `php artisan test --compact modules/Attendance modules/Core/tests/Feature/TenantNavigationTest.php` → hijau.

### Tahap 3 — Izin guru dan dokumentasi
- [x] Cabut dua izin guru — `modules/Identity/config/roles.php`
- [x] Perbarui tes — `modules/Identity/tests/Feature/DefaultRolesTest.php`, `modules/Core/tests/Feature/TenantNavigationTest.php`, tes lain yang bergantung (cek dengan suite)
- [x] Dokumentasi — `modules/Attendance/CONTRACT.md`, `modules/Core/CONTRACT.md`, `docs/architecture/modular-monolith.md`, `AGENTS.md` (bagian Kelas Saya guru)

**Selesai bila:** guru hanya melihat Beranda, Profil Saya, Jadwal Saya, Kelas Mengajar. Bukti: `php artisan test --compact modules/Identity modules/Core modules/Attendance` → hijau.

## Batas tindakan
**Selalu**
- Jalankan Pint dan tes modul sebelum menandai tahap selesai; perbarui status tabel.

**Tanya dulu**
- Mengubah signature kontrak publik; menghapus halaman Core `/saya/jadwal-mengajar`.

**Jangan**
- Menghapus tes tanpa pengganti; menambah FK; commit atau push tanpa diminta.

## Pengujian
- **Tahap 1:** menu guru; halaman `kelas-saya`, `absen-kelas`, `riwayat` terbuka; `segera-hadir` tidak ada; menu siswa tak berubah.
- **Tahap 2:** guru melihat jadwal hari ini dan seminggu; centang selesai tetap bekerja; peran lain 403.
- **Tahap 3:** daftar izin guru; menu guru tanpa Data Induk; halaman master 403 untuk guru.

## Verifikasi end-to-end
1. `php artisan test --compact modules/Identity modules/Core modules/Attendance` → hijau.
2. Masuk sebagai guru → sidebar hanya Beranda, Profil Saya, Jadwal Saya, Kelas Mengajar (+ Ganti Kata Sandi).
3. Setelah rilis: `php artisan roles:sync`.

## Catatan pelaksanaan

### Log keputusan
- 2026-10-07 — Plan dibuat dari analisis menu guru.
- 2026-10-07 — Semua tahap dikerjakan berurutan tanpa jeda persetujuan karena user meminta semuanya selesai.
- 2026-10-07 — Pesan penolakan "Riwayat Absensi" diganti "tab Koreksi" (`ScanWindow`, `SaveOwnLessonAttendance`, `ClassRoll`, `Settings`) agar sesuai nama baru.

### Temuan
- Banyak tes Core memakai `guru` sebagai peran "lihat saja"; peran itu kini `staf-tu`, dan tes baru memastikan guru 403 di Master, Akademik, Statistik & Laporan.
- `TenantPasswordResetTest` (link memakai `http://simas.biz.id`, bukan `localhost`) gagal juga tanpa perubahan ini: masalah lingkungan `.env`, di luar fase.

### Hasil akhir
Menu guru: Beranda, Profil Saya, Jadwal Saya, Kelas Mengajar. `php artisan test --compact modules` → 1476 lulus, 1 gagal (lingkungan, lihat Temuan). Setelah rilis jalankan `php artisan roles:sync`. Tersisa: halaman Core `/saya/jadwal` tanpa menu bisa dihapus di fase lain.
