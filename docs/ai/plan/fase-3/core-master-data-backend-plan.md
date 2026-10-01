# Backend halaman tenant — Core Master Data

## Context
Halaman tenant (`/master/*`) di modul **Core** masih mockup: `MasterDataController` membaca `Infrastructure/Mock/MasterMockData` dan tidak ada yang disimpan. Fase mockup dianggap selesai; tujuan sekarang mengganti mock dengan database nyata untuk **Master Data saja** (sekolah, tahun ajaran, semester, tingkat/jurusan, kelas, mapel, guru, siswa, ruangan, ekskul), **bertahap per entitas (vertikal)**, berhenti di antara tahap. Props Inertia ke halaman React dipertahankan sehingga `.tsx` nyaris tak berubah. Akademik, impor, statistik, integrasi, Absensi, PPDB **di luar cakupan** (tahap lanjutan).

## Status (perbarui setiap tahap berjalan/selesai)
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai · ⏸ menunggu persetujuan

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Fondasi: migrasi Core, izin `core.master.*`, school summary dari DB | ✅ | Selesai: loadMigrationsFrom + folder database, izin `core.master.view/manage` (registry, label, peran default, gate route `/master/*` & nav). School summary dari DB selesai di Tahap 1. Tenant lama: izin baru belum menempel di peran yang sudah ada — perlu sinkron peran. |
| 1 | Sekolah, Tahun Ajaran, Semester | ✅ | `school_profiles`/`academic_years`/`semesters`; `?jenjang` mock dihapus (jenjang dari profil); semester berjalan diturunkan dari tanggal (tombol "Jadikan berjalan" dihapus); nama sekolah read-only (milik Platform); tanpa FK DB (aturan arsitektur) — cascade di Action; model dicatat di `deptrac.baseline.yaml`; listener TenantCreated diganti `SchoolProfile::current()` (firstOrCreate). |
| 2 | Tingkat/Jurusan, Kelas, Mapel, Ruangan | ✅ | `grades` (di-seed dari jenjang oleh `SyncDefaultGrades`, tak diedit manual; ganti jenjang hanya re-seed bila belum ada kelas), `majors`, `rooms`, `subjects`, `classes` (kolom polos, tanpa FK). Wali kelas & jumlah siswa kelas menyusul di Tahap 3 / Akademik. Hapus jurusan/ruangan ditolak bila dipakai kelas. Kolom pengampu dihapus dari daftar mapel (milik Akademik › Pengampu). |
| 3 | Guru & Tendik, Siswa, Ekstrakurikuler | ✅ | `teachers`, `students`, `student_class_history`, `extracurriculars`, `extracurricular_members`. Pencarian/filter/paginasi server-side (25/halaman) untuk guru & siswa; anggota ekskul ditambah lewat NIS; riwayat kelas ditulis saat siswa ditempatkan/pindah/keluar; Form tambah tahun ajaran terisi otomatis (`SuggestAcademicYear`: tahun terakhir + 1, atau tahun berjalan bila belum ada). `CoreDemoSeeder` mengisi data contoh (`php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDemoSeeder"`). Tombol "Undang/buat akun" dihapus (akun dibuat dari Pengguna). Pengampu (`assignments`) masih mock sampai tahap Akademik. |
| — | Akademik, Impor, Statistik/Laporan, Integrasi, Absensi, PPDB | ⬜ | di luar cakupan plan ini |

Checklist per tahap (dicentang di dokumen): migrasi · model+factory+seeder · Action · FormRequest · policy/izin · controller · frontend (Wayfinder) · test Pest · Pint · Deptrac · CONTRACT.md · persetujuan user.

## Aturan yang harus dipatuhi
- Semua tabel tenant: `$table->tenantId()` + model `BelongsToTenant` (dari PlatformPublic). Hindari `DB::table()`; seeder jangan pakai `WithoutModelEvents`; jangan cache model.
- Tidak ada FK lintas modul kecuali `tenant_id`. Guru/siswa: kolom polos `user_id` nullable (tanpa FK/relasi ke Identity).
- Model di `Domain/Models`, logika tulis di `Domain/Actions` (pola Identity: `CreateUser`, dll.), policy di `Domain/Policies`, controller tipis per entitas di `Http/Controllers`, FormRequest di `Http/Requests`.
- Belum ada Contract baru (Core `CONTRACT.md`: "None yet"); contract siswa/guru baru dibuat saat Absensi/PPDB membutuhkannya.
- Izin lewat `PermissionRegistry` (`core.master.*`), dicek via Gate/Policy, seperti `identity.users.*`.
- Jalankan `vendor/bin/pint --dirty --format agent`; test Pest per tahap; update `modules/Core/CONTRACT.md` dan `docs/architecture/modular-monolith.md`.
- Baca `.ai/rules/js.md` sebelum menyentuh `modules/**/resources/js/**`.

## Tahap 0 — Fondasi (kecil, sebelum entitas)
- `modules/Core/database/{migrations,factories,seeders}`; `CoreServiceProvider::boot()` → `loadMigrationsFrom`; cek mapping `Modules\Core\Database\` di `composer.json` (tambah bila belum).
- Daftarkan izin `core.master.view|manage` via `PermissionRegistry`; beri ke peran sesuai `modules/Identity/config/roles.php` (admin sekolah: manage; lainnya: view). Bentuk seeding mengikuti listener `SeedDefaultRoles`.
- Ganti helper `render()` di controller: school summary (nama, kode, zona waktu, jenjang) dibaca dari record sekolah, bukan `?jenjang` session. Hapus `LEVEL_SESSION_KEY` setelah Tahap 1.
- Pecah `MasterDataController` menjadi controller per entitas seiring tahap; route name `core.master.*` dan nama komponen **tidak berubah** (`TenantNavigationTest` tetap hijau).

## Tahap 1 — Sekolah, Tahun Ajaran, Semester
- Tabel: `school_profiles` (1 baris/tenant: npsn, jenjang, kepemilikan, akreditasi, alamat, kontak, kepala sekolah), `academic_years` (nama, kurikulum, mulai, selesai, status draft|active|archived), `semesters` (FK ke academic_years — sesama modul boleh).
- Invarian di Action: hanya satu tahun `active` per tenant; semester dalam rentang tahun; semester `current` diturunkan dari tanggal (bukan disimpan), `week` dihitung.
- Halaman: `School/Show` (edit), `AcademicYears/Index`, `Semesters/Index` — tambah route POST/PUT/DELETE + FormRequest; frontend memakai Wayfinder (`@/actions/...`) untuk form.
- Auto-buat `school_profiles` default saat tenant dibuat? → listener `TenantCreated` (idempoten) di `CoreServiceProvider`, mengikuti pola Identity; untuk tenant lama: `firstOrCreate` saat akses.

## Tahap 2 — Tingkat/Jurusan, Kelas, Mapel, Ruangan
- Tabel: `grades`, `majors`, `classes` (grade_id, major_id?, academic_year_id, homeroom teacher_id nullable — FK ke `teachers` ditambahkan di Tahap 3 atau kolom dulu tanpa constraint), `subjects`, `rooms`.
- Seeder default tingkat per jenjang (logika `MasterMockData::grades()`/`majors()` dipindah ke seeder/Action `SeedDefaultGrades`, bukan dibuang).
- Halaman: `Grades/Index`, `Classes/Index|Show`, `Subjects/Index`, `Rooms/Index`.
- Aturan hapus: tolak bila masih dipakai (kelas berisi siswa, dsb.) dengan pesan Indonesia.

## Tahap 3 — Guru & Tendik, Siswa, Ekstrakurikuler
- Tabel: `teachers` (+ `user_id` nullable polos), `students` (+ `user_id`, status active|graduated|moved, `class_id` aktif), `student_class_history` (tahun, kelas, catatan — dipakai `Students/Show` history), `extracurriculars` + pivot anggota.
- Pencarian/filter/paginasi server-side untuk guru & siswa (data bisa ribuan) — sesuaikan props `Index` (tetap bentuk kolom, tambah `paginator`); cek `Components/MasterPage.tsx` untuk dukungan paginasi.
- Relasi: kelas ↔ siswa ↔ riwayat; `Classes/Show` memakai data nyata (hapus fallback `?: array_slice(...)` mock). Pengampu (`assignments`) masih mock sampai tahap Akademik.
- Setelah tahap ini: hapus `MasterMockData` bagian master yang tak terpakai (pertahankan yang masih dipakai Akademik/Statistik).

## Pengujian (Pest, feature, per tahap)
- Isolasi tenant: data tenant A tidak terlihat di tenant B (pola test Identity/`MasterDataPagesTest`).
- Otorisasi: tanpa izin → 403; guest → redirect login.
- Validasi & invarian (satu tahun aktif, rentang semester, hapus ditolak bila dipakai).
- Factory memakai state; tidak ada tinker.
- `php artisan test --compact modules/Core` + `tests/Architecture` (Deptrac/arch) per tahap; minta user menjalankan suite penuh di akhir.

## Verifikasi end-to-end
1. `php artisan migrate:fresh --seed` (dev seeder Platform) lalu buka tenant dev di `get-absolute-url`.
2. Per halaman: tambah/ubah/hapus lewat UI, muat ulang, data bertahan; ganti tenant → data terpisah.
3. `vendor/bin/deptrac` bersih; `vendor/bin/pint --dirty --format agent`; `npm run build`/`composer run dev` bila perubahan frontend tak tampak.

## Berhenti di antara tahap
Setiap tahap diakhiri ringkasan + menunggu persetujuan user sebelum tahap berikutnya.
