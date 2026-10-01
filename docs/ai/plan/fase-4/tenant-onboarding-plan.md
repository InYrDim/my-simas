# Pendaftaran sekolah: akun pemohon → onboarding → ACC → masuk ke sekolah

> **Status dokumen:** Berjalan
> **Dibuat:** 2026-10-01 · **Diperbarui:** 2026-10-01 · **Branch:** `feat/tenant-onboarding`

## Context
Pendaftaran sekolah sekarang berjalan tanpa akun. Calon admin mengisi form publik `/daftar-sekolah` (`modules/Platform/app/Http/Controllers/SchoolApplyController.php`), provider menyetujui di console (`ApplicationReviewController`), lalu `DefaultTenantApplications::approve()` membuat tenant, memulai trial pada paket default, dan memicu event `TenantApproved`. Identity (`ProvisionFirstAdmin`) membuat admin pertama **tanpa password** dan mengirim email "atur kata sandi". Setelah itu ia login dengan kode sekolah + email + password.

Kelemahannya: pemohon tidak punya akun, jadi tidak bisa melihat status pengajuan, tidak bisa memperbaiki pengajuan yang ditolak, tidak memilih paket, dan baru punya password setelah menunggu email.

## Goal
Setelah ini, calon admin sekolah bisa membuat akun, mengisi data sekolah dan memilih paket di halaman onboarding, mengajukan, melihat statusnya, memperbaiki bila ditolak, dan setelah disetujui provider cukup login dengan email dan password yang sama untuk langsung mendarat di `/beranda` sekolahnya, dengan trial berjalan pada paket yang dipilih.

## Batasan masalah

**Dalam cakupan**
- Akun pemohon (daftar sendiri dan undangan dari provider), verifikasi email, login/keluar.
- Onboarding: data sekolah + pilih paket, status pengajuan, ajukan ulang setelah ditolak.
- ACC memulai trial pada paket terpilih dan memindahkan password pemohon ke admin sekolah.
- Login pemohon yang sudah disetujui membuka sesi sekolah.
- Email verifikasi, undangan, disetujui, ditolak; lupa kata sandi pemohon.

**Di luar cakupan**
- Login sekolah tanpa kode sekolah — aturan tenancy "sekolah dikenali dari kode sekolah" tetap.
- Satu akun pemohon untuk banyak sekolah — satu pemohon = satu pengajuan = satu sekolah.
- Profil sekolah lengkap (NPSN, jenjang, alamat) di onboarding — tetap diisi di Master Data setelah masuk.
- Pembayaran di onboarding — `PaymentGateway` masih stub; aktivasi berbayar tetap dari console provider.

**Asumsi**
- Tabel `plans` sudah terisi (`BillingMasterDataSeeder`). Bila kosong, onboarding tidak bisa menawarkan paket.
- Mail berjalan lewat queue yang ada; di dev tautan dibaca dari mail log.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- `users.tenant_id` wajib dan `unique(tenant_id, email)`, jadi pemohon tidak bisa menjadi `User` sebelum ACC. Akun pemohon adalah entitas pusat milik Platform, sejajar `ProviderUser` (sumber: `modules/Identity/database/migrations/0001_01_01_000000_create_users_table.php`, `modules/Platform/CONTRACT.md`).
- Urutan layer Shared → Platform → Identity → Core: Platform tidak boleh mengimpor Identity. Jembatan ke sesi sekolah memakai inversi: Platform mendefinisikan kontrak, Identity mengimplementasikan (sumber: `docs/architecture/modular-monolith.md`).
- Permukaan publik modul hanya `app/Contracts/**`; billing tetap internal Platform (sumber: `modules/Platform/CONTRACT.md`).
- Tanpa foreign key selain `tenant_id`; arch test menolak `constrained(` lain (sumber: `tests/Architecture/ModularMonolithTest.php`).
- Mail berupa Mailable ter-queue; URL dibuat sebelum di-queue (pola `modules/Identity/app/Infrastructure/Onboarding/ProvisionFirstAdmin.php`).

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-01 | Akun pemohon dibuat sendiri **dan** bisa diundang provider | user |
| 2026-10-01 | Setelah ACC, akun pemohon jadi jembatan; login sekolah biasa tetap pakai kode sekolah | user |
| 2026-10-01 | Form `/daftar-sekolah` tanpa akun diganti alur baru | user |
| 2026-10-01 | Pengajuan ditolak bisa diperbaiki dan diajukan ulang | user |
| 2026-10-01 | Onboarding memilih paket; trial dimulai pada paket itu | user |
| 2026-10-01 | "Tanpa registrasi publik" tetap untuk user sekolah; registrasi publik hanya untuk pemohon | hasil baca kode, perlu dicatat di docs arsitektur |
| 2026-10-01 | Satu kredensial: hash password pemohon **dipindah** ke admin sekolah saat ACC | hasil desain |
| 2026-10-01 | Trial dimulai saat ACC, bukan saat mengajukan (tenant belum ada sebelum ACC) | hasil baca kode |

## Desain

### Alur
1. `/daftar-sekolah` → daftar akun pemohon (nama, email, password) → email verifikasi.
2. `/pemohon` (onboarding): **(a)** data sekolah — nama, kode/slug, zona waktu, pesan; **(b)** pilih paket — kartu paket aktif dengan keterangan "Mulai trial N hari, tanpa pembayaran" → **Ajukan** → status *Menunggu*.
3. Console provider → ACC (boleh mengoreksi data dan paket) atau Tolak + catatan. Pemohon mendapat email.
4. Ditolak → `/pemohon` menampilkan catatan dengan form terisi → **Ajukan ulang** (baris yang sama kembali *Menunggu*).
5. Disetujui → login di `/pemohon/masuk` dengan email + password yang sama → sesi sekolah dibuka → `/beranda`. Login sekolah biasa juga jalan.
6. Jalur provider: console → "Undang pemohon" → tautan atur kata sandi → lanjut ke langkah 2.

### Platform — akun pemohon (internal)
- Tabel `applicants`: name, email (unik), password nullable (undangan belum aktif / sudah dipindah), `email_verified_at`, `tenant_id` nullable (diisi saat ACC), remember_token. Model `Applicant` (Authenticatable, **bukan** `BelongsToTenant`).
- Guard `applicant` + provider `applicants` di `config/auth.php`, mengikuti guard `provider`.
- `tenant_applications` bertambah `applicant_id` (kolom polos berindeks), `plan_key`, `submitted_at`. Kolom `applicant_name/email` tetap, diisi dari akun pemohon, sehingga halaman review dan DTO tidak berubah bentuk.
- Verifikasi email, undangan, dan reset kata sandi memakai signed URL sementara (`URL::temporarySignedRoute`). Tanpa tabel token baru.

### Platform — paket & trial (internal)
- `plan_key` divalidasi saat ajukan, ajukan ulang, dan ACC: paket ada, `is_active`, tidak diarsipkan.
- Onboarding membaca daftar paket dari model `Plan` yang ada (urut `sort_order`) dan `config('billing.trial_days')`.
- `DefaultTenantApplications::approve()` memanggil `SubscriptionManager::startTrial()` dengan `plan_key` pengajuan, fallback `config('billing.trial_plan')`. Perilaku lama dipertahankan: paket hilang hanya menulis log warning dan tidak menggagalkan ACC.

### Platform — kontrak publik (`modules/Platform/app/Contracts/`)
- `TenantApplications`: `submit(int $applicantId, array $school)` (signature berubah), `resubmit(int $applicantId, array $school)` (hanya dari *rejected*), `forApplicant(int $applicantId): ?ApplicationData`.
- `DTOs/ApplicationData`: tambah `applicantId`, `planKey`, `submittedAt`.
- `Events/TenantApproved`: tambah `?string $passwordHash = null` (event sinkron dalam transaksi, tidak di-queue).
- **Baru** `SchoolSessionOpener::attempt(string $tenantId, string $email, string $password, bool $remember): bool` — default Platform mengembalikan `false`; Identity mengikat implementasinya.
- **Baru** `TenantSession::remember(string $tenantId): void` — membungkus `ResolveTenant::SESSION_KEY` yang internal.

### Identity
- `ProvisionFirstAdmin`: bila event membawa hash → isi password dan `email_verified_at`, tanpa email set-password; bila tidak → jalur lama.
- `DefaultSchoolSessionOpener`: di dalam `TenantContext::run`, cocokkan email + password ke `User` aktif tenant itu, login guard `web`, `TenantSession::remember`, regenerate sesi. Aturan login sekolah (akun non-aktif ditolak, error generik) tetap.

### Route (host pusat, modul Platform)
| Route | Guna |
| --- | --- |
| GET/POST `/daftar-sekolah` | daftar akun pemohon (honeypot + throttle dipertahankan) |
| GET/POST `/pemohon/masuk`, POST `/pemohon/keluar` | login pemohon; yang sudah disetujui → `SchoolSessionOpener` → `/beranda` |
| GET `/pemohon/verifikasi/{applicant}` (signed), POST kirim ulang | verifikasi email |
| GET/POST `/pemohon/undangan/{applicant}` (signed) | atur kata sandi dari undangan |
| GET/POST `/pemohon/lupa-sandi`, GET/POST `/pemohon/atur-ulang/{applicant}` (signed) | lupa kata sandi pemohon |
| GET `/pemohon`, POST/PUT `/pemohon/pengajuan` | onboarding, status, ajukan, ajukan ulang |
| Console: GET/POST `applicants` | daftar pemohon + undang |

Halaman React di `modules/Platform/resources/js/Pages/Platform/Applicant/` memakai `AuthShell` dan `AuthInput` dari `modules/Shared/resources/js/components/`.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 1 | Akun pemohon: daftar (mengganti `/daftar-sekolah`), login/keluar, verifikasi email | ✅ | Tabel `applicants`, guard `applicant`, daftar + honeypot, login/keluar, verifikasi lewat signed URL, halaman pemohon (shadcn). Form data sekolah sudah pindah ke `/pemohon` memakai kontrak `submit()` lama (lihat Log keputusan). 411 tes lulus, Deptrac 0 pelanggaran, build sukses. Halaman belum dibuka di browser. |
| 2 | Onboarding: data sekolah + pilih paket, status, ajukan ulang, trial pada paket terpilih, email keputusan | ✅ | Pengajuan terikat ke akun (`applicant_id`, satu per pemohon), pilih paket di onboarding, trial pada paket itu saat ACC (provider bisa mengoreksi), ajukan ulang di baris yang sama, email disetujui/ditolak. 428 tes lulus, Deptrac 0 pelanggaran, tipe TS bersih. Halaman belum dibuka di browser. |
| 3 | Jembatan ACC: password dipindah, login pemohon membuka sesi sekolah | ✅ | Saat ACC hash password pemohon dipindah ke admin sekolah (pemohon jadi tanpa password, `tenant_id` terisi), tanpa email atur kata sandi. Login di `/pemohon/masuk` untuk pemohon yang disetujui membuka sesi sekolah lewat `SchoolSessionOpener` (kontrak Platform, implementasi Identity). 439 tes lulus, Deptrac 0 pelanggaran, tipe TS bersih. Belum dicoba di browser. |
| 4 | Provider mengundang pemohon; lupa kata sandi pemohon | ⬜ | |

## Tasks

### Tahap 1 — Akun pemohon
- [x] Migrasi `applicants` — `modules/Platform/database/migrations/0005_01_01_000000_create_applicants_table.php`
- [x] Model + factory `Applicant` — `modules/Platform/app/Domain/Models/Applicant.php`, `modules/Platform/database/factories/ApplicantFactory.php`
- [x] Guard `applicant` dan provider `applicants` — `config/auth.php`
- [x] Controller daftar, login/keluar, verifikasi, onboarding — `modules/Platform/app/Http/Controllers/Applicant/`
- [x] Middleware pemohon (tamu → login pemohon, belum terverifikasi → pemberitahuan) — `modules/Platform/app/Http/Middleware/AuthenticateApplicant.php`
- [x] `/daftar-sekolah` menjadi form daftar akun; `SchoolApplyController` dan `SchoolApply.tsx` dihapus
- [x] Route pemohon (`applicant.*`) — `modules/Platform/routes/web.php`
- [x] Mail verifikasi + view + pengirim signed URL — `modules/Platform/app/Infrastructure/Mail/ApplicantVerifyMail.php`, `modules/Platform/mail/applicant-verify-text.blade.php`, `modules/Platform/app/Infrastructure/Onboarding/ApplicantVerification.php`
- [x] Halaman `Register`, `Login`, `VerifyNotice`, `Onboarding` + `ApplicantShell` — `modules/Platform/resources/js/Pages/Platform/Applicant/`, `modules/Platform/resources/js/Components/ApplicantShell.tsx`
- [x] Pemohon login tanpa sesi sekolah diarahkan ke `/pemohon`; tautan "Daftarkan sekolah" di login sekolah — `app/Http/Controllers/EntryController.php`, `modules/Identity/resources/js/Pages/Identity/Auth/Login.tsx`
- [x] Tes — `modules/Platform/tests/Feature/ApplicantAccountTest.php` (baru, 16 tes), `modules/Platform/tests/Feature/SchoolApplyTest.php` (disesuaikan ke `/pemohon/pengajuan`, 10 tes)

**Selesai bila:** pengunjung bisa mendaftar, memverifikasi email, login, dan keluar sebagai pemohon; tanpa verifikasi ia tertahan di halaman pemberitahuan. Bukti: `php artisan test --compact modules/Platform` → lulus.

### Tahap 2 — Onboarding, paket, keputusan
- [x] Migrasi `applicant_id` (unik), `plan_key`, `submitted_at` pada `tenant_applications` — `modules/Platform/database/migrations/0005_01_01_000001_add_applicant_and_plan_to_tenant_applications_table.php`
- [x] Kontrak `submit` / `resubmit` / `forApplicant`; `applicantId`, `planKey`, `submittedAt` di DTO — `modules/Platform/app/Contracts/TenantApplications.php`, `modules/Platform/app/Contracts/DTOs/ApplicationData.php`
- [x] Implementasi + validasi paket + trial pada paket terpilih — `modules/Platform/app/Infrastructure/Onboarding/DefaultTenantApplications.php`, `ApplicationDataMapper.php`
- [x] Controller onboarding (tampil, ajukan, ajukan ulang) + route `PUT /pemohon/pengajuan` — `modules/Platform/app/Http/Controllers/Applicant/OnboardingController.php`, `modules/Platform/routes/web.php`
- [x] Halaman `Onboarding` (data sekolah, pilih paket, status, catatan penolakan) + `PlanPicker` — `modules/Platform/resources/js/Pages/Platform/Applicant/Onboarding.tsx`, `modules/Platform/resources/js/Components/PlanPicker.tsx`
- [x] Review provider menampilkan pemohon dan paket, paket bisa dikoreksi — `modules/Platform/app/Http/Controllers/ApplicationReviewController.php`, `modules/Platform/resources/js/Pages/Platform/Applications/Show.tsx`
- [x] Mail disetujui (kode sekolah, paket, akhir trial) dan ditolak (catatan), dikirim setelah transaksi selesai — `modules/Platform/app/Infrastructure/Mail/`, `modules/Platform/app/Infrastructure/Onboarding/ApplicantDecisionNotifier.php`, `modules/Platform/mail/`
- [x] Seeder dev memakai akun pemohon (`kepsek@sekolah-c.test` / `password`) — `modules/Platform/database/seeders/PlatformDevSeeder.php`
- [x] Tes — `modules/Platform/tests/Feature/ApplicantOnboardingTest.php` (baru, 15 tes); disesuaikan: `SchoolApplyTest.php`, `TenantApplicationsTest.php`, `BillingSubscriptionTest.php`

**Selesai bila:** pemohon terverifikasi bisa mengajukan dengan paket, melihat status, dan mengajukan ulang setelah ditolak; ACC membuat tenant dengan trial pada paket itu. Bukti: `php artisan test --compact modules/Platform` → lulus.

### Tahap 3 — Jembatan ke sesi sekolah
- [x] Kontrak `SchoolSessionOpener`, `TenantSession` + default dan binding — `modules/Platform/app/Contracts/SchoolSessionOpener.php`, `modules/Platform/app/Contracts/TenantSession.php`, `modules/Platform/app/Infrastructure/Onboarding/NullSchoolSessionOpener.php`, `modules/Platform/app/Infrastructure/Tenancy/SessionTenantSession.php`, `modules/Platform/app/Infrastructure/Providers/PlatformServiceProvider.php`
- [x] `TenantApproved` membawa `passwordHash`; ACC mengisi `applicants.tenant_id` dan mengosongkan `applicants.password` di dalam transaksi — `modules/Platform/app/Contracts/Events/TenantApproved.php`, `modules/Platform/app/Infrastructure/Onboarding/DefaultTenantApplications.php`
- [x] `ProvisionFirstAdmin` memakai hash bila ada (tanpa email atur kata sandi) — `modules/Identity/app/Infrastructure/Onboarding/ProvisionFirstAdmin.php`
- [x] `DefaultSchoolSessionOpener` + binding — `modules/Identity/app/Infrastructure/Auth/DefaultSchoolSessionOpener.php`, `modules/Identity/app/Infrastructure/Providers/IdentityServiceProvider.php`
- [x] Login pemohon yang sudah disetujui memanggil opener lalu mengarah ke `/` (pintu depan → `/beranda`) — `modules/Platform/app/Http/Controllers/Applicant/SessionController.php`
- [x] Email "disetujui" dan halaman status menyebut cara masuk yang baru — `modules/Platform/mail/application-approved-text.blade.php`, `modules/Platform/resources/js/Pages/Platform/Applicant/Onboarding.tsx`
- [x] Tes — `modules/Identity/tests/Feature/ApplicantSchoolSessionTest.php` (baru, 11 tes)

**Selesai bila:** setelah ACC, login di `/pemohon/masuk` mendarat di `/beranda` sekolah baru. Bukti: `php artisan test --compact` dan `vendor/bin/deptrac analyse` → lulus, 0 pelanggaran.

### Tahap 4 — Undangan provider dan lupa kata sandi
- [ ] Console: daftar pemohon + undang — `modules/Platform/app/Http/Controllers/ApplicantConsoleController.php`, `modules/Platform/resources/js/Pages/Platform/Applicants/Index.tsx`
- [ ] Atur kata sandi dari undangan (sekaligus verifikasi email) — `modules/Platform/app/Http/Controllers/Applicant/`, halaman `SetPassword`
- [ ] Lupa kata sandi pemohon (respons generik) — controller + halaman + mail
- [ ] Tes: undangan, tautan kedaluwarsa/salah tanda tangan, reset tidak membocorkan keberadaan email — `modules/Platform/tests/Feature/ApplicantInviteTest.php`

**Selesai bila:** provider bisa mengundang pemohon yang lalu mengatur kata sandi dan masuk onboarding; pemohon bisa mereset kata sandinya. Bukti: `php artisan test --compact modules/Platform` → lulus.

## Batas tindakan
**Selalu**
- Jalankan `vendor/bin/pint --dirty --format agent` dan tes modul yang disentuh sebelum menandai tahap selesai.
- Perbarui `modules/Platform/CONTRACT.md` dan `modules/Identity/CONTRACT.md` saat permukaan publik berubah.
- Balas generik pada login, lupa sandi, dan verifikasi, supaya keberadaan akun tidak bisa ditebak.

**Tanya dulu**
- Mengubah aturan tenancy (cara tenant dikenali) atau keputusan terkunci lain di `docs/architecture/modular-monolith.md`.
- Menambah kontrak publik di luar yang tercantum di Desain.
- `migrate:fresh` pada database dev yang masih dipakai.

**Jangan**
- Menjadikan pemohon baris di `users`, atau membuat `users.tenant_id` nullable.
- Mengimpor Identity dari Platform, atau model `Tenant` dari modul lain.
- Menambah foreign key selain `tenant_id`.
- Menghapus tes tanpa persetujuan; push atau membuka PR tanpa diminta.

## Pengujian
- **Tahap 1:** validasi daftar, email unik, honeypot diam-diam sukses, login benar/salah, belum terverifikasi tertahan, tautan verifikasi kedaluwarsa atau diubah ditolak.
- **Tahap 2:** satu pengajuan per pemohon, aturan slug yang ada, paket tidak aktif/diarsipkan/tak dikenal ditolak, tolak → ajukan ulang, pemohon lain tidak bisa melihat atau mengubah pengajuan orang lain, trial pada paket terpilih, `plan_key` kosong memakai paket default, paket hilang tidak menggagalkan ACC.
- **Tahap 3:** rollback bila listener gagal, password dipindah (bukan disalin), sesi tenant yang benar, kasus penolakan generik, Deptrac bersih.
- **Tahap 4:** undangan sekali pakai, tanda tangan dan masa berlaku, reset generik.

## Verifikasi end-to-end
1. `php artisan migrate:fresh --seed` → skema dan data dev terbentuk.
2. Buka `/daftar-sekolah`, daftar, buka tautan verifikasi dari mail log → mendarat di `/pemohon`.
3. Isi data sekolah, pilih paket, ajukan → status *Menunggu*.
4. Login console (`admin@simas.com`), tolak dengan catatan → `/pemohon` menampilkan catatan; ajukan ulang.
5. ACC di console → tenant ada, trial pada paket terpilih.
6. Login di `/pemohon/masuk` → mendarat di `/beranda` sekolah baru.
7. `php artisan test --compact` → lulus; `vendor/bin/deptrac analyse` → 0 pelanggaran; cek tipe TS dengan tsconfig yang mencakup `modules/` (tsc bawaan hanya `resources/js`).

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-01 — Pemohon yang sudah disetujui tidak pernah mendapat sesi guard `applicant` saat login: form yang sama langsung membuka sesi sekolah. Sesi pemohon yang masih hidup dari sebelum ACC tetap bisa melihat halaman status, yang menyuruhnya keluar lalu masuk lagi.
- 2026-10-01 — Setelah login jembatan, pengalihan ke `/` (bukan langsung ke route `home`), supaya Platform tidak menyebut nama route milik Core; `EntryController` yang mengarahkan ke `/beranda`.
- 2026-10-01 — Sekolah yang ditangguhkan ditolak di opener dengan error generik, bukan dibukakan sesi yang lalu mentok di 403.
- 2026-10-01 — `resubmit` menerima `applicantId`, bukan id pengajuan: kepemilikan melekat pada pemohon yang login, jadi tidak ada id yang bisa ditukar untuk menyentuh pengajuan orang lain.
- 2026-10-01 — Paket yang dipilih pemohon tidak divalidasi ulang saat ACC; hanya paket hasil koreksi provider yang wajib bisa dipilih. Bila paket pemohon sudah diarsipkan, ACC tetap jalan tanpa langganan (perilaku lama: log warning).
- 2026-10-01 — Email keputusan hanya dikirim untuk pengajuan yang punya akun pemohon; baris lama tanpa akun tidak punya login pemohon untuk dituju.
- 2026-10-01 — Tahap 1 memindahkan form data sekolah ke `/pemohon` (di balik akun terverifikasi) memakai `TenantApplications::submit()` yang ada, dengan nama/email dari akun dan pengajuan terakhir dicari lewat email. Alasan: `/daftar-sekolah` lama dihapus di tahap ini, jadi tanpa itu tidak ada cara mengajukan sekolah sampai Tahap 2. Pengikatan `applicant_id`, pilih paket, dan `resubmit` tetap di Tahap 2; untuk sementara ajukan ulang setelah ditolak membuat baris pengajuan baru.
- 2026-10-01 — Halaman pemohon memakai primitif shadcn dari Shared (`Card`, `Field`, `Input`), bukan `AuthShell`/`AuthInput`, mengikuti `.ai/rules/js.md`.
- 2026-10-01 — Throttle IP hanya di POST `/daftar-sekolah` (dulu GET ikut terkena), supaya membuka form tidak memakan kuota.
- 2026-10-01 — Dokumen diselaraskan ke template skill `writing-plans` (menambah Goal, Batasan masalah, Tasks, Batas tindakan); keputusan dan desain tidak berubah. Lupa kata sandi pemohon ditegaskan masuk Tahap 4.

### Temuan
- `auth:applicant` tidak dipakai: `redirectGuestsTo` di `bootstrap/app.php` sadar-host dan mengirim tamu ke login **sekolah**. Pemohon memakai middleware sendiri (`AuthenticateApplicant`); `guest:` juga diganti pengecekan di controller.
- Di tes, `actingAs($x, 'applicant')` menjadikan guard itu guard default, sehingga route sekolah (`auth`) tampak terbuka bagi pemohon. Itu artefak tes: pemisahan guard diuji lewat login sungguhan (POST `/pemohon/masuk`).
- Model yang dipegang guard tidak ikut ter-refresh dalam satu proses tes; setelah verifikasi, tes memakai `actingAs($applicant->fresh(), ...)`.
- Sejak Tahap 3, pengajuan dari akun pemohon tidak lagi memicu email atur kata sandi; hanya pengajuan lama tanpa akun yang masih memakainya.
- `TenantData::status` adalah enum internal Platform di dalam DTO publik. Identity membandingkan `->value` supaya tidak mengimpor enum itu.
- `SessionTenantSession` mengambil session lewat `session()` saat dipanggil, bukan lewat constructor: binding-nya singleton, sedangkan session store milik request.
- Tautan di email keputusan dibuat dari `TenantUrl::root()`, bukan `route()`: keputusan diambil di host console, dan `route()` akan membawa host itu.
- Pesan aturan slug/paket dari `DefaultTenantApplications` masih berbahasa Inggris dan tampil apa adanya di form pemohon (perilaku lama form publik). Belum diterjemahkan.
- Tidak ada primitif radio group di Shared; `PlanPicker` memakai input radio native di dalam label seukuran kartu, tanpa menambah dependensi.

### Hasil akhir
Belum diisi.
