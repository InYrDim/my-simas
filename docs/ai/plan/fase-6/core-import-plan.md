# Impor siswa dan guru dari CSV tersimpan ke database

> **Status dokumen:** Selesai
> **Dibuat:** 2026-10-02 · **Diperbarui:** 2026-10-02 · **Branch:** `feat/core-import`

## Context
Master Data (`/master/*`, fase 3) dan Akademik (`/akademik/*`, fase 5) di modul **Core** sudah tersimpan di database. Mengisi siswa dan guru masih satu per satu lewat form, padahal sekolah baru punya ratusan siswa.

Halaman **Impor Data** (`/kelola/impor`) masih mockup: `modules/Core/app/Http/Controllers/MasterDataController.php::importData()` mengirim 6 siswa contoh dari `Infrastructure/Mock/MasterMockData.php`; `modules/Core/resources/js/Pages/Core/Manage/Import/Index.tsx` hanya berpindah langkah di state React (berkas tidak pernah dikirim, "NIS sudah dipakai" di-hard-code pada baris ke-4, tombol "Unduh templat CSV" mati). Route-nya hanya di balik `auth`, tanpa gate izin, dan menu "Impor Data" terlihat oleh semua peran.

Fase ini mengganti mock itu dengan impor nyata untuk Siswa dan Guru & Tendik, bertahap vertikal. Absensi dan PPDB sengaja ditunda (keputusan user); Statistik & Laporan dan Integrasi menyusul di fase tersendiri.

## Goal
Setelah ini, admin sekolah bisa mengunduh templat CSV, mengunggah berkas siswa atau guru, memilih mode (tambah saja / perbarui yang sudah ada), melihat pratinjau hasil cek tiap baris dari server, lalu mengimpor baris yang siap. Data muncul di `/master/siswa` dan `/master/guru`, siswa langsung masuk kelas dan riwayat kelasnya tercatat, terpisah antar sekolah, dan hanya pemegang `core.master.manage` yang bisa membuka halaman ini.

## Batasan masalah

**Dalam cakupan**
- Impor **Siswa** dan **Guru & Tendik** dari CSV: unduh templat, pratinjau (cek server), konfirmasi.
- Dua mode impor: **Tambah saja** (kunci yang sudah ada = baris dilewati sebagai galat) dan **Perbarui yang sudah ada** (upsert).
- Gate `core.master.manage` pada route dan menu "Impor Data".
- Halaman React terhubung ke server, banner "Tampilan contoh" hilang; tes Pest (feature, unit, browser).

**Di luar cakupan**
- Berkas Excel (`.xlsx`) — butuh dependensi baru; user memilih CSV saja.
- Impor kelas, mapel, ruangan, pengampu — jumlahnya kecil, diisi lewat form; fase lain bila diminta.
- Riwayat impor (tabel/daftar siapa mengimpor kapan) dan penyimpanan berkas di server — user memilih tanpa riwayat.
- Impor lewat antrean (queue) — berkas dibatasi 1.000 baris, diproses sinkron.
- Mengubah `status` siswa lewat impor (lulus/pindah/keluar) — itu tugas Penempatan Siswa.
- Membuat akun login (`user_id`) untuk siswa/guru yang diimpor.
- Statistik & Laporan, Integrasi, Absensi, PPDB — tetap mock.

**Asumsi**
- Nama kelas unik dalam satu tahun ajaran, sehingga kolom `kelas` bisa dicocokkan dengan nama rombel tahun ajaran **aktif**. Periksa `ClassGroupRequest` di Tahap 1; bila nama bisa ganda, baris dengan nama ganda ditolak dan dicatat di Temuan.
- `useHttp` Inertia v3 bisa mengirim berkas (FormData) dan menerima JSON. Pastikan lewat `search-docs` di Tahap 1; bila tidak, berhenti dan tanya (alternatif: flash data Inertia).
- Tidak ada izin baru, jadi celah "tenant lama tidak mendapat izin baru" (plan fase-5) tidak bertambah.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Semua tetap internal Core (`Contracts/` tetap kosong): tulis lewat `Domain/Actions`, controller tipis, FormRequest di `Http/Requests` (sumber: `modules/Core/CONTRACT.md` § Surfaces).
- Data tenant hanya lewat Eloquent, bukan `DB::table()`; tanpa tabel baru, tanpa FK (sumber: `docs/architecture/modular-monolith.md` § Tenancy rules).
- Tidak menambah dependensi Composer/npm (sumber: `AGENTS.md` § Application Structure). CSV dibaca dengan fungsi PHP bawaan (`SplFileObject`/`fgetcsv`).
- Pembaca CSV adalah utilitas teknis, tetapi baru dipakai satu modul: tinggal di Core. Bila modul kedua membutuhkannya, laporkan sebagai kandidat Shared — jangan dipindah diam-diam.
- UI dasar dari `modules/Shared/resources/js/components/ui`; URL dari Wayfinder `@/actions/...` (sumber: `.ai/rules/js.md`). Aktifkan skill `inertia-react-development`, `wayfinder-development`, `writing-tests`, `testing-best-practices`, `laravel-best-practices` saat menyentuh bagiannya.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-02 | Absensi dan PPDB dikerjakan belakangan; fase 6 = Impor | user |
| 2026-10-02 | Format berkas: CSV saja | user |
| 2026-10-02 | Jenis data: Siswa + Guru & Tendik | user |
| 2026-10-02 | Admin memilih mode impor: tambah saja atau perbarui yang sudah ada | user |
| 2026-10-02 | Tanpa riwayat impor, tanpa menyimpan berkas di server | user |
| 2026-10-02 | Memakai izin yang ada `core.master.manage` (impor = menulis master data); halaman ikut digate karena tidak ada gunanya bagi peran baca-saja | hasil baca kode |
| 2026-10-02 | Alur tanpa status di server: berkas tetap di browser, dikirim dua kali (pratinjau, lalu konfirmasi); konfirmasi mengecek ulang dari nol | hasil desain |
| 2026-10-02 | Pratinjau dan konfirmasi berupa endpoint JSON yang dipanggil `useHttp` (bukan redirect Inertia), karena hasilnya milik langkah wizard di sisi klien | hasil desain |
| 2026-10-02 | Baris bermasalah dilewati, baris siap tetap diimpor (perilaku mockup); penulisan dalam satu `DB::transaction` — galat tak terduga membatalkan semuanya | hasil desain |
| 2026-10-02 | Kunci pencocokan: siswa = NIS, guru = NIP. Guru tanpa NIP selalu dianggap baru | hasil baca kode (`unique(tenant_id, nis)`, `unique(tenant_id, nip)`) |
| 2026-10-02 | Mode perbarui: sel kosong tidak mengubah nilai lama (impor tidak bisa mengosongkan data) | hasil desain |
| 2026-10-02 | Batas berkas: 1 MB, 1.000 baris data | hasil desain |

## Desain

### Core (semua internal)

**Route** — `modules/Core/routes/web.php`, menggantikan baris `kelola/impor` sekarang; grup `prefix('kelola')->name('core.manage.')->middleware('can:core.master.manage')`:

| Method & URL | Nama route | Aksi |
| --- | --- | --- |
| `GET kelola/impor` | `core.manage.import` (tetap) | `ImportController@index` — halaman |
| `GET kelola/impor/templat/{target}` | `core.manage.import.template` | unduh templat CSV (`students`\|`teachers`) |
| `POST kelola/impor/pratinjau` | `core.manage.import.preview` | JSON hasil cek |
| `POST kelola/impor` | `core.manage.import.store` | JSON hasil impor |

Menu "Impor Data" di `CoreServiceProvider::registerNavigation()` mendapat `'permission' => 'core.master.manage'`.

**Berkas baru**
- `Domain/Enums/ImportTarget.php` (`Students`, `Teachers`) dan `Domain/Enums/ImportMode.php` (`AddOnly = 'add'`, `Upsert = 'upsert'`).
- `Infrastructure/Csv/CsvReader.php` — membaca berkas menjadi daftar baris ber-kunci kolom: buang BOM UTF-8, deteksi pemisah `,` atau `;` dari baris judul (CSV ekspor Excel Indonesia memakai `;`), normalisasi judul (huruf kecil, spasi → `_`), lewati baris kosong, simpan nomor baris asli. Melempar `CsvFormatException` (baru, `Domain/Exceptions/`) bila kolom wajib tidak ada, berkas kosong, atau lebih dari 1.000 baris.
- `Domain/Actions/CheckStudentImport.php`, `CheckTeacherImport.php` — menerima baris mentah + mode, mengembalikan daftar baris tercek: `{line, data, outcome: create|update|error, messages[]}`. Data pembanding (NIS/NISN/NIP yang sudah ada, peta nama kelas → id tahun aktif) dimuat **sekali**, bukan per baris.
- `Domain/Actions/ImportStudents.php`, `ImportTeachers.php` — memanggil Check, lalu menulis baris `create`/`update` dalam satu `DB::transaction` lewat `SaveStudent::handle()` / `SaveTeacher::handle()`; mengembalikan `{created, updated, skipped}`.
- `Http/Requests/ImportRequest.php` extends `MasterFormRequest`: `target` (enum), `mode` (enum), `file` (`required`, `file`, `mimes:csv,txt`, `max:1024`).
- `Http/Controllers/ImportController.php` — `index`, `template`, `preview`, `store`; memakai `Http/Concerns/RendersMasterPage.php`. `CsvFormatException` dijadikan galat validasi pada field `file` (422).

**Kolom CSV**

| Jenis | Kolom (wajib **tebal**) | Catatan |
| --- | --- | --- |
| Siswa | **`nama`**, **`nis`**, `nisn`, **`jenis_kelamin`**, `tanggal_lahir`, `nama_wali`, `telepon_wali`, `kelas` | `jenis_kelamin`: `L`/`P` atau `Laki-laki`/`Perempuan`. `tanggal_lahir`: `YYYY-MM-DD` atau `DD/MM/YYYY`. `kelas`: nama rombel tahun ajaran aktif; kosong = tanpa kelas |
| Guru | **`nama`**, `nip`, `nuptk`, **`status_kepegawaian`**, **`tugas`**, `email` | Nilai `status_kepegawaian` dan `tugas` sama dengan `TeacherRequest` (`PNS`/`GTY`/`GTT`/`Honorer`; `Guru Mapel`/`Tenaga Kependidikan`/`Kepala Sekolah`), tidak peka huruf besar |

Aturan per field mengikuti `Http/Requests/StudentRequest.php` dan `TeacherRequest.php` (panjang, format, pilihan); keunikan dicek di memori, bukan `Rule::unique` per baris. NIS/NIP dibaca sebagai teks (nol di depan dipertahankan).

**Hasil cek per baris**
- Galat (baris dilewati): field wajib kosong / format salah; NIS atau NIP muncul dua kali di berkas (kemunculan kedua); NISN dipakai siswa lain; kelas tidak ditemukan di tahun aktif; pada mode **tambah saja**, NIS/NIP sudah terdaftar.
- `update`: mode **perbarui** dan NIS/NIP sudah terdaftar. Sel kosong tidak mengubah nilai lama; `kelas` kosong = kelas tidak berubah; `status` siswa tidak disentuh (siswa nonaktif tetap tanpa kelas, dijaga `SaveStudent`).
- `create`: sisanya.

**Respons JSON**
- Pratinjau: `{summary: {total, create, update, error}, rows: [{line, key, name, detail, outcome, messages}]}` (`key` = NIS/NIP, `detail` = kelas atau tugas).
- Konfirmasi: `{created, updated, skipped}`.

**Halaman** — `modules/Core/resources/js/Pages/Core/Manage/Import/Index.tsx`: tiga langkah tetap. Langkah 1: jenis data, **mode impor** (`OptionSelect` baru), berkas, tautan unduh templat (anchor ke route templat sesuai jenis terpilih), hint kolom mengikuti jenis. Langkah 2: ringkasan + tabel dari respons server, badge `Baru` / `Diperbarui` / pesan galat, saklar "hanya yang bermasalah"; tombol impor mati bila tidak ada baris siap. Langkah 3: angka dari respons konfirmasi + tautan ke daftar Siswa/Guru. Galat berkas (422) tampil di field berkas. Prop `preview` dihapus; `mock={false}`.

**Dipakai ulang**
- Backend: `Domain/Actions/SaveStudent.php` (riwayat kelas sudah benar di sana), `SaveTeacher.php`, `Http/Requests/MasterFormRequest.php` (gate + pesan Indonesia), `RendersMasterPage`.
- Frontend: `Components/{MasterPage,FormField}.tsx`; `@shared/components/page-parts` (`DataTable`, `OptionSelect`, `Panel`, `EmptyState`); `@shared/components/ui/{badge,button,table,alert}`.
- Tes: `schoolAs()`, `inSchool()`, `yearWithSemesters()`, `classIn()` di `modules/Core/tests/Feature/Support/helpers.php`; helper `school($slug, $path)`; `UploadedFile::fake()->createWithContent()`.

### Identity / Platform
- Tidak ada perubahan (tanpa izin baru, tanpa kontrak baru).

### Alur
1. Admin membuka Impor Data, memilih "Siswa", mengunduh templat, mengisinya di Excel, menyimpan sebagai CSV.
2. Memilih mode, mengunggah berkas → "Lanjut ke pratinjau": server mengecek, halaman menampilkan mis. "120 baris terbaca: 115 baru, 3 diperbarui, 2 perlu diperbaiki".
3. "Impor 118 baris yang siap" → langkah Selesai menampilkan jumlah ditambah/diperbarui/dilewati.
4. Siswa muncul di `/master/siswa` dengan kelasnya; riwayat kelas terlihat di detail siswa.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Fondasi: dokumen plan, branch, gate route + menu, `ImportController@index`, `CsvReader`, unduh templat | ✅ | Grup route `kelola` dan menu Impor Data digate `core.master.manage`. `ImportTarget` (kolom, kolom wajib, baris contoh), `ImportMode`, `CsvReader` (+ baris `sep=`, Windows-1252), `ImportController@index,template`. Tombol unduh templat hidup; pratinjau di halaman masih data contoh. 221 tes Core + arsitektur hijau, Deptrac 0 pelanggaran, `tsc` bersih, PHPStan bersih pada berkas baru. |
| 1 | Impor Siswa, mode tambah saja (pratinjau + konfirmasi + halaman terhubung) | ✅ | `CheckStudentImport`, `ImportStudents` (lewat `SaveStudent`), `ImportRequest`, `ImportController@preview,store`. Halaman memakai `useHttp`, banner mock hilang, prop `preview` diganti `targets` + `maxRows`. `ImportStudentsTest` (26 kasus). |
| 2 | Impor Guru & Tendik, mode tambah saja | ✅ | `CheckTeacherImport`, `ImportTeachers` (lewat `SaveTeacher`). NIP berbentuk notasi ilmiah ditolak dengan petunjuk. `ImportTeachersTest` (16 kasus). |
| 3 | Mode perbarui (siswa + guru), tes browser, pembersihan mock & dokumentasi | ✅ | Mode `upsert` di kedua pengecek, pilihan mode di halaman, `tests/Browser/ImportTest.php` (2 tes), `CONTRACT.md` + dokumen arsitektur + memori diperbarui. Suite penuh 602 tes hijau, browser 2 hijau, Deptrac 0, `tsc` bersih, `npm run build` sukses. |

## Tasks

### Tahap 0 — Fondasi
- [x] Buat branch `feat/core-import`; tulis plan ini ke `docs/ai/plan/fase-6/core-import-plan.md` (baru).
- [x] `ImportTarget`, `ImportMode` — `modules/Core/app/Domain/Enums/` (baru).
- [x] `CsvReader` + `CsvFormatException` — `modules/Core/app/Infrastructure/Csv/CsvReader.php`, `modules/Core/app/Domain/Exceptions/CsvFormatException.php` (baru).
- [x] `ImportController@index,template` — `modules/Core/app/Http/Controllers/ImportController.php` (baru); templat = judul kolom + satu baris contoh, `Content-Type: text/csv`, nama berkas `templat-siswa.csv` / `templat-guru.csv`.
- [x] Route grup `kelola` dengan `can:core.master.manage`; hapus `importData()` dari `MasterDataController.php` dan perbarui docblock-nya — `modules/Core/routes/web.php`.
- [x] Menu "Impor Data" `'permission' => 'core.master.manage'` — `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`.
- [x] Halaman: tombol "Unduh templat CSV" menjadi tautan nyata (Wayfinder), prop `preview` tetap berisi data contoh (lihat Log keputusan); banner mock tetap sampai Tahap 1 — `modules/Core/resources/js/Pages/Core/Manage/Import/Index.tsx`.
- [x] Sesuaikan tes yang ada: tes "halaman masih mock" pindah dari `/kelola/impor` ke `/statistik-laporan/statistik` — `modules/Core/tests/Feature/MasterDataPagesTest.php`; daftar menu peran tanpa `core.master.manage` tidak lagi memuat "Impor Data" — `modules/Core/tests/Feature/TenantNavigationTest.php`.
- [x] Tes: `CsvReader` (BOM, pemisah `;` dan `,`, judul dinormalisasi, baris kosong dilewati, kolom wajib hilang, >1.000 baris, berkas kosong) — `modules/Core/tests/Unit/CsvReaderTest.php` (baru); akses (admin 200, guru & staf-TU 403, tamu redirect), templat terunduh dengan judul kolom yang benar — `modules/Core/tests/Feature/ImportAccessTest.php` (baru).

**Selesai bila:** guru mendapat 403 di `/kelola/impor` dan tidak melihat menunya; admin bisa mengunduh kedua templat. Bukti: `php artisan test --compact modules/Core` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 1 — Impor Siswa (tambah saja)
- [x] Pastikan `useHttp` + unggah berkas lewat `search-docs` (paket `inertiajs/inertia-laravel`); pastikan keunikan nama kelas per tahun di `modules/Core/app/Http/Requests/ClassGroupRequest.php` (lihat Asumsi).
- [x] `CheckStudentImport` — `modules/Core/app/Domain/Actions/CheckStudentImport.php` (baru).
- [x] `ImportStudents` (lewat `SaveStudent`) — `modules/Core/app/Domain/Actions/ImportStudents.php` (baru).
- [x] `ImportRequest` — `modules/Core/app/Http/Requests/ImportRequest.php` (baru); `ImportController@preview,store`; route `preview` dan `store`.
- [x] Halaman terhubung: `useHttp` ke `preview` lalu `store`, tabel pratinjau dari server, galat berkas di field, langkah Selesai dari respons, hapus prop `preview`, `mock={false}` — `modules/Core/resources/js/Pages/Core/Manage/Import/Index.tsx`.
- [x] Tes — `modules/Core/tests/Feature/ImportStudentsTest.php` (baru): impor membuat siswa + baris `student_class_history` untuk kelas tahun aktif; pratinjau tidak menulis apa pun; baris galat dilewati dan sisanya masuk (NIS sudah ada, NIS ganda di berkas, NISN dipakai, kelas tak dikenal, nama kosong, jenis kelamin salah, tanggal salah); `Laki-laki`/`DD/MM/YYYY` diterima; nol di depan NIS bertahan; berkas bukan CSV / kolom wajib hilang → 422 pada `file`; guru → 403; isolasi tenant (NIS yang sama di sekolah lain bukan duplikat, kelas sekolah lain tidak cocok).

**Selesai bila:** berkas CSV siswa yang diunggah admin menghasilkan siswa di `/master/siswa` dengan kelas dan riwayatnya, dan halaman tidak lagi menampilkan "Tampilan contoh". Bukti: `php artisan test --compact modules/Core` → hijau; `npx tsc --noEmit` → bersih.

### Tahap 2 — Impor Guru & Tendik (tambah saja)
- [x] `CheckTeacherImport`, `ImportTeachers` (lewat `SaveTeacher`) — `modules/Core/app/Domain/Actions/` (baru).
- [x] `ImportController` meneruskan target `teachers`; hint kolom dan templat guru aktif di halaman — `ImportController.php`, `Import/Index.tsx`.
- [x] Tes — `modules/Core/tests/Feature/ImportTeachersTest.php` (baru): impor membuat guru; NIP sudah ada / ganda di berkas dilewati; guru tanpa NIP selalu dibuat; `status_kepegawaian`/`tugas` tak dikenal dan email salah ditolak; huruf besar-kecil pilihan diterima; 403; isolasi tenant.

**Selesai bila:** berkas CSV guru menghasilkan guru di `/master/guru`. Bukti: `php artisan test --compact modules/Core` → hijau.

### Tahap 3 — Mode perbarui + penutup
- [x] Mode `upsert` di `CheckStudentImport` / `CheckTeacherImport` (outcome `update`) dan penulisan di `ImportStudents` / `ImportTeachers` (sel kosong tidak menimpa) — berkas Tahap 1–2.
- [x] Halaman: pilihan mode aktif dengan keterangan singkat, badge `Diperbarui`, ringkasan memuat jumlah diperbarui — `Import/Index.tsx`.
- [x] Tes (tambahan di `ImportStudentsTest.php`, `ImportTeachersTest.php`): upsert memperbarui nama/wali; sel kosong mempertahankan nilai lama; `kelas` baru memindah siswa dan riwayat mencatat `Pindah kelas`; siswa nonaktif tetap tanpa kelas; mode tambah saja tetap menolak; guru tanpa NIP tetap dibuat baru.
- [x] Tes browser: unggah CSV siswa → pratinjau menampilkan satu galat → impor → muncul di daftar Siswa — `tests/Browser/ImportTest.php` (baru; ikuti pola `tests/Browser/AcademicManagementTest.php`).
- [x] Bersihkan: komentar "mockup phase" impor di `routes/web.php`; method `MasterMockData` yang tak lagi dipakai siapa pun (pertahankan yang dipakai `CoreDemoSeeder` / `InsightMockData`).
- [x] Dokumentasi: `modules/Core/CONTRACT.md` (Impor Data bukan mock, aturan impor), `docs/architecture/modular-monolith.md` (baris Core), memori `project_mockup_first.md`.

**Selesai bila:** admin bisa memilih mode dan memperbarui data lewat CSV; tidak ada sisa mock impor. Bukti: `php artisan test --compact modules/Core tests/Architecture` → hijau; `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/ImportTest.php` → hijau; `composer deptrac` → 0 pelanggaran; `npm run build` → sukses.

## Batas tindakan
**Selalu**
- `vendor/bin/pint --dirty --format agent` dan `php artisan test --compact modules/Core` sebelum menandai tahap ✅.
- Perbarui tabel status + centang task di dokumen plan saat tahap mulai/selesai; catat penyimpangan di Log keputusan.
- Berhenti setelah tiap tahap dan tunggu persetujuan.

**Tanya dulu**
- Menambah dependensi (mis. pembaca Excel), tabel, atau izin baru.
- Menambah kontrak di `modules/Core/app/Contracts` atau memindah `CsvReader` ke Shared.
- Mengubah `SaveStudent` / `SaveTeacher` atau kode Identity/Platform.

**Jangan**
- Memakai `DB::table()` atau `insert()` massal untuk data tenant (melewati scope dan riwayat kelas); `WithoutModelEvents`.
- Menyimpan berkas unggahan di disk atau isi berkas di session.
- Menghapus tes; mengubah nama route `core.manage.import` atau nama komponen `Core/Manage/Import/Index`.
- Commit/push tanpa diminta.

## Pengujian
- **Tahap 0:** unit `CsvReader`; akses per peran; unduh templat.
- **Tahap 1:** jalur utama siswa, tiap jenis galat baris, galat berkas, pratinjau tanpa efek samping, izin, isolasi tenant.
- **Tahap 2:** jalur utama guru, galat baris, guru tanpa NIP, izin, isolasi tenant.
- **Tahap 3:** upsert kedua jenis, sel kosong, pindah kelas lewat impor; satu alur browser penuh.

Factory untuk semua data; tanpa tinker. Setelah Tahap 3, minta user menjalankan suite penuh `php artisan test --compact`.

## Verifikasi end-to-end
1. `php artisan migrate:fresh --seed` lalu `php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDemoSeeder"`.
2. Login sebagai admin sekolah (URL dari tool `get-absolute-url`) → Impor Data → unduh templat siswa, isi beberapa baris (satu dengan NIS yang sudah ada, satu dengan kelas salah), unggah → pratinjau menandai dua baris itu → impor → siswa lain muncul di `/master/siswa`, riwayat kelas ada di detailnya.
3. Ulangi dengan mode "Perbarui": baris ber-NIS lama kini berstatus `Diperbarui` dan datanya berubah.
4. Ulangi untuk Guru & Tendik.
5. Login sebagai guru: menu Impor Data tidak ada, `/kelola/impor` → 403. Login sekolah lain: data terpisah.
6. `composer deptrac` → bersih; `npm run build` → sukses.

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-02 (Tahap 0) — `ImportController@index` tetap mengirim 6 siswa contoh sebagai `preview` (bukan daftar kosong seperti rencana): dengan daftar kosong, langkah pratinjau mockup menampilkan "Impor -1 baris". Data contoh dan banner "Tampilan contoh" hilang bersamaan di Tahap 1.
- 2026-10-02 (Tahap 0) — Templat memakai pemisah `;` dengan baris pertama `sep=;`, supaya Excel memecah kolom apa pun pengaturan regional komputernya; `CsvReader` mengenali dan melewati baris `sep=`. Tanpa BOM (BOM + `sep=` membuat Excel salah membaca).
- 2026-10-02 (Tahap 0) — `CsvReader` juga mengubah teks Windows-1252 ke UTF-8: "CSV (Comma delimited)" dari Excel disimpan dalam ANSI, bukan UTF-8.
- 2026-10-02 — User meminta semua tahap diselesaikan tanpa berhenti di antara tahap (menggantikan "berhenti di antara tahap" untuk eksekusi ini). Tahap 1–3 dibangun berurutan; karena itu penguncian sementara (target `teachers` ditolak, pilihan guru/mode dinonaktifkan) tidak pernah dibuat.
- 2026-10-02 (Tahap 1) — Pencocokan NIS, NISN, NIP, dan nama kelas tidak peka huruf besar-kecil, supaya `a1` dan `A1` tidak lolos cek lalu bertabrakan di indeks unik MySQL.
- 2026-10-02 (Tahap 1) — Aturan field ditulis langsung di `Check*Import` (bukan `Validator` per baris): pesan per baris lebih jelas dan tanpa query per baris. Bila aturan `StudentRequest` / `TeacherRequest` berubah, pengecek ini ikut diubah.
- 2026-10-02 (Tahap 1) — Halaman menerima prop `targets` (nilai, label, kolom, kolom wajib dari `ImportTarget`) dan `maxRows`, supaya hint kolom tidak ditulis dua kali.
- 2026-10-02 (Tahap 2) — `CheckTeacherImport` mengembalikan `data: null` untuk baris galat dan bentuk bertipe untuk baris siap, supaya `SaveTeacher` menerima bentuk yang dijanjikan PHPStan.
- 2026-10-02 (Tahap 3) — Tes browser memakai helper baru `tests/Browser/Support/uploads.php` (`acceptBrowserUploads()`), lihat Temuan.
- 2026-10-02 (setelah Tahap 3) — Atas permintaan user, ditambah satu spec Playwright `tests/E2E/import.spec.ts`: admin `sekolah-a` mengunggah CSV siswa di server sungguhan (pratinjau dengan satu baris ditolak, impor, siswa muncul di daftar). Ini satu-satunya bukti otomatis bahwa PHP asli mengurai unggahan dari halaman; skenario rinci tetap di suite browser Pest. Pendukung baru: `tests/E2E/support/database.ts` (`schoolCode(slug)` membaca kode sekolah dari database e2e, karena kode dibuat ulang tiap seed) dan akun `schoolAdmin` di `tests/E2E/support/env.ts`.
- 2026-10-02 (Tahap 3) — Tidak ada method `MasterMockData` yang dihapus: `students()` masih dipakai `CoreDemoSeeder`.
- 2026-10-02 (Tahap 0) — Kolom, kolom wajib, baris contoh, dan nama berkas templat tinggal di enum `ImportTarget`, supaya templat dan pengecekan Tahap 1–2 memakai satu daftar kolom.

### Temuan
- Nomor baris di `CsvReader` adalah nomor **record** (judul = 1, atau 2 bila ada baris `sep=`); sel berkutip yang memuat baris baru dihitung satu baris.
- Server uji plugin browser Pest tidak mengurai unggahan berkas: permintaan multipart sampai ke aplikasi tanpa field dan tanpa berkas (`@TODO files...` di `vendor/pestphp/pest-plugin-browser/src/Drivers/LaravelHttpServer.php`). `acceptBrowserUploads()` menambal ini lewat middleware khusus tes yang mengurai badan mentah. Ini celah server uji, bukan aplikasi; server sungguhan mengurainya sendiri.
- `php artisan wayfinder:generate` tidak menghapus ekspor lama, sehingga `tsc` lolos padahal `Students/Index.tsx` masih mengimpor `importData` dari `MasterDataController`. `npm run build` yang menangkapnya (plugin Vite meregenerasi dari nol). Jalankan build setelah menghapus method controller.
- Suite Playwright sekali gagal di dua tes (impor dan onboarding) dalam satu run yang berjalan 1,4 menit, lalu lulus 5/5 pada tiga run berikutnya (±55 detik). Penyebabnya tidak terlacak karena hasil run itu terhapus sebelum dibaca; dugaan: mesin sedang lambat sehingga batas 5 detik per ekspektasi terlewati. Bila terulang, baca `test-results/*/error-context.md` sebelum membersihkannya.
- Impor memanggil `SaveStudent` per baris (beberapa query per siswa). Untuk batas 1.000 baris ini cukup; bila batas dinaikkan, pertimbangkan penulisan massal + antrean.
- Ditangani di Tahap 2: Excel mengubah NIP 18 digit menjadi notasi ilmiah (`1,98501E+17`) dan membuang digit terakhir saat disimpan. `CheckTeacherImport` perlu menolak NIP berbentuk notasi ilmiah dengan pesan yang menjelaskan cara memformat kolom sebagai Teks. Hal yang sama berlaku untuk nol di depan NIS (tidak bisa dideteksi; cukup dijelaskan di hint halaman).

### Hasil akhir
Halaman Impor Data kini nyata: admin sekolah mengunduh templat, mengunggah CSV siswa atau guru, memilih mode tambah saja / perbarui, melihat pratinjau hasil cek server, lalu mengimpor baris yang siap. Tanpa tabel, izin, kontrak, atau dependensi baru. Halaman dan menunya hanya untuk pemegang `core.master.manage`.

Verifikasi (2026-10-02): `php artisan test --compact` → 602 lulus; `vendor/bin/pest -c phpunit.e2e.xml` → 15 lulus (2 baru); `npx playwright test` → 5 lulus (1 baru); `composer deptrac` → 0 pelanggaran; `npx tsc --noEmit` bersih; `npm run build` sukses; PHPStan tanpa galat di berkas baru (29 galat lama tetap).

Tersisa / tindak lanjut:
- Periksa tampilan visual/responsif halaman secara manual, dan coba satu berkas hasil simpan Excel sungguhan (tes memakai CSV buatan).
- Belum di-commit; semua perubahan ada di working tree branch `feat/core-import`.
- Statistik & Laporan, Integrasi, Absensi, PPDB masih mock.
- Impor kelas/mapel dan berkas `.xlsx` bila nanti diminta.
