# Statistik dan Laporan sekolah dari data nyata

> **Status dokumen:** Selesai
> **Dibuat:** 2026-10-02 · **Diperbarui:** 2026-10-02 · **Branch:** `feat/core-insight`

## Context
Master Data (fase 3), Akademik (fase 5), dan Impor Data (fase 6) di modul **Core** sudah tersimpan di database. **Statistik & Laporan** (`/statistik-laporan/*`) masih mockup: `modules/Core/app/Http/Controllers/MasterDataController.php::statistics()` dan `::reports()` mengirim angka tetap dari `modules/Core/app/Infrastructure/Mock/InsightMockData.php` (48 guru, kehadiran 94,6%, tiga baris "Terakhir dibuat"). Di `modules/Core/resources/js/Pages/Core/Insight/Reports.tsx` tombol PDF/Excel hanya memunculkan tulisan "Contoh saja", dan daftar periode di-hard-code.

Route-nya hanya di balik `auth` tanpa gate izin (`modules/Core/routes/web.php`), padahal laporan memuat data pribadi siswa (NIS, NISN).

`modules/Core/CONTRACT.md` sudah mencatat arahnya: Core memiliki cangkang dan registry; modul fitur (Absensi, PPDB) kelak mendaftarkan laporan dan angkanya lewat kontrak Core, seperti modul mendaftarkan menu ke Platform. `modules/Core/app/Contracts` masih kosong. Fase ini membangun registry itu dan mengisi halaman dengan data Core yang nyata. Absensi dan PPDB tetap dikerjakan paling akhir (keputusan user 2026-10-02); Integrasi menyusul di fase tersendiri.

## Goal
Setelah ini, pengguna sekolah yang berhak bisa membuka **Statistik** dan melihat jumlah siswa aktif, guru, rombel, sebaran per tingkat dan jenis kelamin dari database sekolahnya; dan membuka **Laporan**, memilih tahun ajaran, lalu mengunduh CSV atau membuka tampilan cetak untuk Daftar Siswa per Kelas, Mutasi Siswa, dan Beban Mengajar Guru. Bagian milik Absensi/PPDB dan Jadwal Pelajaran terlihat sebagai "Segera hadir". Banner "Tampilan contoh" hilang dari kedua halaman.

## Batasan masalah

**Dalam cakupan**
- Kontrak pertama Core: registry laporan dan registry statistik di `modules/Core/app/Contracts`, dipakai Core sendiri.
- Statistik nyata: 3 kartu (siswa aktif, guru & tendik, rombel tahun aktif) dan 2 panel (siswa per tingkat, jenis kelamin).
- Tiga laporan nyata: `student-list`, `student-mutation`, `teaching-load`; unduh **CSV** dan **tampilan cetak** (HTML, "Simpan sebagai PDF" lewat dialog cetak browser).
- Pilihan periode = tahun ajaran sekolah (bawaan: tahun aktif).
- Entri "Segera hadir" (nonaktif): kartu + panel kehadiran, grup laporan Kehadiran dan PPDB, laporan Jadwal Pelajaran.
- Gate izin pada route dan menu; tes Pest (feature, browser); pembersihan mock; dokumentasi.

**Di luar cakupan**
- Berkas `.pdf` / `.xlsx` asli — butuh dependensi baru; user memilih CSV + halaman cetak.
- Riwayat "Terakhir dibuat" dan penyimpanan berkas laporan — user memilih dihapus; laporan dibuat saat diminta.
- Laporan lewat antrean (queue) — sinkron; ukuran satu sekolah cukup kecil.
- Data Absensi dan PPDB — modulnya belum ada; hanya placeholder.
- Laporan Jadwal Pelajaran — belum ada tabel jadwal (`period_slots` hanya templat bel); placeholder.
- "Siswa masuk" pada Mutasi Siswa — tanggal masuk tidak dicatat; laporan hanya memuat pindah kelas, lulus, pindah sekolah, keluar.
- Filter per kelas, periode bulanan/semester, grafik tren — tidak ada data berbasis tanggal di Core.
- Izin baru; halaman Integrasi (tetap mock).

**Asumsi**
- Setiap siswa yang pernah ditempatkan punya baris `student_class_history` untuk tahun ajaran itu (ditulis `SaveStudent`). Daftar siswa dan mutasi dibaca dari riwayat, sehingga tahun yang sudah lewat tetap bisa dilaporkan.
- Catatan riwayat hanya bernilai `Kelas aktif`, `Pindah kelas`, `Lulus`, `Pindah sekolah`, `Keluar` (`modules/Core/app/Domain/Actions/SaveStudent.php`). Mutasi = baris dengan catatan selain `Kelas aktif`. Periksa di Tahap 2; bila ada nilai lain, catat di Temuan.
- Ketiga peran bawaan memegang `core.master.view` dan `core.academic.view` (`modules/Identity/config/roles.php`), jadi gate baru tidak mengubah siapa yang melihat menu.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Permukaan publik hanya `app/Contracts/**`; kontrak tidak membuka model Eloquent — pakai DTO/skalar; implementasi di `Infrastructure/` atau `Domain/`, di-bind di provider modul (sumber: `AGENTS.md` § Public surface, `docs/architecture/modular-monolith.md`).
- `CorePublic` boleh mengakses `Core`, `Shared`, Laravel, PHP native saja; modul fitur hanya boleh memakai `CorePublic` (sumber: `deptrac.php` baris 173–185). Core tidak pernah mengimpor modul fitur.
- Data tenant hanya lewat Eloquent, bukan `DB::table()`; tanpa tabel baru, tanpa FK (sumber: `AGENTS.md` § Tenancy traps).
- Tidak menambah dependensi Composer/npm (sumber: `AGENTS.md` § Application Structure).
- UI dasar dari `modules/Shared/resources/js/components/ui`; URL dari Wayfinder (sumber: `.ai/rules/js.md`). Aktifkan skill `modular-monolith`, `laravel-best-practices`, `inertia-react-development`, `wayfinder-development`, `writing-tests`, `testing-best-practices` saat menyentuh bagiannya.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-02 | Format: CSV + tampilan cetak HTML; tanpa dependensi PDF/Excel | user |
| 2026-10-02 | Registry lewat kontrak Core dibangun sekarang; laporan dan angka Core sendiri didaftarkan lewat kontrak itu | user |
| 2026-10-02 | Bagian Absensi/PPDB ditampilkan sebagai "Segera hadir" (bukan disembunyikan) | user |
| 2026-10-02 | Tabel "Terakhir dibuat" dihapus; tanpa riwayat, tanpa berkas tersimpan | user |
| 2026-10-02 | Tanpa izin baru: grup route + menu memakai `core.master.view`; tiap laporan membawa izinnya sendiri (`teaching-load` = `core.academic.view`) — menghindari celah "tenant lama tidak mendapat izin baru" | hasil baca kode |
| 2026-10-02 | Periode = tahun ajaran (`?tahun=<id>`), bawaan tahun aktif lalu tahun terbaru | hasil desain |
| 2026-10-02 | Kontrak hanya memuat sisi **daftar** (`register`); sisi baca (katalog, cari) tetap di kelas internal Core | hasil desain |
| 2026-10-02 | Placeholder "Segera hadir" berupa daftar label di config Core; entri hilang sendiri begitu ada laporan/angka terdaftar dengan `key` yang sama | hasil desain |
| 2026-10-02 | Jadwal Pelajaran menjadi placeholder (belum ada tabel jadwal) | hasil baca kode |
| 2026-10-02 | CSV memakai `sep=;` tanpa BOM (konvensi templat impor fase 6) dan menetralkan sel yang diawali `=`, `+`, `-`, `@` (injeksi rumus) | hasil desain |

## Desain

### Core — permukaan publik (`modules/Core/app/Contracts`, semua baru)
- `ReportRegistry` — `register(string $module, string $report): void` (`class-string<Report>`; dipanggil dari provider modul pemilik saat boot).
- `Report` — `definition(): ReportDefinition`, `table(ReportPeriod $period): ReportTable`.
- `StatisticsRegistry` — `register(string $module, string $provider): void` (`class-string<StatisticsProvider>`).
- `StatisticsProvider` — `figures(?ReportPeriod $period): list<StatFigure>`, `panels(?ReportPeriod $period): list<StatPanel>` (`null` = sekolah belum punya tahun ajaran).
- `DTOs/ReportDefinition` — `key`, `group`, `name`, `description`, `?permission`, `order`.
- `DTOs/ReportPeriod` — `academicYearId`, `name`, `startsOn`, `endsOn` (string `Y-m-d`); modul fitur memfilter datanya sendiri dengan tanggal ini, tanpa menyentuh model `AcademicYear`.
- `DTOs/ReportTable` — `title`, `columns: list<string>`, `rows: list<list<string|int|float|null>>`.
- `DTOs/StatFigure` — `key`, `label`, `value`, `?hint`.
- `DTOs/StatPanel` — `key`, `title`, `kind` (`bars`|`share`), `points: list<array{label: string, value: int|float}>`, `?note`.

DTO `final readonly`, hanya skalar/array. Tanpa event.

### Core — internal (baru kecuali disebut)
- `Infrastructure/Insight/DefaultReportRegistry.php`, `DefaultStatisticsRegistry.php` — registry in-memory, pola `modules/Platform/app/Infrastructure/Navigation/DefaultTenantNavigation.php`: disaring per request oleh `TenantModules::isEnabled($module)` dan `Gate::allows($permission)`. Method baca internal: `catalogue()` (grup → laporan, digabung placeholder dari config dengan `available: false`), `find(string $key): ?Report`; `figures()/panels()` untuk statistik. Di-bind singleton + alias ke kontrak di `CoreServiceProvider::register()`.
- `config/insight.php` — `upcoming.reports` (`attendance-monthly`, `attendance-class`, `ppdb-applicants`, `ppdb-result`, `schedule`: key, group, name, description — teks dari `InsightMockData`) dan `upcoming.figures` / `upcoming.panels` (kartu "Rata-rata kehadiran", panel "Kehadiran 6 bulan terakhir"). Label saja, tanpa kelas modul lain.
- `Domain/Reports/StudentListReport.php` — `student_class_history` tahun terpilih + siswa; kolom Kelas, NIS, NISN, Nama, L/P, Keterangan; urut kelas lalu nama. Izin `core.master.view`.
- `Domain/Reports/StudentMutationReport.php` — riwayat tahun terpilih dengan catatan ≠ `Kelas aktif`; kolom NIS, Nama, Kelas, Keterangan, Tanggal (`updated_at` pada zona waktu tenant dari `TenantContext::currentOrFail()->timezone`). Izin `core.master.view`.
- `Domain/Reports/TeachingLoadReport.php` — `TeachingAssignment` pada rombel tahun terpilih (`with` teacher/subject/classGroup, dikelompokkan di PHP); satu baris per guru: Nama, NIP, Tugas, Jumlah rombel, Jumlah mapel, Jam per minggu. Izin `core.academic.view`.
- `Domain/Statistics/SchoolStatistics.php` (implements `StatisticsProvider`) — kartu: siswa aktif (`Student` status `active`), guru & tendik (`Teacher`), rombel tahun terpilih; panel `bars` siswa aktif per tingkat (semua `Grade` urut `sort_order`, termasuk yang 0) dan panel `share` jenis kelamin. Query agregat lewat builder Eloquent (`withCount`, `selectRaw ... groupBy`), bukan `DB::table()`.
- `Domain/Actions/ResolveReportPeriod.php` — `?int $yearId` → `ReportPeriod` (tahun diminta → tahun aktif → tahun terbaru → `null` bila sekolah belum punya tahun ajaran).
- `Infrastructure/Csv/CsvWriter.php` — menulis `sep=;`, judul, baris (`fputcsv` ke `php://output`, CRLF), menetralkan injeksi rumus. Pasangan `Infrastructure/Csv/CsvReader.php` yang sudah ada.
- `Http/Controllers/StatisticsController.php` (invokable) → `Core/Insight/Statistics` dengan props `period`, `figures`, `panels` (tiap entri ber-`available`).
- `Http/Controllers/ReportController.php` — `index` (props `groups`, `years`, `yearId`), `download` (`response()->streamDownload`, pola `ImportController::template()`; nama berkas `<key>-<tahun>.csv`), `printView` → `Core/Insight/ReportPrint`. Key tak dikenal / placeholder / modul nonaktif → 404; izin laporan gagal → 403; sekolah tanpa tahun ajaran → katalog tampil dengan `EmptyState`, unduh 404.
- Keduanya memakai `modules/Core/app/Http/Concerns/RendersMasterPage.php`.
- `CoreServiceProvider` (ada): `mergeConfigFrom` insight, bind registry, `registerInsight()` mendaftarkan 3 laporan + `SchoolStatistics`; menu "Statistik & Laporan" mendapat `'permission' => 'core.master.view'`.

**Route** — `modules/Core/routes/web.php`, grup `prefix('statistik-laporan')->name('core.insight.')->middleware('can:core.master.view')`:

| Method & URL | Nama route | Aksi |
| --- | --- | --- |
| `GET statistik` | `core.insight.statistics` (tetap) | `StatisticsController` |
| `GET laporan` | `core.insight.reports` (tetap) | `ReportController@index` |
| `GET laporan/{report}/unduh` | `core.insight.reports.download` | CSV |
| `GET laporan/{report}/cetak` | `core.insight.reports.print` | tampilan cetak |

**Halaman** (`modules/Core/resources/js/Pages/Core/Insight/`)
- `Statistics.tsx` (ada) — merender `figures` dengan `StatCard` dan `panels` menurut `kind` (markup batang dan pembagian yang sekarang dipertahankan); entri `available: false` diredupkan dengan `Badge` "Segera hadir"; `EmptyState` bila belum ada siswa (hindari bagi-nol pada persen); `mock={false}`.
- `Reports.tsx` (ada) — `OptionSelect` tahun ajaran dari `years` (state lokal); tiap laporan: tombol **CSV** (`<a href>` biasa ke route unduh, bukan `Link` Inertia) dan **Cetak** (tab baru); placeholder: `Badge` "Segera hadir" tanpa tombol; hapus `periods`, `notice`, dan bagian "Terakhir dibuat"; `mock={false}`.
- `ReportPrint.tsx` (baru) — tanpa `TenantShell`: nama sekolah, judul laporan, tahun ajaran, tanggal cetak, tabel dari `@shared/components/ui/table`, tombol "Cetak" (`window.print()`, `print:hidden`), `EmptyState` bila tanpa baris.

**Dipakai ulang**
- Backend: `RendersMasterPage`, `SchoolSummaryResource`, model `Student`/`StudentClassHistory`/`ClassGroup`/`Grade`/`Teacher`/`TeachingAssignment`/`AcademicYear`, enum `AcademicYearStatus`, kontrak Platform `TenantModules`, `TenantContext`.
- Frontend: `Components/MasterPage.tsx`; `@shared/components/page-parts` (`StatCard`, `Panel`, `OptionSelect`, `DataTable`, `EmptyState`); `@shared/components/ui/{badge,button,table}`.
- Tes: `schoolAs()`, `inSchool()`, `yearWithSemesters()`, `classIn()` di `modules/Core/tests/Feature/Support/helpers.php`; `school($slug, $path)`; `schoolMemberSignsIn()`, `inTenant()` di `tests/Browser/Support/school.php`.

### Identity / Platform
- Tidak ada perubahan.

### Alur
1. Admin/guru membuka Statistik & Laporan → Statistik: angka sekolahnya untuk tahun aktif; kartu kehadiran bertanda "Segera hadir".
2. Laporan: memilih tahun ajaran, menekan **CSV** pada "Daftar Siswa per Kelas" → berkas terunduh dan terbuka rapi di Excel.
3. Menekan **Cetak** pada "Beban Mengajar Guru" → tab baru berisi tabel → dialog cetak → simpan sebagai PDF.
4. Grup Kehadiran dan PPDB serta Jadwal Pelajaran terlihat, tanpa tombol.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Fondasi: dokumen plan, branch, kontrak + DTO, registry + config placeholder, gate route + menu | ✅ | 4 kontrak + 5 DTO di `app/Contracts`, `DefaultReportRegistry` / `DefaultStatisticsRegistry`, `config/insight.php`, grup route dan menu digate `core.master.view`. Halaman masih mock. 288 tes Core + arsitektur hijau (19 baru), Deptrac 0 pelanggaran. |
| 1 | Statistik nyata (provider Core, controller, halaman) | ✅ | `ResolveReportPeriod`, `SchoolStatistics`, `StatisticsController`; halaman merender `figures`/`panels` generik, banner mock hilang. `InsightStatisticsTest` (5 kasus). |
| 2 | Laporan: katalog, tiga laporan, unduh CSV, halaman terhubung | ✅ | `StudentListReport`, `StudentMutationReport`, `TeachingLoadReport`, `CsvWriter`, `ReportController@index,download`; halaman memilih tahun ajaran, "Terakhir dibuat" dihapus. `InsightReportsTest` (16 kasus), `CsvWriterTest` (8 kasus). |
| 3 | Tampilan cetak, tes browser, pembersihan mock, dokumentasi | ✅ | `ReportController@printView` + `ReportPrint.tsx`; `tests/Browser/InsightTest.php` (2 tes) dan `tests/E2E/insight.spec.ts` (1 spec); `InsightMockData` dihapus; `CONTRACT.md`, dokumen arsitektur, `AGENTS.md`, memori diperbarui. |

## Tasks

### Tahap 0 — Fondasi
- [x] Buat branch `feat/core-insight`; salin plan ini ke `docs/ai/plan/fase-7/core-insight-plan.md` (baru).
- [x] Kontrak dan DTO — `modules/Core/app/Contracts/{ReportRegistry,Report,StatisticsRegistry,StatisticsProvider}.php`, `modules/Core/app/Contracts/DTOs/{ReportDefinition,ReportPeriod,ReportTable,StatFigure,StatPanel}.php` (baru).
- [x] Registry — `modules/Core/app/Infrastructure/Insight/{DefaultReportRegistry,DefaultStatisticsRegistry}.php` (baru); `modules/Core/config/insight.php` (baru).
- [x] Bind + `mergeConfigFrom`; menu "Statistik & Laporan" `'permission' => 'core.master.view'` — `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`.
- [x] Grup route `statistik-laporan` mendapat `can:core.master.view` (controller masih `MasterDataController`) — `modules/Core/routes/web.php`.
- [x] Tes — `modules/Core/tests/Feature/InsightRegistryTest.php` (baru): laporan terdaftar muncul di katalog menurut grup dan `order`; placeholder ber-`available: false` dan hilang saat key yang sama didaftarkan; laporan modul nonaktif / izin gagal tidak muncul; `find()` → `null` untuk modul nonaktif, key tak dikenal, dan placeholder. `modules/Core/tests/Feature/InsightAccessTest.php` (baru): admin/guru/staf-TU 200, pengguna tanpa peran 403, tamu redirect, untuk kedua halaman.

**Selesai bila:** pengguna tanpa `core.master.view` mendapat 403 di kedua halaman dan registry teruji. Bukti: `php artisan test --compact modules/Core tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 1 — Statistik nyata
- [x] `ResolveReportPeriod` — `modules/Core/app/Domain/Actions/ResolveReportPeriod.php` (baru).
- [x] `SchoolStatistics` + pendaftaran di `registerInsight()` — `modules/Core/app/Domain/Statistics/SchoolStatistics.php` (baru), `CoreServiceProvider.php`.
- [x] `StatisticsController`; route `statistik` menunjuk ke sana; hapus `statistics()` dari `MasterDataController.php` — `modules/Core/app/Http/Controllers/StatisticsController.php` (baru).
- [x] Halaman generik `figures`/`panels`, "Segera hadir", keadaan kosong, `mock={false}` — `modules/Core/resources/js/Pages/Core/Insight/Statistics.tsx`.
- [x] Pindahkan tes "pages still on mock data" dari `/statistik-laporan/statistik` ke `/integrasi/whatsapp` — `modules/Core/tests/Feature/MasterDataPagesTest.php`.
- [x] Tes — `modules/Core/tests/Feature/InsightStatisticsTest.php` (baru): jumlah siswa aktif (lulus/pindah/keluar tidak dihitung), guru, rombel hanya tahun aktif; per tingkat termasuk tingkat kosong, urut `sort_order`; jenis kelamin; sekolah kosong tetap 200; placeholder kehadiran ada; isolasi tenant.

**Selesai bila:** Statistik menampilkan angka dari database tanpa banner "Tampilan contoh". Bukti: `php artisan test --compact modules/Core` → hijau; `npx tsc --noEmit` → bersih.

### Tahap 2 — Laporan + unduh CSV
- [x] `CsvWriter` — `modules/Core/app/Infrastructure/Csv/CsvWriter.php` (baru).
- [x] `StudentListReport`, `StudentMutationReport`, `TeachingLoadReport` + pendaftaran — `modules/Core/app/Domain/Reports/` (baru), `CoreServiceProvider.php`.
- [x] `ReportController@index,download`; route `laporan` dan `laporan/{report}/unduh`; hapus `reports()` dari `MasterDataController.php` — `modules/Core/app/Http/Controllers/ReportController.php` (baru), `modules/Core/routes/web.php`.
- [x] Halaman: pilihan tahun ajaran, tombol CSV (Wayfinder), placeholder, hapus "Terakhir dibuat", `mock={false}` — `modules/Core/resources/js/Pages/Core/Insight/Reports.tsx`.
- [x] Tes — `modules/Core/tests/Unit/CsvWriterTest.php` (baru): baris `sep=;`, kutip untuk sel ber-`;`, sel berawalan `=`/`+`/`-`/`@` dinetralkan. `modules/Core/tests/Feature/InsightReportsTest.php` (baru): katalog (grup, placeholder, daftar tahun, tahun bawaan = aktif); isi CSV ketiga laporan (`streamedContent()`), siswa dibuat lewat `SaveStudent` agar riwayat ada; `?tahun=` tahun lama memakai riwayat tahun itu; mutasi memuat pindah kelas dan lulus, tidak memuat `Kelas aktif`; beban mengajar menjumlah jam per guru; key tak dikenal / placeholder → 404; laporan berizin yang tidak dimiliki pengguna → 403 dan tidak ada di katalog (lihat Log keputusan); `?tahun` milik sekolah lain → jatuh ke bawaan; isolasi tenant.

**Selesai bila:** ketiga laporan terunduh sebagai CSV berisi data sekolah yang benar. Bukti: `php artisan test --compact modules/Core` → hijau; `npx tsc --noEmit` → bersih.

### Tahap 3 — Tampilan cetak + penutup
- [x] `ReportController@printView` + route `laporan/{report}/cetak` — `ReportController.php`, `routes/web.php`.
- [x] `ReportPrint.tsx` (baru) dan tombol "Cetak" di `Reports.tsx`.
- [x] Tes (tambahan di `InsightReportsTest.php`): halaman cetak memuat `columns`/`rows` yang sama dengan CSV; 404/403 sama dengan unduh.
- [x] Tes browser — `tests/Browser/InsightTest.php` (baru; pola `tests/Browser/ImportTest.php`): Statistik menampilkan jumlah siswa buatan tes dan tidak ada "Tampilan contoh"; Laporan menampilkan "Segera hadir"; "Cetak" Daftar Siswa menampilkan nama siswa; `assertNoJavaScriptErrors()`.
- [x] Bersihkan: hapus `modules/Core/app/Infrastructure/Mock/InsightMockData.php`; perbarui docblock `MasterDataController.php` (tinggal WhatsApp) dan komentar "mockup phase" di `routes/web.php`; jalankan `npm run build` (Wayfinder tidak menghapus ekspor lama — temuan fase 6).
- [x] Dokumentasi: `modules/Core/CONTRACT.md` (Public interface, Surfaces, "Explicitly NOT exposed"), `docs/architecture/modular-monolith.md` (baris Core: kontrak tidak lagi kosong), salinan `AGENTS.md`, memori `project_mockup_first.md`.

**Selesai bila:** laporan bisa dicetak dari browser dan tidak ada sisa mock Statistik/Laporan. Bukti: `php artisan test --compact modules/Core tests/Architecture` → hijau; `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/InsightTest.php` → hijau; `composer deptrac` → 0 pelanggaran; `npm run build` → sukses.

## Batas tindakan
**Selalu**
- `vendor/bin/pint --dirty --format agent` dan `php artisan test --compact modules/Core` sebelum menandai tahap ✅.
- Perbarui tabel status + centang task di dokumen plan; catat penyimpangan di Log keputusan.
- Berhenti setelah tiap tahap dan tunggu persetujuan.

**Tanya dulu**
- Menambah dependensi, tabel, atau izin baru.
- Menambah method baca ke kontrak, atau mengubah signature kontrak setelah Tahap 0.
- Mengubah `SaveStudent`, `deptrac.php`, atau kode Identity/Platform.

**Jangan**
- Mengimpor modul fitur dari Core; membuka model Eloquent lewat kontrak.
- `DB::table()` untuk data tenant; menyimpan berkas laporan di disk.
- Menghapus tes; mengubah nama route `core.insight.statistics` / `core.insight.reports` atau nama komponen `Core/Insight/Statistics` / `Core/Insight/Reports`.
- Menyentuh halaman Integrasi; commit/push tanpa diminta.

## Pengujian
- **Tahap 0:** registry (urutan, placeholder, modul nonaktif, izin); akses per peran.
- **Tahap 1:** tiap angka dan panel, sekolah kosong, isolasi tenant.
- **Tahap 2:** unit `CsvWriter`; katalog; isi tiap laporan; tahun lama; 404/403; isolasi tenant.
- **Tahap 3:** props halaman cetak; satu alur browser.

Factory untuk semua data; tanpa tinker. Setelah Tahap 3, minta user menjalankan suite penuh `php artisan test --compact`.

## Verifikasi end-to-end
1. `php artisan migrate:fresh --seed` lalu `php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDemoSeeder"`.
2. Login sebagai admin sekolah (URL dari tool `get-absolute-url`) → Statistik: angka cocok dengan `/master/siswa`, `/master/guru`, `/master/kelas`; kartu kehadiran "Segera hadir".
3. Laporan → unduh CSV "Daftar Siswa per Kelas", buka di Excel: kolom terpisah, nama benar. Luluskan beberapa siswa di `/akademik/penempatan` → "Mutasi Siswa" memuatnya.
4. "Cetak" pada "Beban Mengajar Guru" → tab baru, dialog cetak hanya memuat kop dan tabel.
5. Login sekolah lain: angka dan laporan terpisah. Pengguna tanpa peran: menu tidak ada, URL → 403.
6. `composer deptrac` → bersih; `npm run build` → sukses.

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-02 (Tahap 0) — `StatisticsProvider::figures()/panels()` menerima `?ReportPeriod`: jumlah siswa dan guru tetap bisa ditampilkan untuk sekolah yang belum punya tahun ajaran. `Report::table()` tetap wajib berperiode.
- 2026-10-02 (Tahap 0) — `DefaultReportRegistry::find()` hanya menyaring modul aktif, tidak mengecek izin: controller yang memutuskan 403, supaya laporan yang ada tetapi tidak boleh dibuka tidak tersamar sebagai 404. `catalogue()` tetap menyaring izin.
- 2026-10-02 (Tahap 0) — Placeholder hilang begitu key-nya terdaftar oleh modul mana pun, juga bila modul itu nonaktif untuk sekolah tersebut (sekolah tidak ditawari "Segera hadir" untuk modul yang tidak ia pakai).
- 2026-10-02 — User meminta Tahap 1–3 diselesaikan tanpa berhenti di antara tahap, dan ditutup dengan tes browser Pest serta spec Playwright untuk fase ini.
- 2026-10-02 (Tahap 2) — Method tampilan cetak bernama `printView` (bukan `print`, kata kunci PHP); nama route tetap `core.insight.reports.print`.
- 2026-10-02 (Tahap 2) — `TeachingLoadReport` membaca dari `Teacher` dengan `teachingAssignments` tahun terpilih (bukan mengelompokkan `TeachingAssignment` di PHP): bertipe penuh untuk PHPStan dan urut nama dari database.
- 2026-10-02 (Tahap 2) — Model `StudentClassHistory` mendapat relasi `student()` (internal Core) untuk kedua laporan siswa.
- 2026-10-02 (Tahap 2) — Penolakan 403 diuji dengan laporan tiruan berizin `core.master.manage` yang dibuka peran guru (`tests/Feature/Support/insight.php`), karena tidak ada peran bawaan yang memegang `core.master.view` tanpa `core.academic.view`. Jalur kodenya sama dengan `teaching-load`.
- 2026-10-02 (Tahap 2) — `MasterDataController` tinggal `whatsapp()` dan tidak lagi memakai `MasterMockData`; `MasterMockData` tetap ada karena dipakai `CoreDemoSeeder`.
- 2026-10-02 (Tahap 3) — Tautan CSV dan Cetak diberi `aria-label` ("Unduh CSV <laporan>", "Cetak <laporan>") supaya tiap tombol punya nama sendiri bagi pembaca layar dan tes.
- 2026-10-02 (setelah Tahap 3) — Spec Playwright `tests/E2E/insight.spec.ts` membuktikan dua hal yang tidak bisa dibuktikan suite browser Pest: unduhan berkas dari server sungguhan dan tampilan cetak di tab baru. Datanya dari helper baru `seedDemoSchoolData()` (`tests/E2E/support/database.ts`) yang menjalankan `CoreDemoSeeder` pada database e2e; sekolah hasil seed tidak punya tahun ajaran.

### Temuan
- `with(['relasi' => $closure])` menyerahkan objek `Relation`, sedangkan `whereHas` menyerahkan `Builder`: satu closure ber-type-hint `Builder` untuk keduanya melempar `TypeError` (500). Pakai `whereHas('a.b', ...)` dan closure terpisah untuk `with`.
- `fputcsv` mengutip sel yang memuat spasi, jadi baris CSV berbentuk `"X 1";0071;;"Budi Santoso"`. Tes membaca balik dengan `str_getcsv`, bukan membandingkan teks mentah.
- `assertDontSee('X 1')` di tes browser cocok dengan `IX 1`: beri nama kelas yang bukan potongan nama lain.
- NIS dengan nol di depan tetap utuh di berkas CSV, tetapi Excel menampilkannya sebagai angka saat berkas dibuka dengan klik ganda (sama dengan catatan impor fase 6). Tampilan cetak tidak terpengaruh.
- Pencocokan mutasi bergantung pada teks catatan `Kelas aktif` di `SaveStudent`; bila teks itu diubah, `StudentMutationReport::UNCHANGED_NOTE` ikut diubah.

### Hasil akhir
Statistik dan Laporan kini membaca database sekolah. Statistik menampilkan siswa aktif, guru & tendik, rombel tahun aktif, sebaran per tingkat dan jenis kelamin. Laporan menawarkan Daftar Siswa per Kelas, Mutasi Siswa, dan Beban Mengajar Guru per tahun ajaran, sebagai unduhan CSV atau tampilan cetak. Bagian kehadiran, PPDB, dan Jadwal Pelajaran tampil sebagai "Segera hadir". Core kini punya kontrak pertamanya (`ReportRegistry`, `Report`, `StatisticsRegistry`, `StatisticsProvider` + DTO). Tanpa tabel, izin, atau dependensi baru; halaman dan menu di balik `core.master.view`.

Verifikasi (2026-10-02): `php artisan test --compact` → 650 lulus (48 baru); `vendor/bin/pest -c phpunit.e2e.xml` → 17 lulus (2 baru); `npx playwright test` → 6 lulus (1 baru); `composer deptrac` → 0 pelanggaran; `npx tsc --noEmit` bersih; `npm run build` sukses; PHPStan tanpa galat di berkas baru.

Tersisa / tindak lanjut:
- Periksa tampilan visual/responsif kedua halaman dan hasil cetak di kertas secara manual; coba buka satu CSV di Excel sungguhan.
- Belum di-commit; semua perubahan ada di working tree branch `feat/core-insight`.
- Tenant lama tidak terdampak (tanpa izin baru).
- Integrasi (WhatsApp) masih mock; Absensi dan PPDB belum dibangun — saat dibangun, mereka mendaftarkan laporan dan angkanya lewat kontrak Core dengan key yang sudah diumumkan di `modules/Core/config/insight.php`.
- Laporan Jadwal Pelajaran menunggu tabel jadwal.
