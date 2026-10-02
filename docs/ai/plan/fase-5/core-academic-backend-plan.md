# Akademik sekolah tersimpan di database: wali kelas, pengampu, penempatan, jam pelajaran, kalender

> **Status dokumen:** Selesai
> **Dibuat:** 2026-10-02 · **Diperbarui:** 2026-10-02 · **Branch:** `feat/core-academic` 

## Context
Master Data sekolah (`/master/*`) di modul **Core** sudah tersimpan di database (`docs/ai/plan/fase-3/core-master-data-backend-plan.md`, tahap 0–3 selesai). Lima halaman **Akademik** (`/akademik/*`) masih mockup: `modules/Core/app/Http/Controllers/MasterDataController.php` (`placement`, `assignments`, `homerooms`, `periods`, `calendar`) membaca `modules/Core/app/Infrastructure/Mock/MasterMockData.php`, tombol "Simpan" hanya mengubah state React, dan halamannya menampilkan peringatan "Tampilan contoh".

Akibatnya data induk yang sudah nyata tidak bisa dipakai penuh: `classes.homeroom_teacher_id` selalu kosong, `Classes/Show` dan `Teachers/Show` mengirim `assignments: []`, dan `student_class_history` hanya ditulis lewat form siswa satu per satu. Route `/akademik/*` juga belum punya gate izin (hanya `auth`).

Fase ini mengganti mock kelima halaman dengan database, **bertahap per halaman (vertikal)**, berhenti di antara tahap. Nama route `core.academic.*`, URL, dan nama komponen Inertia tidak berubah.

## Goal
Setelah ini, admin sekolah bisa menetapkan wali kelas, menentukan guru pengampu dan JP/minggu tiap mapel per kelas, menaikkan/memindahkan/meluluskan siswa secara massal, menyusun jam pelajaran per hari, dan mengisi kalender akademik. Semuanya bertahan setelah muat ulang, terpisah antar sekolah, dan hanya bisa diubah oleh pemegang izin `core.academic.manage`. Guru dan staf TU bisa melihat tanpa bisa mengubah.

## Batasan masalah

**Dalam cakupan**
- Izin `core.academic.view|manage` + gate route dan sidebar.
- Wali Kelas, Pengampu Mapel, Penempatan Siswa, Jam Pelajaran, Kalender Akademik: tabel, model, Action, FormRequest, controller, halaman React, tes Pest.
- Mengisi `assignments` di `Classes/Show` dan `Teachers/Show` dari data nyata.
- `CoreDemoSeeder` mengisi contoh data akademik.

**Di luar cakupan**
- Jadwal pelajaran (mapel × kelas × slot jam) — butuh pengampu + jam pelajaran dulu; plan tersendiri.
- Impor Data, Statistik & Laporan, Integrasi, Absensi, PPDB — tetap mock.
- Kontrak publik Core (`Contracts/`) untuk kelas/siswa/kalender — dibuat saat Absensi membutuhkannya.
- Pengampu per semester — diputuskan per tahun ajaran.
- Sinkron izin baru ke peran tenant **lama** — belum ada mekanismenya (lihat Asumsi).

**Asumsi**
- Dev memakai `php artisan migrate:fresh --seed`; tenant baru mendapat izin lewat `SeedDefaultRoles`. Tenant lama tidak otomatis mendapat `core.academic.*` (celah yang sama tercatat di plan fase-3 Tahap 0). Bila ada tenant yang harus dipertahankan, berhenti dan tanya.
- Jumlah peristiwa kalender dan slot jam per sekolah kecil (puluhan), jadi dikirim utuh tanpa paginasi.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Tabel tenant: `$table->tenantId()` + model `BelongsToTenant`; Eloquent saja (bukan `DB::table()`); tanpa FK selain `tenant_id` — relasi berupa kolom polos berindeks, cascade/penolakan di Action (sumber: `docs/architecture/modular-monolith.md` § Tenancy rules; `tests/Architecture/ModularMonolithTest.php`).
- Setiap model baru yang memakai `BelongsToTenant` ditambahkan ke `deptrac.baseline.yaml` (satu baris `TenantScope`, pola model Core yang ada).
- Semua tetap internal Core: model di `Domain/Models`, tulis lewat `Domain/Actions`, controller tipis per halaman, FormRequest di `Http/Requests`, JsonResource menjaga bentuk props (sumber: `modules/Core/CONTRACT.md` § Surfaces).
- Izin didaftarkan lewat `PermissionRegistry` di `CoreServiceProvider`, ditempelkan ke peran lewat nama di `modules/Identity/config/roles.php`.
- UI dasar dari `modules/Shared/resources/js/components/ui`; form memakai Wayfinder `@/actions/...` (sumber: `.ai/rules/js.md`). Aktifkan skill `inertia-react-development` dan `wayfinder-development` saat menyentuh halaman; `writing-tests` + `testing-best-practices` saat menulis tes.
- Seeder tidak memakai `WithoutModelEvents`.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-02 | Kelima halaman dikerjakan, urutan: Wali Kelas → Pengampu → Penempatan → Jam Pelajaran → Kalender | user |
| 2026-10-02 | Izin baru `core.academic.view` / `core.academic.manage` (admin: manage; guru & staf-TU: view) | user |
| 2026-10-02 | Pengampu berlaku per tahun ajaran (kelas sudah terikat tahun; tanpa kolom periode) | user |
| 2026-10-02 | Plan di `docs/ai/plan/fase-5/` | user |
| 2026-10-02 | Halaman Pengampu menampilkan **semua** mapel untuk kelas terpilih; baris tersimpan hanya bila guru dipilih (tanpa tabel kurikulum terpisah) | hasil desain |
| 2026-10-02 | Wali kelas & pengampu bekerja pada kelas tahun ajaran **aktif**; guru merangkap ditandai, tidak ditolak (perilaku mockup) | hasil baca kode |
| 2026-10-02 | Penempatan memakai ulang `SaveStudent` (riwayat kelas sudah benar di sana), dibungkus satu transaksi | hasil baca kode |
| 2026-10-02 | Jam pelajaran satu template per sekolah (tidak terikat tahun); "Jam ke" dihitung dari urutan slot bertipe Pelajaran, tidak disimpan | hasil desain |
| 2026-10-02 | Nilai tipe slot dan kategori mengikuti konvensi Core: label apa adanya (`Pelajaran`, `Istirahat`, …) untuk slot; kunci `holiday|exam|activity` untuk kalender (bentuk props sekarang) | hasil baca kode |

## Desain

### Core (semua internal; `Contracts/` tetap kosong)

**Izin & route.** `core.academic.view` pada grup `akademik`, `core.academic.manage` pada grup tulis di dalamnya — pola persis grup `master` di `modules/Core/routes/web.php`. Entri sidebar "Akademik" di `CoreServiceProvider::registerNavigation()` mendapat `'permission' => 'core.academic.view'`. Basis FormRequest baru `AcademicFormRequest extends MasterFormRequest` yang hanya menimpa `authorize()` (memakai ulang `existsInSchool`, `uniqueInSchool`, sentinel `NONE`, pesan Indonesia).

**Controller.** Satu controller per halaman menggantikan method di `MasterDataController` (yang tersisa di sana: impor, statistik, laporan, WhatsApp). Semua memakai trait `Http/Concerns/RendersMasterPage.php`.

| Halaman | Controller (baru) | Route tulis baru | Tabel |
| --- | --- | --- | --- |
| Wali Kelas | `HomeroomController` | `PUT akademik/wali-kelas` → `homerooms.update` | `classes.homeroom_teacher_id` (sudah ada) |
| Pengampu | `TeachingAssignmentController` | `PUT akademik/pengampu/{classGroup}` → `assignments.update` | `teaching_assignments` (baru) |
| Penempatan | `PlacementController` | `POST akademik/penempatan` → `placement.store` | `students`, `student_class_history` (sudah ada) |
| Jam Pelajaran | `PeriodSlotController` | `POST/PUT/DELETE akademik/jam-pelajaran[/{slot}]`, `POST akademik/jam-pelajaran/salin` | `period_slots` (baru) |
| Kalender | `CalendarEventController` | `POST/PUT/DELETE akademik/kalender[/{event}]` | `calendar_events` (baru) |

**Tabel baru**
- `teaching_assignments`: `tenant_id`, `class_id`, `subject_id`, `teacher_id` (semua berindeks), `hours_per_week` tinyint; `unique(tenant_id, class_id, subject_id)`.
- `period_slots`: `tenant_id`, `day` tinyint (1=Senin … 6=Sabtu), `start_time`, `end_time` (time), `type` string(16); `unique(tenant_id, day, start_time)`.
- `calendar_events`: `tenant_id`, `title`, `category` string(16), `start_date`, `end_date` nullable; indeks `(tenant_id, start_date)`.

**Action & aturan**
- `AssignHomerooms`: peta `class_id → teacher_id|null`; kelas harus milik tahun aktif; ditulis dengan `forceFill` (kolom sengaja tidak fillable).
- `SaveClassAssignments`: untuk satu kelas, tiap baris `{subject_id, teacher_id|null, hours}` — guru kosong menghapus baris, selain itu `updateOrCreate`. `hours` 1–20.
- Penjaga hapus: `DeleteTeacher` menolak bila masih mengampu; aksi hapus mapel menolak bila masih diampu; `DeleteClassGroup` ikut menghapus baris pengampu kelas itu.
- `PlaceStudents`: aksi `promote|move|graduate`. Semua siswa harus aktif dan berada di rombel asal. `move`: tujuan di tahun yang sama, kelas berbeda. `promote`: tujuan di tahun ajaran yang mulai lebih lambat dari tahun asal. `graduate`: tanpa tujuan. Tiap siswa lewat `SaveStudent::handle()` (`class_id` baru, atau `status=graduated`) dalam satu `DB::transaction` — riwayat (`Kelas aktif` / `Pindah kelas` / `Lulus`) ditulis oleh `SaveStudent`.
- `SavePeriodSlot` / `DeletePeriodSlot`: `end_time > start_time`, tidak tumpang tindih dalam satu hari. `CopyPeriodDay`: mengganti slot hari tujuan dengan salinan hari sumber.
- `SaveCalendarEvent` / `DeleteCalendarEvent`: `end_date >= start_date` bila diisi.

**Props (bentuk dipertahankan, tambahan seperlunya)** — `modules/Core/resources/js/types/master.ts`
- `Assignment` mendapat `subjectId`, `teacherId: number | null`, `classId`; field lama (`teacher`, `subject`, `class`, `hours`) tetap karena dipakai `Classes/Show` dan `Teachers/Show`.
- Pengampu & Penempatan memilih kelas di server lewat query `?kelas=<id>` (daftar siswa/mapel hanya untuk kelas terpilih).
- `PeriodSlot` mendapat `id`; `PeriodDay` mendapat `dayNumber`.
- Halaman dipindah ke `mock={false}` satu per satu.

**Dipakai ulang**
- Frontend: `modules/Core/resources/js/Components/{MasterPage,FormDialog,MasterForm,FormField,ConfirmAction,format}.tsx`; `@shared/components/page-parts` (`DataTable`, `OptionSelect`, `Panel`, `EmptyState`).
- Backend: `SaveStudent`, `ClassGroupResource`, `TeacherResource`, `StudentResource`, `AcademicYearResource`, `RendersMasterPage`.
- Tes: `schoolAs()`, `inSchool()`, `yearWithSemesters()`, `classIn()` di `modules/Core/tests/Feature/Support/helpers.php`; helper `school($slug, $path)`.
- Data contoh: `MasterMockData::periods()` dan `calendarEvents()` tetap ada sebagai sumber `CoreDemoSeeder`.

### Identity
- Hanya `modules/Identity/config/roles.php`: tambah nama izin (admin-sekolah: view+manage; guru, staf-tu: view). Tidak ada kode Identity lain.

### Alur
1. Admin membuka Akademik › Wali Kelas, memilih guru per rombel tahun aktif, Simpan → daftar Kelas dan detail Guru menampilkan wali kelasnya.
2. Akademik › Pengampu: pilih kelas → tiap mapel diberi guru + JP/minggu → Simpan → tampil di detail Kelas dan detail Guru.
3. Akademik › Penempatan: pilih tahun & rombel asal, centang siswa, pilih tindakan (+ rombel tujuan) → Konfirmasi → siswa pindah, riwayat kelas tercatat.
4. Akademik › Jam Pelajaran: tambah/ubah/hapus slot per hari, salin satu hari ke hari lain.
5. Akademik › Kalender: tambah/ubah/hapus peristiwa; grid bulan dibuka pada bulan berjalan.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Fondasi: dokumen plan, branch, izin `core.academic.*`, gate route + sidebar | ✅ | Izin `core.academic.view` / `core.academic.manage` terdaftar + label, menempel ke peran default, grup `akademik` dan menu Akademik digate. `AcademicFormRequest` siap dipakai Tahap 1. Ekspektasi `RolesPageTest` (Identity) ikut disesuaikan. Tenant lama belum mendapat izin baru (lihat Asumsi). |
| 1 | Wali Kelas | ✅ | `AssignHomerooms`, `HomeroomRequest`, `HomeroomController`; route `PUT akademik/wali-kelas`. Halaman memakai `useForm` + Wayfinder (bukan `MasterForm`, karena tabel bukan dialog). Opsi "Belum ditentukan" memakai sentinel `none`. Tes: `AcademicHomeroomTest` (9 kasus). `tsc --noEmit` bersih. |
| 2 | Pengampu Mapel (+ `assignments` di detail Kelas & Guru) | ✅ | Tabel `teaching_assignments`, `SaveClassAssignments` (sinkron: baris yang tidak dikirim dihapus), `DeleteSubject` (baru), penjaga di `DeleteTeacher`/`DeleteClassGroup`. Halaman: pilih kelas via `?kelas=`, form per kelas dengan `useForm`. 155 tes Core + arsitektur hijau, Deptrac 0 pelanggaran, `tsc` bersih. |
| 3 | Penempatan Siswa | ✅ | Enum `PlacementAction`, `PlaceStudents` (validasi semua dulu, lalu satu transaksi lewat `SaveStudent`), `PlacementRequest`, `PlacementController`. Halaman: rombel asal via `?kelas=`, form berkunci per rombel asal, pilih semua. Catatan riwayat naik kelas = `Kelas aktif` (dari `SaveStudent`). 168 tes Core + arsitektur hijau, Deptrac 0, `tsc` bersih. |
| 4 | Jam Pelajaran | ✅ | Tabel `period_slots` (waktu disimpan `H:i:s`), `SavePeriodSlot` (tanpa tumpang tindih per hari; slot berurutan boleh), `CopyPeriodDay`, `PeriodSlotController`. Jenis slot: `Pelajaran`, `Istirahat`, `Pembiasaan`, `Upacara`, `Lainnya`. Halaman: tab per hari, tambah/ubah/hapus, dialog salin dengan checkbox hari. 185 tes Core + arsitektur hijau, Deptrac 0, `tsc` bersih. |
| 5 | Kalender Akademik + pembersihan mock & dokumentasi | ✅ | Tabel `calendar_events`, `SaveCalendarEvent`, `CalendarEventController` (prop `today` untuk bulan awal), `MasterMockData` dibersihkan, `CONTRACT.md` + dokumen arsitektur + memori diperbarui. Suite penuh 529 tes hijau, Deptrac 0 pelanggaran, `tsc` bersih, `npm run build` sukses. |

## Tasks

### Tahap 0 — Fondasi
- [x] Buat branch `feat/core-academic`; tulis plan ini ke `docs/ai/plan/fase-5/core-academic-backend-plan.md`.
- [x] Daftarkan `core.academic.view`, `core.academic.manage` — `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php` (`registerPermissions`), dan `'permission' => 'core.academic.view'` pada entri nav "Akademik".
- [x] Label izin — `modules/Core/config/permission_labels.php`.
- [x] Tempelkan ke peran default — `modules/Identity/config/roles.php`.
- [x] Gate grup `akademik` (`can:core.academic.view`) — `modules/Core/routes/web.php`.
- [x] `AcademicFormRequest` — `modules/Core/app/Http/Requests/AcademicFormRequest.php` (baru).
- [x] Tes: registry memuat 4 izin (`modules/Core/tests/Feature/MasterPermissionsTest.php`), peran default (`modules/Identity/tests/Feature/DefaultRolesTest.php`), tiga peran bisa membuka `/akademik/wali-kelas`, tanpa peran → 403, tamu → redirect — `modules/Core/tests/Feature/AcademicPermissionsTest.php` (baru).

**Selesai bila:** pengguna tanpa `core.academic.view` mendapat 403 di `/akademik/*` dan tidak melihat menu Akademik. Bukti: `php artisan test --compact modules/Core modules/Identity/tests/Feature/DefaultRolesTest.php` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 1 — Wali Kelas
- [x] `AssignHomerooms` — `modules/Core/app/Domain/Actions/AssignHomerooms.php` (baru).
- [x] `HomeroomRequest` (`homerooms` = array `class_id => teacher_id|none`) — `modules/Core/app/Http/Requests/HomeroomRequest.php` (baru).
- [x] `HomeroomController@index,update`; kelas tahun aktif + semua guru — `modules/Core/app/Http/Controllers/HomeroomController.php` (baru); arahkan route di `modules/Core/routes/web.php`; hapus `homerooms()` dari `MasterDataController.php`.
- [x] Halaman: kirim lewat Wayfinder, opsi "Belum ditentukan" (sentinel `none`; sekarang `String(null)`), keadaan kosong bila belum ada tahun aktif, `mock={false}` — `modules/Core/resources/js/Pages/Core/Academic/Homerooms/Index.tsx`.
- [x] Pindahkan tes "pages still on mock data" dari `/akademik/wali-kelas` ke `/kelola/impor` — `modules/Core/tests/Feature/MasterDataPagesTest.php`.
- [x] `CoreDemoSeeder`: beri wali kelas tiap rombel — `modules/Core/database/seeders/CoreDemoSeeder.php`.
- [x] Tes: simpan & tampil di `/master/kelas`; kosongkan; guru sekolah lain ditolak; kelas di luar tahun aktif ditolak; peran guru → 403 saat PUT; isolasi tenant — `modules/Core/tests/Feature/AcademicHomeroomTest.php` (baru).

**Selesai bila:** wali kelas yang disimpan muncul di daftar Kelas dan detail Guru setelah muat ulang. Bukti: `php artisan test --compact modules/Core` → hijau.

### Tahap 2 — Pengampu Mapel
- [x] Migrasi `teaching_assignments` — `modules/Core/database/migrations/0004_01_01_000003_create_teaching_assignments_table.php` (baru).
- [x] Model `TeachingAssignment` + factory; relasi `assignments()` di `ClassGroup`, `Teacher`, `Subject` — `modules/Core/app/Domain/Models/`, `modules/Core/database/factories/TeachingAssignmentFactory.php` (baru); tambah ke `deptrac.baseline.yaml`.
- [x] `SaveClassAssignments` — `modules/Core/app/Domain/Actions/SaveClassAssignments.php` (baru); penjaga di `DeleteTeacher.php`, aksi hapus mapel (lihat `SubjectController::destroy`), dan pembersihan di `DeleteClassGroup.php`.
- [x] `TeachingAssignmentRequest`, `TeachingAssignmentResource`, `TeachingAssignmentController@index,update` (baru di `Http/…`); route; hapus `assignments()` dari `MasterDataController.php`.
- [x] Isi `assignments` nyata — `ClassGroupController::show`, `TeacherController::show`.
- [x] Halaman: pemilih kelas (`?kelas=`), semua mapel, guru opsional, input JP, simpan satu kelas — `modules/Core/resources/js/Pages/Core/Academic/Assignments/Index.tsx`; tipe `Assignment` di `resources/js/types/master.ts`; perbarui impor tautan "Atur pengampu" di `Pages/Core/Master/Subjects/Index.tsx` (sekarang mengimpor dari `MasterDataController`).
- [x] `CoreDemoSeeder`: pengampu contoh.
- [x] Tes: simpan/ubah/hapus baris; muncul di detail kelas & guru; hapus guru/mapel yang dipakai ditolak; hapus kelas membersihkan; validasi JP; 403; isolasi tenant — `modules/Core/tests/Feature/AcademicAssignmentTest.php` (baru).

**Selesai bila:** pengampu tersimpan per kelas dan tampil di detail Kelas dan Guru. Bukti: `php artisan test --compact modules/Core` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 3 — Penempatan Siswa
- [x] `PlaceStudents` (memakai `SaveStudent`) — `modules/Core/app/Domain/Actions/PlaceStudents.php` (baru).
- [x] `PlacementRequest` (`action`, `source_class_id`, `target_class_id` wajib kecuali `graduate`, `student_ids[]`) dan `PlacementController@index,store` (baru); route; hapus `placement()` dari `MasterDataController.php`.
- [x] Halaman: tahun asal default = tahun aktif, tahun tujuan default = tahun berikutnya bila ada (sekarang hard-code `'2'`/`'3'`); rombel disaring per tahun; untuk `move` tujuan = tahun asal; pilih semua; kirim lewat Wayfinder; `mock={false}` — `modules/Core/resources/js/Pages/Core/Academic/Placement/Index.tsx`.
- [x] Tes: naik kelas menulis baris riwayat tahun tujuan dan memindah `class_id`; pindah rombel memberi catatan `Pindah kelas`; lulus mengosongkan kelas + catatan `Lulus`; siswa bukan anggota rombel asal ditolak; tujuan salah tahun ditolak; gagal satu = batal semua; 403; isolasi tenant — `modules/Core/tests/Feature/AcademicPlacementTest.php` (baru).

**Selesai bila:** penempatan massal mengubah kelas siswa dan riwayatnya terlihat di `/master/siswa/{id}`. Bukti: `php artisan test --compact modules/Core` → hijau.

### Tahap 4 — Jam Pelajaran
- [x] Migrasi `period_slots` — `modules/Core/database/migrations/0004_01_01_000004_create_period_slots_table.php` (baru).
- [x] Model `PeriodSlot` + factory (baru); `deptrac.baseline.yaml`.
- [x] `SavePeriodSlot`, `CopyPeriodDay` (tanpa `DeletePeriodSlot`, lihat Log keputusan) — `modules/Core/app/Domain/Actions/` (baru).
- [x] `PeriodSlotRequest`, `CopyPeriodDayRequest`, `PeriodSlotResource`, `PeriodSlotController` (baru); route; hapus `periods()` dari `MasterDataController.php`.
- [x] Halaman: tab Senin–Sabtu, tambah/ubah (`FormDialog`), hapus (`ConfirmAction`), dialog "Salin ke hari lain" dengan checkbox hari, keadaan kosong, `mock={false}` — `modules/Core/resources/js/Pages/Core/Academic/Periods/Index.tsx`; tipe di `master.ts`.
- [x] `CoreDemoSeeder`: isi dari `MasterMockData::periods()`.
- [x] Tes: CRUD; tumpang tindih & jam terbalik ditolak; "Jam ke" hanya menghitung slot Pelajaran; salin mengganti hari tujuan; 403; isolasi tenant — `modules/Core/tests/Feature/AcademicPeriodTest.php` (baru).

**Selesai bila:** template jam bisa disusun dan disalin antar hari. Bukti: `php artisan test --compact modules/Core` → hijau.

### Tahap 5 — Kalender Akademik + penutup
- [x] Migrasi `calendar_events` — `modules/Core/database/migrations/0004_01_01_000005_create_calendar_events_table.php` (baru).
- [x] Model `CalendarEvent` + factory (baru); `deptrac.baseline.yaml`.
- [x] `SaveCalendarEvent` (tanpa `DeleteCalendarEvent`, lihat Log keputusan), `CalendarEventRequest`, `CalendarEventResource`, `CalendarEventController` (baru); route; hapus `calendar()` dari `MasterDataController.php`.
- [x] Halaman: form tambah terhubung, ubah/hapus pada daftar, kursor awal = bulan berjalan di zona waktu sekolah (sekarang hard-code Des 2025), `mock={false}` — `modules/Core/resources/js/Pages/Core/Academic/Calendar/Index.tsx`.
- [x] `CoreDemoSeeder`: isi dari `MasterMockData::calendarEvents()`.
- [x] Bersihkan: komentar route "mockup phase" di `routes/web.php`; docblock `MasterDataController.php`; method `MasterMockData` yang tak lagi dipakai siapa pun (pertahankan yang dipakai Impor/Statistik/seeder).
- [x] Dokumentasi: `modules/Core/CONTRACT.md` (tabel, izin, Akademik bukan mock), `docs/architecture/modular-monolith.md` (baris Core), memori `project_mockup_first.md`.
- [x] Tes: CRUD; rentang tanggal terbalik ditolak; kategori tak dikenal ditolak; 403; isolasi tenant — `modules/Core/tests/Feature/AcademicCalendarTest.php` (baru).

**Selesai bila:** tidak ada halaman `/akademik/*` yang menampilkan "Tampilan contoh" dan semua bertahan setelah muat ulang. Bukti: `php artisan test --compact modules/Core tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran; `npm run build` → sukses.

## Batas tindakan
**Selalu**
- `vendor/bin/pint --dirty --format agent` dan `php artisan test --compact modules/Core` sebelum menandai tahap ✅.
- Perbarui tabel status + centang task di dokumen plan saat tahap mulai/selesai; catat penyimpangan di Log keputusan.
- Berhenti setelah tiap tahap dan tunggu persetujuan.

**Tanya dulu**
- Menambah kontrak di `modules/Core/app/Contracts`.
- Mengubah kode Identity/Platform selain `modules/Identity/config/roles.php` (termasuk membuat perintah sinkron peran untuk tenant lama).
- Mengubah migrasi yang sudah ada, atau menambah dependensi.

**Jangan**
- Menambah FK selain `tenant_id`; memakai `DB::table()` untuk data tenant; `WithoutModelEvents` di seeder.
- Menghapus tes; mengubah nama route `core.academic.*` atau nama komponen.
- Menambah entri `deptrac.baseline.yaml` selain untuk model baru pemakai `BelongsToTenant`.
- Commit/push tanpa diminta.

## Pengujian
Per tahap, feature test Pest di `modules/Core/tests/Feature/`: jalur utama, validasi & invarian Action, izin (peran `guru` bisa GET, 403 saat tulis; tanpa peran 403; tamu redirect), dan isolasi tenant (data sekolah A tidak terbaca/tertulis dari sekolah B). Rincian kasus ada di task tiap tahap. Factory untuk semua data; tanpa tinker. Setelah Tahap 5, minta user menjalankan suite penuh `php artisan test --compact`.

## Verifikasi end-to-end
1. `php artisan migrate:fresh --seed` lalu `php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDemoSeeder"` → sekolah contoh terisi.
2. Login sebagai admin sekolah (URL dari tool `get-absolute-url`), buka tiap halaman Akademik: ubah → muat ulang → data bertahan; tidak ada banner "Tampilan contoh".
3. Wali kelas & pengampu yang disimpan muncul di `/master/kelas/{id}` dan `/master/guru/{id}`; hasil penempatan muncul di riwayat `/master/siswa/{id}`.
4. Login sebagai guru: halaman terbuka, tombol simpan ditolak (403). Login sekolah lain: data terpisah.
5. `composer deptrac` → bersih; `npm run build` → sukses (jalankan bila perubahan frontend tak tampak).

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-02 — User meminta semua tahap dikerjakan berurutan tanpa berhenti di antara tahap (menggantikan "berhenti di antara tahap" untuk eksekusi ini) — instruksi user. Tiap tahap tetap diverifikasi sebelum ditandai ✅.
- 2026-10-02 (Tahap 2) — `SaveClassAssignments` memakai semantik sinkron (daftar yang dikirim = isi akhir kelas itu; mapel yang tidak dikirim kehilangan gurunya), bukan "guru kosong = hapus" seperti di rancangan awal — tidak perlu sentinel `none` dan satu aturan lebih sederhana. Request: `assignments` wajib ada (boleh kosong).
- 2026-10-02 (Tahap 4–5) — `DeletePeriodSlot` dan `DeleteCalendarEvent` tidak dibuat: hapus slot/peristiwa tidak punya aturan bisnis (belum ada jadwal pelajaran yang memakai slot), jadi controller menghapus langsung (pola `SubjectController` sebelum ada `DeleteSubject`). Buat Action-nya bila nanti ada aturan.
- 2026-10-02 (Tahap 5) — `PeriodSlotRequest::slotData()` dan `CalendarEventRequest::eventData()` mengembalikan array bertipe, supaya Action menerima bentuk yang dijanjikan PHPStan (`validated()` hanya `array<string, mixed>`).
- 2026-10-02 (Tahap 5) — Kalender mendapat prop `today` (tanggal sekolah di zona waktunya) untuk menentukan bulan awal grid dan menandai hari ini.
- 2026-10-02 (Tahap 5) — Pembersihan `MasterMockData`: dihapus `school()`, `academicYears()`, `semesters()`, `assignments()`, `normaliseLevel()`, `LEVELS`, `options()` (tak dipakai siapa pun). Yang tersisa dipakai `CoreDemoSeeder`, `InsightMockData`, dan pratinjau Impor. `MasterDataController::find()` (mati) ikut dihapus.

### Perlu diketahui user
- **Tenant lama tidak otomatis mendapat izin `core.academic.*`.** Tidak ada perintah sinkron peran: sekolah yang sudah ada di database mendapat 403 di `/akademik/*` dan tidak melihat menu Akademik sampai `php artisan migrate:fresh --seed` dijalankan. Bila ada tenant yang harus dipertahankan, perlu mekanisme sinkron di Platform/Identity — masuk daftar "Tanya dulu", belum dikerjakan.
- **Tes di luar Core ikut diubah karena perilaku memang berubah:** `modules/Identity/tests/Feature/RolesPageTest.php` (label izin staf-TU), `modules/Identity/tests/Feature/DefaultRolesTest.php` (izin peran default), `modules/Core/tests/Feature/MasterPermissionsTest.php` (registry memuat 4 izin), `modules/Core/tests/Feature/MasterDataPagesTest.php` (tes "halaman yang masih mock" kini memakai `/kelola/impor`). Tidak ada tes yang dihapus atau dilemahkan.
- **Tampilan diuji di browser sungguhan** lewat `tests/Browser/AcademicManagementTest.php` (6 tes, `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/AcademicManagementTest.php`): wali kelas, pengampu, penempatan (naik kelas lalu luluskan), jam pelajaran (tambah, salin hari lewat checkbox, tabrakan jam), kalender (tambah, ubah, hapus). Tes ini menemukan satu bug yang tidak terlihat di tes server: tombol "Konfirmasi penempatan" melempar `TypeError` karena `form.transform(...)` di Inertia v3 tidak mengembalikan form (rangkaian `.post()` gagal). Sudah diperbaiki di `Pages/Core/Academic/Placement/Index.tsx`. Yang belum dilihat manusia: tampilan visual dan responsif; jalankan `composer run dev` bila ingin memeriksanya.
- **PHPStan (`composer types:check`) gagal karena kode lama:** 29 galat tersisa, semuanya di berkas yang sudah ada sebelum fase ini (pola `validated()` di controller master data, `BerandaController`, `MasterMockData::classes()`, Platform/Identity). Tidak ada galat di berkas baru fase ini.
- **Penempatan `Naik kelas` mencatat `Kelas aktif`** di riwayat siswa (catatan dari `SaveStudent`, sesuai rancangan); `Pindah kelas` dan `Lulus` tercatat sebagai `Pindah kelas` / `Lulus`.
- **Belum di-commit.** Semua perubahan ada di working tree branch `feat/core-academic`.

### Temuan
- 2026-10-02 — Tes browser menangkap bug UI yang lolos dari tes server: di Inertia v3 `form.transform(...)` tidak mengembalikan form, jangan dirangkai dengan `.post()`. Panggil `form.transform(...)` terpisah sebelum `form.post(...)` (pola di `Assignments/Index.tsx` dan `Homerooms/Index.tsx` sudah benar).
- Menambah izin ke peran default juga mengubah daftar izin di halaman Sistem › Peran: `modules/Identity/tests/Feature/RolesPageTest.php` membandingkan label izin staf-TU secara persis dan perlu diperbarui tiap kali peran default berubah.
- `git stash` lama milik user (`stash@{0}`, dari commit `d4ff59b`) ada di repo; tidak disentuh.

### Hasil akhir
Kelima halaman Akademik (wali kelas, pengampu mapel, penempatan siswa, jam pelajaran, kalender) kini tersimpan di database, terpisah per sekolah, dan digate izin `core.academic.view|manage`. Tiga tabel baru (`teaching_assignments`, `period_slots`, `calendar_events`), tujuh Action baru, lima controller baru, `assignments` di detail Kelas dan Guru terisi data nyata.

Verifikasi (2026-10-02): `php artisan test --compact` → 529 lulus; suite browser `vendor/bin/pest -c phpunit.e2e.xml` → 13 lulus (6 baru); `composer deptrac` → 0 pelanggaran; `npx tsc --noEmit` bersih; `npm run build` sukses.

Tersisa / tindak lanjut:
- Periksa tampilan visual/responsif secara manual (alur sudah diuji otomatis di browser).
- Sinkron izin `core.academic.*` ke peran tenant lama (bila ada tenant yang harus dipertahankan).
- Jadwal pelajaran (mapel × kelas × slot jam) — plan tersendiri; kini bisa dibangun di atas pengampu + jam pelajaran.
- Impor Data, Statistik & Laporan, Integrasi, Absensi, PPDB masih mock.
- 29 galat PHPStan lama di luar fase ini.
