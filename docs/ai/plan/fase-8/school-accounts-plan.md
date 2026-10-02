# Akun login untuk siswa dan guru, tertaut ke data induk

> **Status dokumen:** Selesai
> **Dibuat:** 2026-10-02 · **Diperbarui:** 2026-10-02 · **Branch:** `feat/school-accounts`
> **Draf Absensi** (fase 10) disimpan di `docs/ai/plan/fase-10/attendance-plan.md`.

## Context
User ingin membangun Absensi (QR sekali pakai dari halaman siswa, absensi per jam oleh guru, notifikasi WA). Saat merencanakannya terlihat bahwa Absensi berdiri di atas hal yang belum ada, sehingga user memutuskan menyiapkan dasarnya dulu. Urutan yang disepakati: **fase 8 akun siswa & guru + sinkron peran (dokumen ini)** → fase 9 Integrasi WhatsApp → fase 10 Absensi (drafnya di Lampiran A). Jadwal pelajaran tidak dikerjakan.

Kondisi kode sekarang:
- Peran hanya `admin-sekolah`, `guru`, `staf-tu` (`modules/Identity/config/roles.php`). Tidak ada peran siswa.
- Login hanya dengan email: `users.email` wajib, unik per sekolah (`modules/Identity/database/migrations/0001_01_01_000000_create_users_table.php`); `AuthenticatedSessionController::store()` memvalidasi `email`. Banyak siswa tidak punya email.
- `students.user_id` dan `teachers.user_id` ada tetapi tidak pernah diisi; hanya dibaca sebagai `hasAccount` (`modules/Core/app/Http/Resources/StudentResource.php`, `TeacherResource.php`).
- Core tidak punya cara membuat akun: kontrak Identity hanya `ResolvesUsers` (baca).
- Izin baru tidak sampai ke sekolah yang sudah ada: hanya ada `permissions:sync`; peran bawaan diisi sekali saat `TenantCreated` (`modules/Identity/app/Infrastructure/Permissions/SeedDefaultRoles.php`).

## Goal
Setelah ini, admin sekolah bisa membuatkan akun untuk semua siswa satu kelas (atau satu siswa) dan untuk guru/tendik dari halaman Master Data; siswa login dengan kode sekolah, NIS, dan tanggal lahirnya, lalu wajib mengganti kata sandi; guru login dengan NIP; Beranda menyapa mereka sebagai siswa/guru yang benar; akun siswa yang lulus/pindah/keluar nonaktif sendiri; dan `php artisan roles:sync` membawa peran dan izin bawaan terbaru ke sekolah yang sudah ada.

## Batasan masalah

**Dalam cakupan**
- `users`: kolom `username` (unik per sekolah), `email` menjadi opsional, penanda wajib ganti kata sandi.
- Login dengan email **atau** nama pengguna; halaman ganti kata sandi pertama yang tidak bisa dilewati.
- Peran `siswa` (tanpa izin) dan perintah `roles:sync`.
- Kontrak Identity untuk membuat, mereset, menonaktifkan, mengaktifkan, dan memperbarui akun dari modul lain.
- Core: buat akun siswa per kelas dan per siswa (NIS + tanggal lahir), akun guru/tendik (NIP + kata sandi acak yang ditampilkan sekali), menautkan guru ke akun yang sudah ada lewat email, reset kata sandi, penonaktifan otomatis.
- Halaman `/users`: paginasi, saring per peran, kolom nama pengguna.
- Beranda mengenali siswa/guru yang login.
- Tes Pest (feature, browser), spec Playwright, dokumentasi.

**Di luar cakupan**
- Akun wali murid/orang tua.
- Lupa kata sandi mandiri untuk akun tanpa email — admin yang mereset.
- Membuat akun otomatis saat Impor CSV — setelah impor, admin menekan "Buatkan akun" per kelas.
- Daftar/kartu akun untuk dicetak — tidak perlu karena kata sandi awal = tanggal lahir.
- Melepas tautan akun (unlink) dan menghapus akun — akun hanya dinonaktifkan.
- Halaman dan izin khusus siswa (QR, dsb.) — fase 10.

**Asumsi**
- NIS unik per sekolah (`unique(tenant_id, nis)`) dan tidak pernah memuat `@`; NIP unik per sekolah bila diisi. Nama pengguna siswa dan guru berbagi satu ruang nama; bentrokan dilaporkan, tidak ditimpa.
- `->change()` pada kolom `email` berjalan di semua driver yang dipakai (Laravel 13 mendukungnya secara bawaan). Bila gagal di SQLite tes, catat di Temuan dan tanyakan sebelum mengedit migrasi lama di tempat.
- Belum ada UI untuk mengubah izin peran (halaman Sistem hanya baca), jadi `roles:sync` aman menyamakan peran dengan `config/roles.php`.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Modul lain tidak pernah mengimpor model `User`; `user_id` kolom polos; data pengguna lewat kontrak Identity (sumber: `AGENTS.md` § Identity, `modules/Identity/CONTRACT.md`).
- Core boleh memakai `IdentityPublic`, tidak sebaliknya; kontrak hanya DTO/skalar (sumber: `deptrac.php`, `docs/architecture/modular-monolith.md`).
- Jangan pernah meneruskan `school` ke `Auth::validate()`; rate limit di controller dengan kunci `{flow}:{tenant_id}:{identitas}:{ip}`; jawaban generik untuk semua kegagalan login (sumber: `AGENTS.md` § Identity, § Tenancy traps).
- `sessions.user_id` ambigu antar sekolah — kebijakan sesi ditegakkan lewat atribut model di middleware (sumber: `docs/architecture/modular-monolith.md` § Tenancy traps).
- Mail = Mailable antrean; tanpa Notification (keputusan terkunci).
- UI dasar dari `modules/Shared/resources/js/components/ui`; URL dari Wayfinder (sumber: `.ai/rules/js.md`). Aktifkan skill `modular-monolith`, `fortify-development` bila relevan, `laravel-best-practices`, `inertia-react-development`, `wayfinder-development`, `writing-tests`, `testing-best-practices`.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-02 | Urutan: akun siswa & guru + sinkron peran → Integrasi WhatsApp → Absensi; jadwal pelajaran dilewati | user (atas rekomendasi) |
| 2026-10-02 | Siswa login dengan NIS sebagai nama pengguna; email opsional | user |
| 2026-10-02 | Kata sandi awal siswa = tanggal lahir (`ddmmyyyy`), wajib ganti saat login pertama | user |
| 2026-10-02 | Guru & tendik juga bisa dibuatkan akun dengan NIP + kata sandi, selain undangan email | user |
| 2026-10-02 | Siswa lulus/pindah/keluar → akun dinonaktifkan otomatis; kembali aktif → diaktifkan lagi | user |
| 2026-10-02 | Guru tidak punya tanggal lahir di data induk → kata sandi awal acak, ditampilkan sekali ke admin, wajib ganti | hasil baca kode |
| 2026-10-02 | Siswa tanpa tanggal lahir dilewati dengan alasan, bukan diberi kata sandi lain | hasil desain |
| 2026-10-02 | Satu kolom login `login` ("Email atau NIS/NIP"): mengandung `@` → email, selain itu → `username` | hasil desain |
| 2026-10-02 | Pembuatan akun dari Core digate `core.master.manage` + `identity.users.create`; reset `identity.users.sendReset` — tanpa izin baru | hasil desain |
| 2026-10-02 | `roles:sync` di Identity (pemilik `config/roles.php`), membaca sekolah lewat `TenantDirectory` | hasil desain |
| 2026-10-02 | Migrasi baru, bukan mengedit migrasi `users` di tempat | hasil desain |

## Desain

### Identity — permukaan publik (`modules/Identity/app/Contracts`)
- `AccountProvisioner` (baru):
  - `create(NewAccount $account): int` — akun dengan nama pengguna, kata sandi awal, peran, `must_change_password = true`; mengembalikan id. Nama pengguna/email sudah dipakai → `UsernameTakenException`.
  - `resetPassword(int $userId, string $password): void` — kata sandi baru + wajib ganti.
  - `updateIdentity(int $userId, string $name, string $username): void` — mengikuti perubahan nama/NIS/NIP.
  - `deactivate(int $userId): void`, `reactivate(int $userId): void` — admin sekolah aktif terakhir tetap dilindungi (`DeactivationNotAllowedException` → dibungkus exception kontrak).
- `DTOs/NewAccount` (baru) — `name`, `username`, `?email`, `password`, `role`.
- `Exceptions/UsernameTakenException`, `Exceptions/AccountActionRefusedException` (baru).
- `ResolvesUsers` (ada) — tambah `findMany(array $ids): array<int, UserRecord>`.
- `UserRecord` (ada) — `email` menjadi `?string`; tambah `?string $username`, `bool $active`, `bool $mustChangePassword`. Periksa semua pemakai `->email` di luar Identity sebelum mengubah tipe.

### Identity — internal
- Migrasi `modules/Identity/database/migrations/0001_01_01_000010_add_account_columns_to_users_table.php` (baru): `username` string(64) nullable + unique `(tenant_id, username)`; `email` nullable; `must_change_password` boolean default false.
- `Domain/Models/User.php` — `username` fillable; cast `must_change_password`; `sendPasswordResetNotification()` berhenti bila `email` null. `UserFactory` — state `withUsername()`, `mustChangePassword()`.
- `Http/Controllers/Auth/AuthenticatedSessionController.php` — validasi `login` (string) menggantikan `email`; pilih kolom pencarian dari ada/tidaknya `@`; `Auth::validate()` hanya menerima kolom itu + `password`; kunci throttle memakai identitas huruf kecil; pesan gagal tetap generik di field `login`. `Pages/Identity/Auth/Login.tsx` — label "Email atau NIS/NIP", teks bantuan disesuaikan.
- `Http/Controllers/Auth/ChangePasswordController.php` + `Pages/Identity/Auth/ChangePassword.tsx` (baru) — `GET/PUT ganti-kata-sandi` (auth): kata sandi sekarang, kata sandi baru (`Password::defaults()`, tidak boleh sama dengan yang lama), konfirmasi; mematikan penanda. Bisa dibuka kapan saja oleh pengguna yang login.
- `Http/Middleware/RequirePasswordChange.php` (baru) — pengguna guard `web` dengan `must_change_password` dialihkan ke `password.change` untuk semua route kecuali route itu dan `logout`. Didaftarkan di `bootstrap/app.php` dalam satu panggilan `web(append:)` yang sudah ada, setelah `EnsureSessionTenant`.
- `Domain/Actions/ProvisionAccount.php`, `Infrastructure/Accounts/DefaultAccountProvisioner.php` (baru) — implementasi kontrak di atas `User` + `DeactivateUser::handleAsProvider()` / `ReactivateUser`; bind di `IdentityServiceProvider::register()`.
- `config/roles.php` — peran `siswa` (`label: Siswa`, `permissions: []`).
- `Infrastructure/Commands/RolesSyncCommand.php` (baru) — `roles:sync {--tenant=}`: untuk tiap sekolah dari `TenantDirectory::all()` panggil `TenantRoles::ensure()` per peran di `config('roles')`; mencetak jumlah sekolah. Idempoten. Didaftarkan lewat `$this->commands()` di provider.
- `Http/Controllers/UsersManagementController.php` + `Pages/Identity/Users/{Index,Edit}.tsx` — daftar berpaginasi (25) dengan saring `?role=` dan pencarian, kolom nama pengguna, email boleh kosong; "Kirim tautan reset" nonaktif untuk akun tanpa email.

### Core
- `Domain/Actions/CreateStudentAccounts.php` (baru) — daftar siswa → tiap siswa: lewati bila sudah punya akun, tidak aktif, atau tanpa tanggal lahir; `AccountProvisioner::create()` dengan `username = nis`, `password = birth_date->format('dmY')`, peran `siswa`; isi `user_id` (`forceFill`). Hasil: `created`, `skipped: [{name, reason}]`. Bentrok nama pengguna → dilewati dengan alasan.
- `Domain/Actions/CreateTeacherAccount.php` (baru) — guru ber-NIP: `username = nip`, kata sandi acak 10 karakter, peran pilihan admin (`guru` bawaan, atau `staf-tu`); email guru ikut bila ada. Mengembalikan kata sandi polos untuk ditampilkan sekali (flash), tidak disimpan.
- `Domain/Actions/LinkTeacherAccount.php` (baru) — menautkan guru ke akun yang sudah ada dengan email yang sama (`ResolvesUsers::findByEmail`); ditolak bila akun itu sudah tertaut ke guru lain. Jalur untuk guru tanpa NIP: undang lewat `/users`, lalu tautkan.
- `Domain/Actions/ResetLinkedPassword.php` (baru) — siswa: kembali ke tanggal lahir; guru: acak baru, ditampilkan sekali.
- `Domain/Actions/SaveStudent.php` (ada) — status meninggalkan `active` → `deactivate`; kembali `active` → `reactivate`; nama/NIS berubah → `updateIdentity`. `SaveTeacher.php` — nama/NIP berubah → `updateIdentity`. `DeleteStudent.php`, `DeleteTeacher.php` — akun tertaut dinonaktifkan.
- `Http/Controllers/AccountController.php` (baru) + route di grup `can:core.master.manage` (`modules/Core/routes/web.php`), tiap aksi juga `Gate::authorize('identity.users.create')` / `identity.users.sendReset`:

| Method & URL | Nama route |
| --- | --- |
| `POST master/kelas/{classGroup}/akun-siswa` | `core.master.classes.accounts` |
| `POST master/siswa/{student}/akun` | `core.master.students.account` |
| `POST master/siswa/{student}/akun/reset` | `core.master.students.account.reset` |
| `POST master/guru/{teacher}/akun` | `core.master.teachers.account` |
| `POST master/guru/{teacher}/akun/tautkan` | `core.master.teachers.account.link` |
| `POST master/guru/{teacher}/akun/reset` | `core.master.teachers.account.reset` |

- `StudentResource` / `TeacherResource` pada halaman detail: `account` = `{username, active, mustChangePassword}` atau `null` (dari `ResolvesUsers::findMany`); daftar tetap `hasAccount`.
- Halaman: `Pages/Core/Master/Students/Show.tsx` panel "Akun login" (status, Buat akun, Reset kata sandi, keterangan "NIS + tanggal lahir"); `Pages/Core/Master/Classes/Show.tsx` tombol "Buatkan akun siswa" + ringkasan dibuat/dilewati; `Pages/Core/Master/Teachers/Show.tsx` panel akun (buat dengan pilihan peran, tautkan, reset) dan `Alert` kata sandi sekali tampil.
- `Http/Controllers/BerandaController.php` + `Pages/Core/Beranda.tsx` — prop `me`: `{kind: 'student', name, nis, class}` / `{kind: 'teacher', name}` / `null` dari `user_id` yang cocok dengan `Auth::id()`; ringkasan akun hanya untuk yang memegang `identity.users.view`.

**Dipakai ulang**
- Identity: `DeactivateUser::handleAsProvider()`, `ReactivateUser`, `CreateUser` (pola), `UserPolicy`, pola throttle di `AuthenticatedSessionController`.
- Platform Contracts: `TenantRoles::ensure()`, `TenantDirectory::all()`, `TenantContext`.
- Core: `RendersMasterPage`, `FormDialog`, `ConfirmAction`, `StatusBadge` (`linked`/`unlinked`), `SaveStudent`, `PlaceStudents` (memanggil `SaveStudent`, jadi kelulusan massal ikut menonaktifkan akun).
- Tes: `schoolAs()`, `inSchool()`, `classIn()` (`modules/Core/tests/Feature/Support/helpers.php`); `schoolMemberSignsIn()` (`tests/Browser/Support/school.php`); `UserFactory::forTenant()`.

### Platform
- Tidak berubah. `EnsureSessionTenant` sudah mengakhiri sesi akun yang dinonaktifkan.

### Alur
1. Admin membuka Master Data › Kelas › X-1 → "Buatkan akun siswa" → "32 akun dibuat, 2 dilewati (tanpa tanggal lahir)".
2. Siswa membuka login, mengisi kode sekolah, NIS, tanggal lahir → dialihkan ke Ganti kata sandi → setelah mengganti, tiba di Beranda yang menampilkan nama dan kelasnya.
3. Admin membuka data guru → "Buat akun" → kata sandi acak tampil sekali → guru login dengan NIP dan menggantinya.
4. Siswa lupa kata sandi → admin menekan "Reset kata sandi" di data siswa → kembali ke tanggal lahir.
5. Kelas XII diluluskan di Penempatan Siswa → akun siswanya nonaktif.
6. Setelah rilis, operator menjalankan `php artisan roles:sync` → sekolah lama mendapat peran `siswa`.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Fondasi: dokumen plan, branch, kolom akun, peran `siswa`, `roles:sync` | ✅ | Migrasi `username` + `email` opsional + `must_change_password`, state factory `withUsername()` / `mustChangePassword()`, peran `siswa`, `roles:sync {--tenant=}`. 661 tes hijau (11 baru), Deptrac 0 pelanggaran, PHPStan tanpa galat baru. |
| 1 | Login email/nama pengguna, wajib ganti kata sandi, `/users` berpaginasi | ✅ | Field login menjadi `login` (email bila memuat `@`, selain itu `username`); `ChangePasswordController` + halaman `Identity/Auth/ChangePassword`; middleware `RequirePasswordChange` di grup web; `/users` berpaginasi (25) dengan `?q=` dan `?role=`. 678 tes hijau (17 baru), `tsc` bersih. |
| 2 | Kontrak `AccountProvisioner` + akun siswa dari Core, nonaktif otomatis | ✅ | Kontrak `AccountProvisioner` + `NewAccount` + dua exception, `ResolvesUsers::findMany`, `UserRecord` diperluas; `CreateStudentAccounts`, `ResetLinkedPassword`, `AccountController`, sinkron di `SaveStudent`/`DeleteStudent`; panel akun di halaman siswa dan tombol per kelas. `AccountProvisionerTest` (12 kasus), `StudentAccountsTest` (13 kasus). |
| 3 | Akun guru & tendik: buat, tautkan, reset | ✅ | `CreateTeacherAccount` (NIP + kata sandi acak lewat `flash.password`), `LinkTeacherAccount`, reset; sinkron di `SaveTeacher`/`DeleteTeacher`; panel akun di halaman guru. `TeacherAccountsTest` (11 kasus). |
| 4 | Beranda mengenali siswa/guru, tes browser, dokumentasi | ✅ | Prop `me` di Beranda, ringkasan akun hanya untuk pemegang `identity.users.view`; `tests/Browser/SchoolAccountsTest.php` (2 tes), `tests/E2E/school-accounts.spec.ts` (1 spec); `CONTRACT.md` Identity dan Core, dokumen arsitektur, `AGENTS.md`, memori. |

## Tasks

### Tahap 0 — Fondasi
- [x] Buat branch `feat/school-accounts`; salin plan ini ke `docs/ai/plan/fase-8/school-accounts-plan.md` (baru) dan Lampiran A ke `docs/ai/plan/fase-10/attendance-plan.md` (baru, status Draf).
- [x] Migrasi kolom akun — `modules/Identity/database/migrations/0001_01_01_000010_add_account_columns_to_users_table.php` (baru).
- [x] Model + factory — `modules/Identity/app/Domain/Models/User.php`, `modules/Identity/database/factories/UserFactory.php`.
- [x] Peran `siswa` — `modules/Identity/config/roles.php`.
- [x] `roles:sync` — `modules/Identity/app/Infrastructure/Commands/RolesSyncCommand.php` (baru), `modules/Identity/app/Infrastructure/Providers/IdentityServiceProvider.php`.
- [x] Tes — `modules/Identity/tests/Feature/AccountColumnsTest.php` (baru): nama pengguna sama boleh di dua sekolah, tidak di satu sekolah; dua akun tanpa email di satu sekolah boleh. `RolesSyncTest.php` (baru): sekolah yang perannya kurang mendapat peran dan izin dari config, dua kali jalan hasilnya sama, `--tenant` hanya menyentuh satu sekolah, sekolah baru mendapat `siswa` lewat `TenantCreated`.

**Selesai bila:** akun tanpa email tetapi bernama pengguna bisa disimpan, dan `roles:sync` menyamakan peran sekolah lama. Bukti: `php artisan test --compact modules/Identity tests/Architecture` → hijau.

### Tahap 1 — Login dan ganti kata sandi
- [x] Login `login` — `modules/Identity/app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `modules/Identity/resources/js/Pages/Identity/Auth/Login.tsx`; perbarui pemakai field lama: `tests/Browser/Support/school.php`, spec di `tests/E2E/`, `modules/Identity/tests/Feature/TenantAuthTest.php`.
- [x] Ganti kata sandi — `ChangePasswordController.php`, `Pages/Identity/Auth/ChangePassword.tsx` (baru), route `password.change` / `password.change.update` di `modules/Identity/routes/web.php`; tautan di menu akun `modules/Shared/resources/js/components/AccountMenu.tsx` (lewat prop/URL yang dibagikan, tanpa mengimpor Identity dari Shared).
- [x] Middleware — `modules/Identity/app/Http/Middleware/RequirePasswordChange.php` (baru), `bootstrap/app.php`.
- [x] `/users` — `UsersManagementController.php`, `Pages/Identity/Users/{Index,Edit}.tsx`, `resources/js/types/ManagedUser.ts`.
- [x] Tes — `UsernameLoginTest.php` (baru): login dengan nama pengguna, dengan email, nama pengguna sekolah lain ditolak, akun nonaktif ditolak dengan pesan sama, throttle per identitas. `PasswordChangeTest.php` (baru): akun bertanda dialihkan dari halaman mana pun, logout tetap bisa, kata sandi baru sama dengan lama ditolak, penanda mati setelah berhasil. Tambahan di `UserManagementTest.php`: paginasi, saring peran, akun tanpa email tampil.

**Selesai bila:** akun bernama pengguna bisa login dan tidak bisa membuka halaman lain sebelum mengganti kata sandi. Bukti: `php artisan test --compact modules/Identity` → hijau; `npx tsc --noEmit` → bersih.

### Tahap 2 — Akun siswa
- [x] Kontrak, DTO, exception, `findMany`, `UserRecord` — `modules/Identity/app/Contracts/` (sebagian baru); implementasi + binding — `modules/Identity/app/Infrastructure/Accounts/DefaultAccountProvisioner.php` (baru).
- [x] `CreateStudentAccounts`, `ResetLinkedPassword` — `modules/Core/app/Domain/Actions/` (baru).
- [x] `SaveStudent`, `DeleteStudent` — sinkron identitas dan nonaktif/aktif otomatis.
- [x] `AccountController` (bagian siswa) + route — `modules/Core/app/Http/Controllers/AccountController.php` (baru), `modules/Core/routes/web.php`.
- [x] `StudentResource` (`account`), `Students/Show.tsx`, `Classes/Show.tsx`.
- [x] Tes — `modules/Identity/tests/Feature/AccountProvisionerTest.php` (baru): buat, bentrok nama pengguna, reset menyalakan wajib ganti, nonaktif/aktif, admin terakhir dilindungi, gagal tertutup tanpa konteks sekolah. `modules/Core/tests/Feature/StudentAccountsTest.php` (baru): satu kelas dibuatkan akun dengan peran `siswa`, login NIS + tanggal lahir berhasil, siswa tanpa tanggal lahir/nonaktif/sudah berakun dilewati dengan alasan, jalan kedua tidak menggandakan, lulus lewat `PlaceStudents` menonaktifkan akun, kembali aktif mengaktifkan, NIS berubah → nama pengguna ikut, tanpa `identity.users.create` 403, isolasi sekolah.

**Selesai bila:** siswa satu kelas bisa login dengan NIS dan tanggal lahir setelah admin menekan satu tombol. Bukti: `php artisan test --compact modules/Identity modules/Core tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 3 — Akun guru & tendik
- [x] `CreateTeacherAccount`, `LinkTeacherAccount`; `ResetLinkedPassword` untuk guru — `modules/Core/app/Domain/Actions/` (baru).
- [x] `SaveTeacher`, `DeleteTeacher` — sinkron identitas, nonaktif saat dihapus.
- [x] `AccountController` (bagian guru) + route; `TeacherResource` (`account`); `Teachers/Show.tsx`.
- [x] Tes — `modules/Core/tests/Feature/TeacherAccountsTest.php` (baru): guru ber-NIP dibuatkan akun dengan peran pilihan dan login berhasil, kata sandi hanya muncul di flash sekali, guru tanpa NIP ditolak dengan petunjuk undangan, tautkan lewat email yang sama, akun yang sudah tertaut ke guru lain ditolak, reset memberi kata sandi baru, hapus guru menonaktifkan akun, izin, isolasi sekolah.

**Selesai bila:** guru bisa login dengan NIP, dan guru yang sudah punya akun email bisa ditautkan. Bukti: `php artisan test --compact modules/Core modules/Identity` → hijau.

### Tahap 4 — Beranda dan penutup
- [x] Prop `me` + tampilan — `modules/Core/app/Http/Controllers/BerandaController.php`, `modules/Core/resources/js/Pages/Core/Beranda.tsx`.
- [x] Tes — tambahan di tes Beranda Core: siswa melihat nama dan kelasnya dan tidak melihat ringkasan akun; guru melihat namanya; admin tak tertaut `me = null`.
- [x] Tes browser — `tests/Browser/SchoolAccountsTest.php` (baru): admin membuatkan akun satu kelas; siswa login dengan NIS + tanggal lahir, dipaksa mengganti kata sandi, tiba di Beranda dengan namanya; `assertNoJavaScriptErrors()`. Spec `tests/E2E/school-accounts.spec.ts` (baru): alur yang sama pada server sungguhan.
- [x] Dokumentasi: `modules/Identity/CONTRACT.md`, `modules/Core/CONTRACT.md`, `docs/architecture/modular-monolith.md`, salinan `AGENTS.md`, memori `project_mockup_first.md`; perbarui catatan di `docs/ai/plan/fase-10/attendance-plan.md` (akun siswa sudah ada).

**Selesai bila:** alur admin → siswa login → ganti kata sandi → Beranda lulus di browser. Bukti: `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/SchoolAccountsTest.php` → hijau; `php artisan test --compact modules/Identity modules/Core tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran; `npm run build` → sukses.

## Batas tindakan
**Selalu**
- `vendor/bin/pint --dirty --format agent` dan tes modul yang disentuh sebelum menandai tahap ✅.
- Perbarui tabel status, centang task, catat penyimpangan di Log keputusan.
- Berhenti setelah tiap tahap dan tunggu persetujuan.

**Tanya dulu**
- Mengedit migrasi `users` yang lama di tempat, atau mengubah `password_reset_tokens`.
- Menambah izin baru, peran selain `siswa`, atau dependensi.
- Mengubah kode Platform atau `deptrac.php`.
- Mengubah aturan kata sandi awal (tanggal lahir siswa, acak untuk guru).

**Jangan**
- Mengimpor `User` dari Core; menambah FK atau relasi Eloquent ke `users`.
- Menyimpan kata sandi polos di database, log, atau sesi lebih dari satu flash.
- Membedakan pesan gagal login menurut keadaan akun.
- Menghapus akun saat siswa/guru dihapus; menghapus tes; commit/push tanpa diminta.

## Pengujian
- **Tahap 0:** keunikan per sekolah, email kosong, `roles:sync` idempoten.
- **Tahap 1:** login dua identitas, anti-enumerasi, throttle, pengalihan wajib ganti, `/users`.
- **Tahap 2:** kontrak penyedia akun; pembuatan massal, yang dilewati, nonaktif otomatis, izin, isolasi sekolah.
- **Tahap 3:** akun guru, tautan email, reset.
- **Tahap 4:** Beranda; satu alur browser; satu spec Playwright.

Factory untuk semua data. Setelah Tahap 4, minta user menjalankan suite penuh `php artisan test --compact`.

## Verifikasi end-to-end
1. `php artisan migrate:fresh --seed`, lalu `php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDemoSeeder"`.
2. Login admin sekolah (URL dari tool `get-absolute-url`) → Master Data › Kelas › satu kelas → "Buatkan akun siswa" → ringkasan dibuat/dilewati.
3. Jendela privat: login dengan kode sekolah, NIS seorang siswa, tanggal lahirnya → halaman Ganti kata sandi; mencoba membuka `/beranda` kembali ke halaman itu; setelah mengganti → Beranda dengan nama dan kelas.
4. Data guru ber-NIP → "Buat akun" → kata sandi tampil sekali → login dengan NIP.
5. Penempatan Siswa → luluskan satu siswa → login siswa itu ditolak dengan pesan generik.
6. `/users` → saring peran Siswa, pindah halaman.
7. `php artisan roles:sync` dua kali → kali kedua tanpa perubahan. `composer deptrac` → bersih.

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-02 — User: setiap akun pada akhirnya punya email, tetapi tidak wajib; Tahap 1–4 dikerjakan tanpa berhenti di antara tahap.
- 2026-10-02 (Tahap 1) — `ListPager` + `useListFilters` dipindah dari `modules/Core/resources/js/Components` ke `modules/Shared/resources/js/components/ListPager.tsx`: utilitas teknis murni yang kini dipakai Core dan Identity.
- 2026-10-02 (Tahap 1) — Tautan "Ganti kata sandi" di `AccountMenu` memakai prop `changePasswordHref` (bawaan `/ganti-kata-sandi` di `TenantShell`), pola yang sama dengan `logoutHref`; Shared tidak mengimpor Identity.
- 2026-10-02 (Tahap 1) — Tes login lama diubah dari kunci `email` ke `login` (perilaku disengaja). Spec Playwright tidak diubah: `getByLabel("Email")` tetap cocok dengan label "Email atau NIS/NIP".
- 2026-10-02 (Tahap 1) — `SendResetLink` dan aksi konsol provider (kirim ulang undangan, reset) kini menolak akun tanpa email dengan pesan, bukan galat.
- 2026-10-02 (Tahap 0) — Peran bawaan menjadi empat: harapan jumlah/daftar peran diubah di `DefaultRolesTest`, `RolesPageTest`, `BerandaTest` (Core), dan `TenantApplicationsTest` (Platform). Perubahan perilaku yang disengaja.
- 2026-10-02 (Tahap 0) — `Console/SchoolAdminController::index()` menyaring dengan `if` biasa menggantikan `when()`; perilaku sama, tipe baris kini memuat `email: string|null`.

- 2026-10-02 (Tahap 2) — Halaman detail menerima prop `login: {account, can}` (trait `Http/Concerns/DescribesLinkedAccount`), bukan `account` di dalam `StudentResource`/`TeacherResource`: resource tetap bebas dari panggilan ke Identity, dan daftar tetap hanya memakai `hasAccount`.
- 2026-10-02 (Tahap 2) — Hasil pembuatan akun per kelas dikirim sebagai satu kalimat `flash.status` ("2 akun dibuat. Dilewati: Budi (tanpa tanggal lahir); 3 sudah punya akun."), tanpa komponen ringkasan baru.
- 2026-10-02 (Tahap 2) — `Panel` di `@shared/components/page-parts` mendapat prop opsional `actions` (memakai `CardAction`) untuk tombol "Buatkan akun siswa" di kepala panel.
- 2026-10-02 (Tahap 3) — `SaveTeacher` hanya menyinkronkan akun yang punya `username` (dibuat lewat NIP); akun email yang ditautkan tidak diberi nama pengguna.
- 2026-10-02 (Tahap 3) — Kata sandi sementara guru dibagikan lewat prop bersama `flash.password` (`app/Http/Middleware/HandleInertiaRequests.php`), tampil satu kali.
- 2026-10-02 (Tahap 4) — Beranda mengirim `accounts: null` dan `roles: []` kepada pengguna tanpa `identity.users.view` (sebelumnya guru dan staf ikut menerima angka akun). Kalimat hari itu untuk mereka menjadi "Anda masuk sebagai …".
- 2026-10-02 (Tahap 4) — Skenario dibagi agar tidak diuji dua kali: tes browser Pest mencakup dialog admin (kelas, siswa, guru, daftar pengguna); spec Playwright mencakup login pertama siswa, penahanan di halaman ganti kata sandi, dan login ulang — yang butuh sesi dimuat ulang tiap request.

### Temuan
- Kata sandi tanggal lahir mudah ditebak teman sekelas sampai siswa menggantinya. Penangkalnya di fase ini: wajib ganti saat login pertama dan throttle login per identitas. Reset oleh admin mengembalikannya ke tanggal lahir.
- `UserSummary` (Beranda admin) akan ikut menghitung akun siswa; periksa apakah angkanya masih bermakna dan catat bila perlu dipisah per peran.
- (Tahap 0) `->change()` pada `users.email` berjalan di SQLite; migrasi lama tidak perlu diedit.
- (Tahap 0) Membuat token reset untuk akun tanpa email gagal di database (`password_reset_tokens.email` NOT NULL). `User::sendPasswordResetNotification()` sudah berhenti lebih dulu, tetapi `SendResetLink` dan `Console/SchoolAdminController` (kirim ulang undangan, reset) masih menganggap email selalu ada — tangani di Tahap 1 bersama `/users`.
- (Tahap 0) PHPStan proyek sudah punya 29 galat sebelum fase ini; ukurannya "tanpa galat baru", bukan nol.

- (Tahap 2) `npx tsc --noEmit` hanya memeriksa `resources/js`: halaman di `modules/` tidak termasuk `include` di `tsconfig.json`. Diperiksa dengan konfigurasi sementara yang menambahkan `modules/**/resources/js/**/*`; hasilnya satu galat lama di `modules/Core/resources/js/Pages/Core/Academic/Placement/Index.tsx:187` (bukan dari fase ini, belum diperbaiki).
- (Tahap 4) Kunci `TenantCache` mengikuti tenant ambient: `ModuleFlagManager::enable()` yang dipanggil tanpa konteks sekolah (konsol provider, CLI) menghapus partisi `central:`, bukan milik sekolah, sehingga sekolah masih melihat jawaban lama sampai 5 menit. Bug Platform yang sudah ada; tidak diperbaiki di fase ini, dicatat di dokumen arsitektur.
- (Tahap 4) Sidebar siswa menampilkan menu modul yang belum bergate izin (Absensi, PPDB, Integrasi — semuanya masih mock). Halaman master data tetap 403 bagi siswa. Dibereskan saat modul itu mendapat izinnya (fase 9 dan 10).
- (Tahap 4) Di server sungguhan, membuat akun satu kelas memakan beberapa detik karena tiap kata sandi di-hash dengan biaya penuh; spec Playwright diberi batas waktu lebih panjang. Untuk kelas besar ini masih dalam satu request.

### Hasil akhir
Admin sekolah kini bisa membuatkan akun untuk siswa satu kelas atau satu siswa (NIS + tanggal lahir) dan untuk guru/tendik (NIP + kata sandi acak yang tampil sekali, atau menautkan akun email yang sudah ada) dari Master Data. Login menerima email atau NIS/NIP; akun yang kata sandinya ditentukan orang lain ditahan di halaman Ganti kata sandi sampai menggantinya. Akun mengikuti data induk: nama dan nomor ikut berubah, siswa yang lulus/pindah/keluar dinonaktifkan, yang dihapus dinonaktifkan. Beranda menyapa siswa dan guru dengan namanya. `php artisan roles:sync` menyamakan peran sekolah lama dengan config; peran `siswa` ditambahkan tanpa izin.

Verifikasi (2026-10-02): `php artisan test --compact` → 717 lulus (56 baru); `vendor/bin/pest -c phpunit.e2e.xml` → 19 lulus (2 baru); `npx playwright test` → 7 lulus (1 baru); `composer deptrac` → 0 pelanggaran; PHPStan 29 galat, sama dengan sebelum fase ini (tidak ada di berkas baru); `npm run build` sukses.

Tersisa / tindak lanjut:
- Belum di-commit; semua perubahan ada di working tree branch `feat/school-accounts`.
- Jalankan `php artisan roles:sync` di lingkungan yang sudah punya sekolah (di database dev sudah dijalankan).
- Periksa tampilan halaman Ganti kata sandi, panel "Akun login", dan Beranda siswa secara manual di HP.
- Akun tanpa email tidak punya "lupa kata sandi" mandiri; admin yang mereset. Akun wali murid belum ada.
- Impor CSV belum membuat akun; setelah impor, tekan "Buatkan akun siswa" per kelas.
- Bug cache flag modul (lihat Temuan) dan galat TypeScript lama di halaman Penempatan menunggu keputusan user.
- Berikutnya: fase 9 Integrasi WhatsApp, lalu fase 10 Absensi (draf di `docs/ai/plan/fase-10/attendance-plan.md`, direvisi dulu).

