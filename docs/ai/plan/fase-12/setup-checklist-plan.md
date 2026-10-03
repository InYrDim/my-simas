# Checklist persiapan sekolah di Beranda: langkah berikutnya dihitung dari data

> **Status dokumen:** Selesai
> **Dibuat:** 2026-10-03 · **Diperbarui:** 2026-10-03 · **Branch:** `feat/setup-checklist`

## Context
Peta dependensi Master Data (`docs/architecture/master-data-dependencies.md`) menunjukkan urutan isi yang aman: Profil Sekolah → Tahun Ajaran → Jurusan/Ruangan/Mapel/Guru → Kelas → Siswa berkelas. Sekolah baru tidak diberi tahu urutan itu, jadi mudah macet: Kelas tidak bisa dibuat karena Tingkat belum ada (Tingkat hanya di-seed saat profil disimpan), atau jurusan ditambah setelah kelas dibuat sehingga datanya bercampur. Beranda admin hanya menampilkan angka akun.

## Goal
Setelah ini, Beranda admin sekolah menampilkan kartu "Persiapan sekolah": daftar langkah dengan status, satu langkah ditandai sebagai berikutnya, dan tiap langkah menaut ke halamannya. Statusnya dihitung dari data sekolah, tanpa tabel atau penyimpanan baru, dan kartu hilang sendiri setelah semua langkah wajib selesai.

## Batasan masalah

**Dalam cakupan**
- Query baca `SetupChecklist` di Core, prop `setup` di Beranda (hanya pemegang `core.master.manage`), komponen React, tes feature dan satu tes browser, pembaruan dokumentasi.
- Langkah inti Master Data: profil, tahun ajaran, jurusan, ruangan, mapel, guru, kelas, siswa berkelas, ekskul.

**Di luar cakupan**
- Langkah Akademik (wali kelas, pengampu, jam pelajaran), akun siswa/guru, WhatsApp.
- Menyembunyikan atau melipat kartu secara manual, preferensi per pengguna, migrasi, cache.
- Komponen `Progress` baru di Shared (tidak ada; dipakai teks hitungan).

**Asumsi**
- "Profil selesai" = `Grade` sudah ada, karena Tingkat hanya di-seed oleh `SaveSchoolProfile` → `SyncDefaultGrades`. Itu penghalang nyata bagi Kelas.
- Sekolah yang profilnya belum pernah disimpan dihitung memakai jenjang bawaan `SchoolProfile::current()` (SMA) untuk aturan jurusan.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Semua tetap internal Core; kontrak tidak berubah (sumber: `modules/Core/CONTRACT.md`). Data tenant hanya lewat Eloquent, tanpa `DB::table()` (sumber: `AGENTS.md`).
- Beranda memakai `Gate`, bukan policy; data tambahan hanya untuk pemegang izin dan `null` untuk lainnya (sumber: `CONTRACT.md` Core § Surfaces).
- UI dasar dari `modules/Shared/resources/js/components/ui`; URL lewat Wayfinder (sumber: `.ai/rules/js.md`).
- Jangan memakai `SchoolProfile::current()` untuk membaca: ia `firstOrCreate` (efek samping saat membuka Beranda).

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-03 | Fitur yang dipilih dari analisis dependensi: checklist persiapan sekolah | user |
| 2026-10-03 | Cakupan langkah: inti Master Data saja | user |
| 2026-10-03 | Setelah semua langkah wajib selesai kartu hilang otomatis, dihitung dari data | user |
| 2026-10-03 | Jurusan wajib hanya untuk jenjang yang memakainya (`SchoolLevel::hasMajors()`); kelas menunggu jurusan bila wajib | hasil baca kode |
| 2026-10-03 | Hanya `admin-sekolah` (`core.master.manage`) melihat kartu | hasil baca kode |

## Desain

### Aturan langkah
"N dari M selesai" hanya menghitung langkah wajib.

| Kunci | Langkah | Selesai bila | Wajib | Menunggu |
| --- | --- | --- | --- | --- |
| `profile` | Simpan profil sekolah | ada `Grade` | ya | – |
| `year` | Buat dan aktifkan tahun ajaran | ada `AcademicYear` aktif | ya | – |
| `majors` | Tambah jurusan | ada `Major` | bila `SchoolLevel::hasMajors()` | – |
| `rooms` | Tambah ruangan | ada `Room` | opsional | – |
| `subjects` | Tambah mata pelajaran | ada `Subject` | ya | – |
| `teachers` | Tambah guru | ada `Teacher` | ya | – |
| `classes` | Buat kelas | ada `ClassGroup` di tahun aktif | ya | `profile`, `year`, `majors` bila wajib |
| `students` | Tambah dan tempatkan siswa | ada siswa aktif ber-kelas di tahun aktif | ya | `classes` |
| `extracurriculars` | Tambah ekstrakurikuler | ada `Extracurricular` | opsional | – |

Status: `done`, `next` (langkah wajib pertama yang belum selesai dan tidak terkunci; satu saja), `todo`, `blocked` (menunggu prasyarat, hint menyebutnya), `optional`.

### Core
- `modules/Core/app/Domain/Queries/SetupChecklist.php` (baru): `forCurrentSchool(): ?array` mengembalikan `{done, total, unplaced, steps[]}` atau `null` bila semua langkah wajib selesai.
- `BerandaController`: prop `setup` = hasil query bila `Gate::allows('core.master.manage')`, selain itu `null`.

### Frontend
- `modules/Core/resources/js/Components/SetupChecklist.tsx` (baru): daftar bergaris yang mengikuti gaya Beranda (tanpa kartu), tautan lewat Wayfinder; tautan `students` ke Penempatan Siswa `?kelas=belum` bila `unplaced > 0`. Jurusan menaut ke `core.master.grades`.

### Alur
1. Admin sekolah baru membuka Beranda: langkah "Simpan profil sekolah" ditandai berikutnya, langkah lain menunggu atau bisa dikerjakan.
2. Tiap langkah selesai, hitungan naik dan langkah berikutnya bergeser.
3. Setelah semua langkah wajib selesai, kartu tidak lagi muncul.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Dikerjakan berurutan tanpa berhenti; tiap tahap ✅ setelah buktinya lulus.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Dokumen plan, `SetupChecklist` + tes feature | ✅ | `SetupChecklist` (`Domain/Queries`) dan `SetupChecklistTest` (9 kasus). |
| 1 | Prop `setup`, komponen React, Wayfinder | ✅ | Prop `setup` di `BerandaController` (gate `core.master.manage`), `SetupChecklist.tsx`, tiga tes baru di `BerandaTest`. |
| 2 | Tes browser, dokumentasi | ✅ | `tests/Browser/SetupChecklistTest.php` (3 tes); `CONTRACT.md` Core, dokumen arsitektur, `AGENTS.md`. |

## Tasks

### Tahap 0 — Query dan tes
- [x] Salin plan ke `docs/ai/plan/fase-12/setup-checklist-plan.md` (baru).
- [x] `SetupChecklist` — `modules/Core/app/Domain/Queries/SetupChecklist.php` (baru).
- [x] Tes feature — `modules/Core/tests/Feature/SetupChecklistTest.php` (baru).

**Selesai bila:** semua langkah dan statusnya terhitung benar dari data. Bukti: `php artisan test --compact modules/Core/tests/Feature/SetupChecklistTest.php` → hijau.

### Tahap 1 — Beranda
- [x] Prop `setup` dan gate — `modules/Core/app/Http/Controllers/BerandaController.php`.
- [x] Komponen dan penyisipan — `modules/Core/resources/js/Components/SetupChecklist.tsx` (baru), `modules/Core/resources/js/Pages/Core/Beranda.tsx`.
- [x] Tes Beranda: prop `setup` untuk admin, `null` untuk guru dan setelah lengkap — `modules/Core/tests/Feature/BerandaTest.php` (tambahan).

**Selesai bila:** admin sekolah baru melihat kartu di Beranda dan guru tidak. Bukti: `php artisan test --compact modules/Core/tests/Feature/BerandaTest.php` → hijau; `npm run types:check` → bersih; `npm run build` → sukses.

### Tahap 2 — Penutup
- [x] Tes browser — `tests/Browser/SetupChecklistTest.php` (baru).
- [x] Dokumentasi — `modules/Core/CONTRACT.md` (Surfaces: prop Beranda), `docs/architecture/modular-monolith.md`, `AGENTS.md`.

**Selesai bila:** alur terbukti di browser dan dokumen selaras. Bukti: `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/SetupChecklistTest.php` → hijau; `composer deptrac` → 0 pelanggaran; `vendor/bin/phpstan analyse` → 0 galat.

## Batas tindakan
**Selalu:** `vendor/bin/pint --dirty --format agent` dan tes modul sebelum menandai tahap ✅; perbarui tabel status dan Log keputusan.
**Tanya dulu:** menambah dependensi, tabel, atau izin; menambah langkah di luar inti Master Data; mengubah kontrak Core.
**Jangan:** `DB::table()` untuk data tenant; memanggil `SchoolProfile::current()` untuk membaca; menghapus atau melemahkan tes; commit/push tanpa diminta.

## Pengujian
- Feature: sekolah kosong, profil → tahun, tahun draf vs aktif, kelas tahun non-aktif, jurusan wajib vs opsional menurut jenjang, siswa berkelas vs belum ditempatkan, gate per peran, semua selesai → `null`, tanpa efek samping pada `SchoolProfile`, isolasi tenant.
- Browser: admin sekolah baru melihat kartu, mengeklik langkah berikutnya, mendarat di halaman yang benar, tanpa error JS.

## Verifikasi end-to-end
1. `php artisan test --compact modules/Core` dan `tests/Architecture`.
2. `composer deptrac`, `vendor/bin/phpstan analyse`, `vendor/bin/pint --dirty --format agent`.
3. `npm run check`, `npm run types:check`, `npm run build`.
4. `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/SetupChecklistTest.php`.
5. Manual: sekolah baru → kartu menampilkan langkah berikutnya; isi tiap langkah → hitungan naik, kartu hilang di akhir; login guru → tidak ada kartu.

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-03 — Plan dibuat dari analisis dependensi; cakupan dan perilaku setelah lengkap diputuskan user.
- 2026-10-03 — Tipe `SetupChecklistData` diekspor dari `Components/SetupChecklist.tsx` dan dipakai `Beranda.tsx`, bukan ditulis inline di Beranda seperti rencana: komponen butuh tipenya sendiri, dan satu definisi menghindari salinan.
- 2026-10-03 — Satu hitungan tambahan di luar plan: `unplaced` dikirim di tingkat atas `setup`, supaya tautan langkah Siswa bisa memilih Penempatan Siswa `?kelas=belum` tanpa bentuk langkah yang berbeda-beda.
- 2026-10-03 — Sebelum profil disimpan, jurusan dihitung wajib mengikuti jenjang bawaan `SchoolProfile::current()` (SMA); total langkah wajib bisa turun dari 7 ke 6 bila sekolah lalu memilih jenjang tanpa jurusan.

### Temuan
- `SchoolProfile::query()->first()?->level ?? ...` ditolak PHPStan (`nullsafe.neverNull`); ditulis dengan percabangan eksplisit.
- Tes browser lulus walau `public/hot` ada: `tests/Pest.php` menimpa hot file, jadi tes memakai hasil build.

### Hasil akhir
Beranda admin sekolah menampilkan "Persiapan sekolah": sembilan langkah Master Data (tujuh wajib untuk jenjang SMA/SMK, enam untuk SD/SMP) dengan satu langkah berikutnya, dihitung dari data tanpa tabel baru, dan hilang sendiri setelah semua langkah wajib selesai. Guru, staf, dan siswa tidak menerimanya.

Bukti: `php artisan test --compact modules/Core tests/Architecture` → 476 lulus; `tests/Browser/SetupChecklistTest.php` → 3 lulus; `composer deptrac` → 0 pelanggaran; PHPStan 0 galat; `npm run check`, `npm run types:check`, `npm run build` → bersih.

Belum dibuktikan: tampilan visual dan responsif di HP (diuji otomatis hanya isinya, bukan rupanya); suite penuh `php artisan test --compact` dan CI GitHub setelah branch ini digabung.
