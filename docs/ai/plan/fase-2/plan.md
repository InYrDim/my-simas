# Fase 2 — Identity: role default, pengelolaan user, reset password tenant-aware, onboarding sekolah (aplikasi → ACC provider)

## Context

Fase 1 hijau: `modules/Platform` (tenancy, module registry, flag, Spatie teams via `HasTenantRoles`, queue/cache/storage tenant-aware) dan `modules/Identity` (login/logout tenant-scoped, rate limit keyed `login:{tenant_id}:{email}:{ip}`, `EnsureSessionTenant`, guard `provider` terpisah di central). Fase 2 membuat lapisan "manusia yang login dan mengelola sekolahnya sendiri": admin sekolah bisa mengelola user (guru, staf) tanpa developer, sekolah baru bisa mendaftar sendiri lalu di-ACC provider, dan `password_reset_tokens` berhenti menyeberang tenant.

Yang **tidak** masuk Fase 2: Absensi, PPDB, billing, UI provider di luar review aplikasi sekolah, self-register bebas. Fase 2 tetap fondasi.

## Keputusan terkunci (jawaban user + konsekuensi desain)

- **Onboarding sekolah**: admin sekolah mengisi form identitas sekolah + identitas dirinya di host **central** → tersimpan sebagai _tenant application_ berstatus pending → **admin provider yang ACC** di konsol platform → saat ACC: tenant dibuat, modul onboarding diaktifkan (dari config `onboarding_modules` — hanya modul ber-flag, default `['identity']`; core selalu aktif tanpa flag), **user pertama (role Admin Sekolah) dibuat oleh Identity lewat event**, dan ia menyelesaikan akunnya via link set-password di email, lalu login di domain/subdomain tenant. Bukan self-provisioning; ACC provider wajib. Provider dapat mengoreksi data sekolah (nama/slug/timezone) dari form ACC — data final tenant = hasil form.
- **Konsekuensi layering**: `users.tenant_id` NOT NULL → user pengaju **belum bisa dibuat** sebelum tenant ada. Identitas pengaju disimpan di tabel aplikasi (Platform); pembuatan user pertama terjadi di **Identity** via listener event `TenantApproved` (Platform publish, Identity listen — arah dependensi benar: Identity → PlatformPublic).
- **Nonaktifkan user** = kolom `deactivated_at` nullable (audit-friendly, mudah reaktivasi). Login ditolak; sesi aktif diakhiri via enforcement atribut (perluasan `EnsureSessionTenant`), **bukan** query `sessions.user_id`.
- **password_reset_tokens**: edit migration in-place (pre-production, konsisten pendekatan Fase 1 "belum ada data") → `tenant_id` + PK komposit `(tenant_id, email)`, dibuktikan `migrate:fresh`.
- **Undangan user oleh admin sekolah**: dua jalur — buat akun langsung (admin isi password) **atau** undang via email (password null → penerima set password lewat link). Self-register tetap tidak ada. Halaman penerimaan set-password dibangun **bersama provisioning admin pertama** (Stage 8) — email tidak boleh menunjuk halaman yang belum ada; Stage 10 tinggal menambah aksi undang di UI admin.
- **Role default per sekolah**: Admin Sekolah, Guru, Staf/TU — dibuat oleh **listener `TenantCreated` milik Identity** (roles = konsep sekolah, bukan Platform). Platform menyediakan mekanisme kontrak `TenantRoles`; pemetaan role→permission didefinisikan di Identity.
- **Email tanpa machinery Notification**: email transaksional tetap ada (reset password, undangan, link admin pertama), tapi **fitur Notification tidak dibangun** — pengiriman via Mailable queued langsung (`Mail::to()`), bukan kelas Laravel `Notification`. Tanpa in-app notification center, notification preferences, atau channel tambahan di Fase 2; machinery itu menyusul bila dibutuhkan.
- **Queue tenant-aware**: **sudah selesai di Fase 1** (Stage 4, `TenantQueueContext` save/restore sync-safe, terregistrasi di provider). Tidak ada pekerjaan queue; mailable queued otomatis terbawa konteks tenant. Test tetap membuktikan payload stamped.
- **Policy berbasis relasi dirintis**: `UserPolicy` menjadi contoh kerja pertama (permission = aksi, policy = "boleh untuk data yang mana"); konvensi didokumentasikan. Implementasi penuh menunggu modul Absensi.
- **Landing pasca-login tidak diubah** (tetap `route('home')`/welcome): dashboard khusus admin sekolah menyusul bersama modul bisnis; keputusan dicatat di docs (Stage 11).
- **Hasil grill (stress-test sebelum eksekusi)**:
    - Role name Spatie = **machine name** (`admin-sekolah`, `guru`, `staf-tu`); label tampilan ("Admin Sekolah", "Guru", "Staf/TU") hidup di config saja — rename label tanpa UPDATE baris roles di tiap tenant.
    - **Anti-lockout dua invarian** di `DeactivateUser`: tidak boleh menonaktifkan diri sendiri; tidak boleh menonaktifkan admin aktif terakhir di tenant.
    - **TTL token 60 menit untuk semua alur** (reset & set-password); tanpa tombol kirim-ulang — link hangus ditangani dengan membuat undangan baru (user password-null existing di-update, bukan duplikat).
    - Undangan di-gate **`identity.users.create`** (bukan permission terpisah).
    - **Respons generik selalu** pada permintaan reset (anti-enumerasi) — test Stage 4 direvisi.
    - Form publik: **IP throttle + honeypot** (captcha eksternal menyusul bila spam nyata).
    - **Provider dapat mengoreksi** data sekolah saat ACC.
    - Dev seeder: + provider user + **1 aplikasi pending (sekolah-c)** untuk mencoba alur review end-to-end.

## Fakta repo yang sudah diverifikasi

- `TenantCreated` (Contracts/Events, payload `tenantId`) sudah fired dari hook `created` model Tenant — **belum ada listener**; docs Platform menandai default-role seeding sebagai TODO Fase 2. PlatformDevSeeder membuat tenant via factory → event ikut fired, jadi listener Fase 2 otomatis ikut jalan di seeder dev.
- `TenantRoleResolver` (internal Platform) `resolveOrCreate/resolveOrNull` melempar `TenantNotSetException` tanpa konteks — kontrak publik baru untuk membuat role **dengan tenantId eksplisit** dibutuhkan (listener jalan dari central/CLI).
- `PermissionRegistry` + `permissions:sync` (idempotent, create-only) ada; baris `permissions` bersifat **global** (bukan per tenant) — role per tenant yang attach permission hanya butuh baris permission global sudah ada.
- `HasTenantRoles::assignTenantRole` create-if-missing dalam konteks aktif; `User` sudah `BelongsToTenant + HasTenantRoles`, `guard_name = 'web'`, factory punya `forTenant()`.
- `password_reset_tokens` masih `email` primary (Identity migration `0001_01_01_000000`); `users` belum punya `deactivated_at`, `password` belum nullable; `sessions.user_id` **ambigu lintas tenant** (users.id bigint per tenant bisa sama) → jangan pernah query sessions berdasarkan user_id untuk logout massal; enforcement atribut di middleware.
- `IdentityServiceProvider` sudah `registerPolicies()` (`UserPolicy` kosong — diisi Fase 2). Routes Identity hanya login/logout, deklarasi `Route::middleware('web')` sendiri di file modul.
- Platform punya alias middleware `central` (`EnsureCentralHost`) — dipakai grup `platform.*`; halaman modul resolve sebagai `<Module>/<Page>`; frontend posting via action Wayfinder.
- `redirectGuestsTo`/`redirectUsersTo` guard-aware di `bootstrap/app.php` (login tenant vs platform.login) — halaman baru mengikuti pola ini.
- Composer test gate = lint + phpstan(7) + deptrac + pest; `spatie/laravel-permission` ^8.3 terpasang; **tidak ada dependency baru** di Fase 2. Laravel ^13.17 — cek API broker/manager versi terpasang (`composer show`) sebelum menulis implementasi token repository.
- Deptrac: Identity → PlatformPublic legal; tidak ada trait publik baru yang dikonsumsi modul lain → baseline tidak perlu tumbuh (kontrak baru berupa interface/event/DTO, bukan trait).
- `TenantData` DTO tidak membawa `domain` — `TenantUrl` membaca attribute arrays dari `TenantHydrator` (internal, sudah di-cache), tanpa query baru dan tanpa perubahan DTO publik.

## Stages (satu commit logis per stage, semua gate hijau sebelum lanjut)

Gate tiap stage: `php artisan test --compact`, `vendor/bin/deptrac analyse`, `composer types:check`, `vendor/bin/pint --dirty --format agent`, `npm run check` bila frontend tersentuh, `php artisan migrate:fresh` di stage yang mengubah schema.

### Stage 1 — Schema: reset token tenant-aware + user deactivation (in-place)

1. Edit `0001_01_01_000000_create_users_table.php` (Identity): `users` tambah `deactivated_at` timestamp nullable; `password` → nullable. `password_reset_tokens`: `tenantId()` (macro FK→tenants, exception legal) + PK komposit `primary(['tenant_id','email'])` menggantikan `email` primary. Bukti `migrate:fresh` (tenants `0000_` selalu lebih dulu).
2. Model/factory Identity: cast `deactivated_at`, state factory `deactivated()` / `uninvited` (password null), sesuaikan dokumen class-level PHPDoc.
3. Catat trap di docs: `sessions.user_id` ambigu lintas tenant — dilarang dipakai sebagai kunci logout massal.
4. Commit: `feat(identity): tenant-aware reset tokens + deactivation columns`.

### Stage 2 — Role & permission default per tenant

1. PlatformPublic kontrak baru `TenantRoles`: `ensure(string $tenantId, string $name, array $permissions = []): void` dan `names(string $tenantId): array` — implementasi internal Platform (`TenantRoleResolver`), menjalankan konteks `TenantContext::run($tenantId, …)` sendiri (aman dipanggil dari central/CLI), dan **idempoten**; sebelum attach permission, jalankan `PermissionSync` internal (idempoten) agar baris permission global pasti ada. `names()` dipakai UI admin (Stage 9) untuk dropdown role.
2. Identity: konfigurasi pemetaan role default (`modules/Identity/config/roles.php`): `admin-sekolah` (label "Admin Sekolah") → semua `identity.users.*`; `guru`, `staf-tu` → set kosong dulu (diisi modul bisnis nanti). Role name Spatie = machine name; label hanya di config (rename label tanpa menyentuh DB per tenant). Identity mendaftarkan permission `identity.users.view/create/update/deactivate/sendReset` via `PermissionRegistry` dari provider.
3. Listener Identity `SeedDefaultRoles` pada `TenantCreated` (didftar di IdentityServiceProvider): panggil `TenantRoles::ensure(...)` untuk tiga role default.
4. Dev seeder: user dev `admin@sekolah-a.test` otomatis dapat role `Admin Sekolah` (root seeder assign via `HasTenantRoles` — glue layer, legal).
5. Tests: tenant baru → 3 role terbentuk dengan permission set benar; `tenant:create` ikut memicu seeding; role bernama sama di dua tenant terpisah; idempoten (fire ulang event tidak menduplikasi); `TenantRoles::ensure()` dengan tenant id tidak dikenal gagal jelas (fail closed tetap terjaga).
6. Commit: `feat: seed default school roles on TenantCreated`.

### Stage 3 — Deactivation enforcement

1. Login (Identity controller): kredensial cocok tapi `deactivated_at` ≠ null → gagal generik (pesan sama dengan kredensial salah — jangan bocorkan status akun).
2. `EnsureSessionTenant` (Platform, tetap generik tanpa import Identity): request dengan konteks tenant + user terautentikasi yang atribut `deactivated_at` ≠ null → logout + invalidasi sesi (pola sama seperti mismatch `tenant_id`). Konvensi atribut `deactivated_at` dicatat di docs (Stage 11) — tanpa trait publik baru, baseline Deptrac tidak tumbuh.
3. Helper kecil di Identity (action `DeactivateUser`/`ReactivateUser`) — UI admin menyusul di Stage 9; deactivation **tidak** menghapus role. **Dua invarian anti-lockout** di action: menolak menonaktifkan diri sendiri dan menolak menonaktifkan admin aktif terakhir di tenant.
4. Tests: user nonaktif tidak bisa login; sesi aktif diakhiri di request berikutnya; user nonaktif tidak muncul di resolver login tenant B (isolasi tetap); reaktivasi mengembalikan akses; invarian — self-deactivation & penonaktifan admin terakhir ditolak.
5. Commit: `feat(identity): enforce deactivated users at login and session`.

### Stage 4 — Password reset tenant-aware + TenantUrl

1. PlatformPublic kontrak `TenantUrl`: host untuk sebuah tenant (`{slug}.{central}` atau custom domain; scheme/port dikonfigurasi untuk dev `*.localhost`) — dipakai mailable yang dibangun **di dalam queue** (request root tidak bisa dipercaya di worker). Implementasi membaca data resolusi yang sudah di-cache (`TenantHydrator`, tanpa query baru).
2. Identity: `TenantDatabaseTokenRepository` (subkelas `DatabaseTokenRepository`) — insert `tenant_id` dari konteks; `exists/delete/recentlyCreated` scoped `(tenant_id, email)`; + broker wiring kustom (manager/repository terdaftar via container; cek API `PasswordBrokerManager` versi terpasang sebelum memilih mekanisme). Broker user resolution tetap lewat model `User` tenant-scoped — reset di host tenant hanya bisa menemukan user tenant itu.
3. Halaman + routes Identity (grup web tenant, guest): `forgot-password` (form email) dan `reset-password` (token+email dari URL). Rate limit di controller (pola login: key menyertakan `tenant_id`), bukan `throttle:` middleware. Central host tidak melayani reset (route tenant saja — controller menolak tanpa konteks). TTL token 60 menit untuk semua alur (reset & set-password). Respons permintaan reset SELALU generik — "jika email terdaftar, link telah dikirim" — untuk email tidak dikenal, user nonaktif, maupun user tanpa password (anti-enumerasi).
4. Mailable `ResetPasswordMail` (queued) dengan URL host tenant (`TenantUrl`) — dikirim langsung via `Mail::to()` (tanpa kelas Notification; lihat keputusan terkunci).
5. Tests: token dibuat di tenant A tidak valid di host tenant B (meski email sama); reset mengganti password user yang benar; token expired ditolak; permintaan reset untuk email tidak dikenal/nonaktif/tanpa password → respons generik dan TIDAK ada email terkirim (anti-enumerasi); konsumsi token oleh user nonaktif ditolak; link email memuat host tenant yang benar.
6. Commit: `feat(identity): tenant-aware password reset`.

### Stage 5 — Tenant applications (Platform)

1. Migration `tenant_applications` (Platform): id bigint, `school_name`, `desired_slug`, `timezone` (default `Asia/Jakarta`), `applicant_name`, `applicant_email`, `applicant_message` nullable, `status` (pending/approved/rejected), `admin_note` nullable, `decided_at` nullable, `decided_by` (id provider_users, FK dalam modul — legal), timestamps. Tanpa FK ke `users` (user belum ada; larang FK lintas modul).
2. Model + status enum + `TenantApplications` (Contracts): `submit(payload)`, `pending()`, `approve(id, decidedBy, payload)` — menerima koreksi school_name/slug/timezone dari form ACC (data final tenant = hasil form), `reject(id, note, decidedBy)`. Validasi submit: slug non-reserved, belum dipakai tenant/pending, email tanpa aplikasi pending lain (cek in-code).
3. Event baru `TenantApproved` (Contracts/Events): payload `tenantId`, `applicantName`, `applicantEmail`. `approve()` = transaksi: **validasi ulang slug** (defensif, aturan sama dengan `tenant:create`, memakai data final hasil koreksi form ACC), buat Tenant (event `TenantCreated` → roles ter-seed dari Stage 2), enable modul dari `config('tenancy.onboarding_modules')` (default `['identity']` — hanya modul ber-flag; core always-active tanpa flag. Platform membaca config, **tidak hardcode** modul lain), isi kolom keputusan, fire `TenantApproved`. Reject = set status saja. `approve/reject` idempoten (bukan pending → exception).
4. Tests: submit validasi (slug reserved/dupek, duplikat pending), approve membuat tenant + 3 role default + flag modul dari config + event payload benar, reject tanpa efek samping, idempotensi, transaksi roll-back bila gagal.
5. Commit: `feat(platform): tenant applications with provider approval`.

### Stage 6 — Konsol provider: review aplikasi

1. Routes Platform (grup `platform`, guard provider, central-only): index aplikasi pending + detail + aksi approve (form pre-filled school_name/slug/timezone yang dapat dikoreksi provider)/reject (POST, CSRF, konfirmasi note di reject).
2. Pages `Platform/Applications/Index.tsx` + `Platform/Applications/Show.tsx` (via Wayfinder actions).
3. Tests: provider harus auth guard provider; tenant user TIDAK bisa mengakses route platform (dan sebaliknya — pola Fase 1); approve dari UI menghasilkan tenant nyata end-to-end; koreksi data saat ACC tercermin di tenant final.
4. Dev seeder diperluas: satu `provider_users` untuk login konsol platform + satu `tenant_application` pending (sekolah-c) — alur review→ACC→provisioning bisa dicoba end-to-end tanpa isi form manual. (Role admin@sekolah-a.test sudah di Stage 2.)
5. Commit: `feat(platform): provider console application review with dev seed`.

### Stage 7 — Halaman publik pengajuan sekolah (central)

1. Routes Platform (grup `central`, tanpa auth): GET/POST pendaftaran sekolah. Rate limit by IP (middleware `throttle:` boleh di sini — tidak butuh konteks tenant) + **honeypot** field tersembunyi: terisi → submit ditolak diam-diam (anti-spam tanpa dependency).
2. Page `Platform/SchoolApply.tsx`: identitas pengaju (nama, email) + identitas sekolah (nama, slug, timezone) + pesan opsional; share props central (`tenant: null`) dipakai apa adanya.
3. Setelah submit: halaman sukses generik ("menunggu ACC") — tidak ada login dibuat.
4. Tests: validasi, duplikat pending ditolak, slug bentrok tenant existing ditolak, sukses → row pending; honeypot terisi → ditolak tanpa row; central host requirement.
5. Commit: `feat(platform): public school application page`.

### Stage 8 — Provisioning admin pertama + penerimaan set-password (Identity)

1. Listener Identity `ProvisionFirstAdmin` pada `TenantApproved`: dalam konteks tenant — buat `User` (nama+email pengaju, password null, `email_verified_at` null), `assignTenantRole('admin-sekolah')` (machine name, Q1), kirim Mailable `SetPasswordMail` (queued; bukti payload `tenant_id` ter-stamp). Mailable memakai `TenantUrl` — link set-password mengarah ke host tenant (bukan central).
2. **Halaman + route penerimaan dibangun di stage yang sama** (email tidak boleh menunjuk halaman yang belum ada): grup web tenant, guest — `set-password` (token+email dari URL) → set password + `email_verified_at = now()` + auto-login. Rate limit di controller. Token ditolak untuk user nonaktif.
3. Tests: user terbuat ter-pin ke tenant benar + role benar; mailable queued dengan payload tenant; email pengaju yang sudah ada di tenant LAIN tidak bentrok (unique komposit); approval ulang tidak menduplikasi user; **alur end-to-end ACC → email → set-password → login berhasil**.
4. Commit: `feat(identity): provision first school admin with set-password acceptance`.

### Stage 9 — Pengelolaan user oleh admin sekolah + UserPolicy

1. Routes/pages Identity (auth, konteks tenant): index user (nama, email, role, status), create (nama, email, password — buat langsung), edit (nama, role assign/remove via dropdown dari `TenantRoles::names()`, deactivate/reactivate, kirim reset link). Undangan via email di-gate **`identity.users.create`** — bukan permission terpisah. UI tidak menyediakan tombol kirim-ulang undangan (keputusan terkunci).
2. Direct-create menandai `email_verified_at = now()` — data diinput admin terpercaya; undangan (password null) diverifikasi saat set-password.
3. `UserPolicy` diisi: `viewAny/create/update/deactivate/sendReset` di-gate permission `identity.users.*` (role Admin Sekolah dari Stage 2). Guru/Staf default **tanpa** permission tersebut → tertolak. Policies selalu memvalidasi target user satu tenant (scope sudah dari `BelongsToTenant`; policy menegaskan ulang).
4. Semua aksi lewat actions Domain (mis. `CreateUser`, `AssignRole`, `DeactivateUser`) supaya bisa diuji tanpa HTTP; role assign/remove memakai `HasTenantRoles` (guard `web`).
5. Landing pasca-login **tidak diubah** (tetap `route('home')` — welcome): dashboard khusus menyusul bersama modul bisnis; aksi admin diakses lewat halaman modul Identity.
6. Tests: Admin Sekolah boleh semuanya; Guru/Staf ditolak 403; admin tenant A tidak bisa melihat/mengubah user tenant B (404/403 — scope+policy); buat user dengan email sama seperti tenant lain OK; deactivate dari UI menutup akses (lintasan Stage 3); tombol nonaktifkan tidak tersedia untuk diri sendiri & admin aktif terakhir (invarian Stage 3); kirim reset link dari UI memakai machinery Stage 4.
7. Commit: `feat(identity): school-admin user management with policy gate`.

### Stage 10 — Undangan via email (aksi UI admin)

1. Aksi "undang" di UI Stage 9: buat user password-null + Mailable `SetPasswordMail` — **machinery lengkap dari Stage 8** (satu jalur token, satu halaman penerimaan; yang baru hanya konteks penerima & entry point UI). Mengundang user yang SUDAH ada dengan password null = UPDATE user itu (hapus token lama, token + email baru) — bukan duplikat (unique komposit melindungi); inilah jalur pengganti kirim-ulang yang sengaja tidak dibuat.
2. Tests: undang → set-password (halaman Stage 8) → login sukses; token kedaluwarsa/terpakai ditolak; user nonaktif ditolak; link host tenant benar; user tanpa password tidak bisa login (verify gagal — test eksplisit).
3. Commit: `feat(identity): email invitations from school admin UI`.

### Stage 11 — Dokumentasi + ringkasan

1. `docs/architecture/modular-monolith.md`: tambah Fase 2 — aturan role default (listener `TenantCreated`, kontrak `TenantRoles`), alur aplikasi→ACC→provisioning (termasuk modul onboarding dari `config('tenancy.onboarding_modules')`), reset token tenant-aware, konvensi policy ("permission = aksi, policy = data mana", contoh `UserPolicy`), konvensi atribut `deactivated_at` (middleware generik), trap `sessions.user_id`, `TenantUrl` untuk mail dari queue, keputusan landing pasca-login, keputusan **tanpa machinery Notification** (email transaksional via Mailable langsung), invarian anti-lockout, konvensi role machine-name + label config, dan konvensi respons generik anti-enumerasi. Hapus/arsipkan bagian "Fase 2 notes (planned)".
2. `CONTRACT.md` Identity + Platform final (tabel: `password_reset_tokens` kini tenant-scoped, `tenant_applications`, kolom `users` baru; kontrak baru `TenantRoles`, `TenantUrl`, event `TenantApproved`; events published/consumed kedua modul). Sinkronkan addendum AGENTS.md (bagian Identity) dengan CONTRACT.md.
3. Ringkasan akhir: keputusan menyimpang + alasan, risiko tersisa, usulan Fase 3 (permission UI, expiry job `expires_at`, dashboard admin sekolah, modul Absensi memakai lapisan policy relasi).
4. Commit: `docs: fase 2 architecture and contract updates`.

## Peta test → kriteria

File test baru:

- `modules/Identity/tests/Feature/DefaultRolesTest.php` → role default per tenant, idempoten, isolasi antar tenant (Stage 2)
- `modules/Identity/tests/Feature/DeactivationTest.php` → login/sesi nonaktif + invarian anti-lockout (Stage 3)
- `modules/Identity/tests/Feature/TenantPasswordResetTest.php` → token tenant-scoped, expiry, host link (Stage 4)
- `modules/Platform/tests/Feature/TenantApplicationsTest.php` → submit/approve/reject/idempotensi (Stage 5)
- `modules/Platform/tests/Feature/ProviderReviewTest.php` → guard + end-to-end ACC (Stage 6)
- `modules/Platform/tests/Feature/SchoolApplyTest.php` → form publik central (Stage 7)
- `modules/Identity/tests/Feature/FirstAdminProvisioningTest.php` → user+role+mailable + penerimaan set-password end-to-end (Stage 8)
- `modules/Identity/tests/Feature/UserManagementTest.php` + `UserPolicyTest.php` → CRUD admin + gate + invarian anti-lockout (Stage 9)
- `modules/Identity/tests/Feature/InvitationTest.php` → undangan dari UI admin + set-password (Stage 10)

Arch tests + deptrac tetap penjaga struktural di semua stage (Identity hanya boleh ke Shared + PlatformPublic).

## Risiko & default yang diambil

- **Halaman pengajuan sekolah dimiliki Platform** (bukan Identity): tabel `tenant_applications` + tenant lifecycle ada di Platform; pengaju bukan user Identity sehingga tidak ada keuntungan menaruhnya di Identity. Identity tetap menerima `TenantApproved` untuk membuat user.
- **Set-password untuk admin pertama & undangan** memakai `password_reset_tokens` yang sama (tabel token generik) dengan mailable berbeda; email terverifikasi saat set-password. Password pengaju tidak pernah disimpan di tabel aplikasi.
- **Tanpa machinery Notification**: kelas `Notification`, channel database/broadcast, dan notification center bukan bagian Fase 2. Bila nanti dibutuhkan, Mailable transaksional ada dipindah ke notifikasi tanpa mengubah jalur token/link.
- **Konsumsi silang token**: token buatan `SetPassword` yang dikonsumsi lewat halaman reset tetap sah (set password tanpa verifikasi email) — tabel tidak tahu konteks penerbit; halaman menentukan efek. Dampak kecil, diterima dan dicatat di docs.
- **Modul default saat ACC dari config**: `config('tenancy.onboarding_modules')` default `['identity']` — hanya modul ber-flag (core selalu aktif); tanpa `identity` aktif, tenant baru tidak bisa login sama sekali. Platform tidak hardcode modul lain (prinsip registry tetap terjaga).
- **TTL 60 menit untuk semua token** (keputusan user; berlawanan saran TTL panjang untuk undangan): link undangan sering hangus → ditangani dengan undangan baru yang meng-update user password-null existing. Diterima sebagai trade-off kesederhanaan.
- **Spam form publik** ditangani throttle IP + honeypot; captcha eksternal hanya bila spam nyata terjadi (butuh dependency — di luar batasan Fase 2).
- **Urutan listener `TenantCreated`** tidak diandalkan untuk order antar modul: kontrak `TenantRoles::ensure` menjalankan `PermissionSync` internal sendiri sebelum attach permission (idempoten), sehingga tidak ada dependensi urutan registrasi provider.
- Custom token repository/broker menyentuh API internal auth Laravel (versi ^13.17): diverifikasi dulu via `composer show` + `search-docs` di awal Stage 4; kalau hook resmi tidak tersedia, fallback = manager kustom ter-bind di container oleh Identity (tanpa patch vendor).
- Deactivation tidak menghapus role dan tidak menghapus user (soft-keep untuk audit); penghapusan permanen bukan bagian Fase 2.
- `Guru`/`Staf/TU` lahir dengan permission kosong — permission bisnis (absensi dsb.) menyusul saat modulnya dibangun; role set diubah lewat config `roles.php` tanpa migration.
- Landing pasca-login tetap welcome (`home`): dashboard admin sekolah menunggu modul bisnis; hindari konflik route `/` root vs modul.
- Tidak ada dependency baru; tidak ada perubahan ruleset Deptrac (kontrak baru = interface/event/DTO; baseline trait tidak tumbuh).
