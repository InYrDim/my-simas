# Tes E2E (browser) untuk alur pendaftaran sekolah: Pest Browser + Playwright

> **Status dokumen:** Berjalan
> **Dibuat:** 2026-10-01 · **Diperbarui:** 2026-10-02 · **Branch:** `feat/tenant-onboarding`

## Context
Alur pendaftaran sekolah (`docs/ai/plan/fase-4/tenant-onboarding-plan.md`, keempat tahap ✅) hanya diuji di tingkat HTTP (`modules/Platform/tests/Feature/Applicant*Test.php`, `modules/Identity/tests/Feature/ApplicantSchoolSessionTest.php`). Tes itu memastikan aturan server, tetapi tidak pernah menjalankan halaman React-nya: form onboarding, `PlanPicker`, dialog review provider, dan perpindahan antar host belum pernah dibuka di browser. Bagian "Hasil akhir" rencana onboarding mencatat itu sebagai sisa pekerjaan.

Proyek belum punya alat E2E. Yang ada: Pest 5.2 (`composer.json`), SQLite in-memory + mail `array` + sesi `array` untuk tes (`phpunit.xml`), dan aset hasil build di `public/build`.

## Goal
Setelah ini, `composer test:e2e` membuka browser sungguhan dan membuktikan alur onboarding dari ujung ke ujung: daftar → verifikasi email → isi data sekolah dan pilih paket → provider menolak lalu menyetujui di host console → pemohon login dan mendarat di `/beranda` sekolahnya. Perintah itu terpisah, jadi `php artisan test` tetap cepat dan tidak butuh browser.

Selain itu, `composer test:playwright` menjalankan suite tipis terhadap **server dan host sungguhan** (`php artisan serve`, `localhost` dan `console.localhost`), membuktikan hal yang tidak bisa diuji suite Pest: perpindahan host, cookie, dan redirect yang sebenarnya.

## Batasan masalah

**Dalam cakupan**
- Memasang Pest Browser (Playwright) dan konfigurasi terpisah untuk suite E2E.
- Suite Playwright (TypeScript) tipis terhadap server sungguhan: uji asap + satu alur utama dari data seeder.
- Skenario E2E alur onboarding fase-4 (lihat Tahap 2).
- Dokumentasi singkat cara menjalankan.

**Di luar cakupan**
- E2E untuk Master Data, Akademik, billing console, dan halaman lain — suite ini hanya fondasi + onboarding; skenario lain menyusul per fitur.
- Job CI — repo belum punya remote; `.github/workflows/tests.yml` tidak diubah (keputusan user: perintah terpisah saja).
- Visual regression (`assertScreenshotMatches`) dan uji lintas browser/perangkat.

**Asumsi**
- `pestphp/pest-plugin-browser` punya rilis yang cocok dengan Pest 5.2. Bila composer tidak menemukan versi yang cocok, **berhenti dan laporkan**, jangan menurunkan versi Pest.
- Plugin berjalan di Windows (mesin dev). Bila tidak, laporkan sebelum mencari jalan lain.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Dependensi tidak diubah tanpa persetujuan (sumber: `AGENTS.md` › Application Structure). Persetujuan rencana ini mencakup **dua dependensi dev**: `pestphp/pest-plugin-browser` (composer) dan `playwright` (npm), plus unduhan browser Chromium.
- `tests/` di root adalah layer glue: boleh memakai modul mana pun, tidak dipakai siapa pun (sumber: `docs/architecture/modular-monolith.md`). E2E melintasi Platform, Identity, dan Core, jadi tempatnya di root, bukan di `modules/<Name>/tests`.
- Sekolah dikenali dari sesi/kode sekolah, console dari host `console.localhost` (sumber: `modules/Platform/CONTRACT.md` › Tenant resolution).
- Jangan menghapus atau melemahkan tes yang ada.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-01 | Alat: Pest Browser (bukan Playwright TypeScript) | user |
| 2026-10-01 | Dijalankan lewat perintah terpisah, tidak ikut suite utama, tanpa job CI | user |
| 2026-10-01 | Suite dipisah lewat file konfigurasi sendiri (`phpunit.e2e.xml`), bukan lewat group | hasil desain: suite utama tidak pernah memuat tes browser |
| 2026-10-02 | Tambah Playwright (TypeScript) sebagai suite tipis lingkungan nyata; skenario rinci tetap di Pest Browser | user |
| 2026-10-02 | Urutan: Playwright dulu, baru skenario Pest Browser | user |
| 2026-10-02 | Dependensi dev ketiga disetujui: `@playwright/test` | user |

## Desain

### Pemisahan suite
- Tes browser di **`tests/Browser/`**. `phpunit.xml` tidak mendaftarkan folder itu, sehingga `php artisan test` dan `composer test` tidak berubah.
- **`phpunit.e2e.xml`** (baru): hanya testsuite `Browser`, env sama dengan `phpunit.xml`.
- `tests/Pest.php`: tambah binding `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Browser')`.
- `composer.json` › scripts: `test:e2e` = `npm run build` lalu `pest -c phpunit.e2e.xml`. Aset harus hasil build karena browser memuat JS sungguhan.
- `.gitignore`: `tests/Browser/Screenshots`.

### Cara tes bekerja
- Plugin menjalankan aplikasi di dalam proses tes, jadi factory, `Mail::fake()`, dan database tes dipakai apa adanya. Yang dipakai ulang: `ApplicantFactory`, `ProviderUserFactory`, `PlanFactory`, `TenantApplicationFactory` (`modules/Platform/database/factories/`).
- Host pusat: default plugin (`127.0.0.1`, sudah ada di `config('tenancy.central_domains')`). Host console: `visit(...)->withHost('console.localhost')`.
- Tautan email diambil dari mailable yang ter-queue (`ApplicantVerifyMail::$verifyUrl`, `ApplicantInvitationMail::$invitationUrl`, `ApplicantPasswordResetMail::$resetUrl`), lalu **hanya path + query-nya** yang dikunjungi. Host di dalam tautan berasal dari `TenantUrl::root()` (`localhost`), bukan host server tes. Tautan undangan/reset ditandatangani relatif, jadi tetap sah.
- Elemen dicari lewat label/teks yang dilihat pengguna ("Nama sekolah", "Ajukan sekolah"), bukan selector CSS, supaya tes tidak rapuh.
- Helper bersama di `tests/Browser/Support/onboarding.php` (pola `modules/Core/tests/Feature/Support/helpers.php`): menyiapkan paket, mengambil path dari tautan email, login provider.

### Suite Playwright (lingkungan nyata)
- **Pembagian peran.** Pest Browser: skenario rinci yang butuh factory, `Mail::fake()`, dan pemeriksaan database. Playwright: sedikit skenario terhadap aplikasi yang berjalan seperti aslinya. Satu skenario tidak ditulis di dua tempat.
- **Lokasi.** Spec di `tests/E2E/` (di dalam `tests/`, tanpa folder dasar baru); konfigurasi di `playwright.config.ts`.
- **Server.** Playwright menyalakan server bawaan PHP dengan router Laravel (yang dijalankan `artisan serve` di baliknya) di port **8989** lewat opsi `webServer`, dengan OPcache dimatikan. Host pusat `http://localhost:8989`, host console `http://console.localhost:8989` (Chromium memetakan `*.localhost` ke loopback).
- **Database.** SQLite terpisah `database/e2e.sqlite` (sudah tercakup `database/.gitignore`), di-reset dengan `php artisan migrate:fresh --seed` di `globalSetup`. Database dev (`database/database.sqlite`) tidak tersentuh.
- **Konfigurasi.** Tanpa file `.env` baru: variabel proses (`DB_DATABASE`, `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, `APP_URL`, `TENANCY_URL_PORT`) menimpa `.env` untuk server dan untuk perintah migrasi. `APP_ENV` tetap `local` supaya `DatabaseSeeder` menjalankan `PlatformDevSeeder`.
- **Data.** Dari seeder yang ada: provider `admin@simas.com` / `admin123`, pemohon `kepsek@sekolah-c.test` / `password` dengan pengajuan `sekolah-c` berstatus menunggu, paket dari `BillingMasterDataSeeder`.
- **Skenario.** (1) Uji asap: login sekolah, daftar pemohon, login console tampil di host masing-masing tanpa error konsol. (2) Alur utama: provider login di host console → buka pengajuan `SMA Sekolah C` → setujui → pemohon login di host pusat → mendarat di `/beranda` sekolahnya. Alur ini tidak butuh membaca email.
- **Aset.** `composer test:playwright` menjalankan `npm run build` dulu. Bila `npm run dev` sedang jalan, halaman memuat aset dari dev server (`public/hot`); keduanya sah untuk suite ini.

### Hal yang harus dibuktikan dulu (Tahap 1)
Tiga hal belum pasti dan menentukan desain, jadi Tahap 1 berakhir dengan satu tes yang membuktikannya:
1. **Sesi bertahan antar request** dengan `SESSION_DRIVER=array`. Bila tidak, `phpunit.e2e.xml` memakai `file`.
2. **`withHost('console.localhost')`** benar-benar melayani route console.
3. **Tidak ada error JavaScript** di halaman pemohon (`assertNoJavaScriptErrors`).

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 1 | Fondasi: pasang Pest Browser + Playwright, konfigurasi suite terpisah, satu tes asap yang membuktikan sesi, host console, dan tanpa error JS | ✅ | Plugin + Playwright terpasang, `phpunit.e2e.xml` dan `composer test:e2e` terpisah dari suite utama, tes asap lulus di Chromium (2 tes, ±5 detik). Setelah `sockets` diaktifkan di php.ini, `php artisan test --compact` kembali 452 lulus tanpa memuat tes browser. Deptrac 0 pelanggaran. |
| 2 | Playwright: `@playwright/test`, konfigurasi + server + database terpisah, uji asap dan alur utama terhadap host sungguhan | ✅ | `composer test:playwright`: server PHP sendiri di port 8989, database `database/e2e.sqlite` di-reset tiap run, 4 spec lulus di Chromium (±45 detik) terhadap `localhost` dan `console.localhost`. Database dev tidak berubah. `php artisan test --compact` 452 lulus, `composer test:e2e` 2 lulus. `npm run check` sudah gagal format di ±199 file sebelum pekerjaan ini; tidak diperbaiki. |
| 3 | Skenario onboarding di Pest Browser: alur utama, tolak → ajukan ulang, undangan provider, lupa kata sandi | ⬜ | |

## Tasks

### Tahap 1 — Fondasi
- [x] Salin rencana ke docs — `docs/ai/plan/fase-4/onboarding-e2e-plan.md`
- [x] `pestphp/pest-plugin-browser` ^5.0 (dev) — `composer.json`, `composer.lock`
- [x] `playwright` ^1.63 (dev) dan `npx playwright install chromium` — `package.json`, `package-lock.json`
- [x] Konfigurasi suite E2E — `phpunit.e2e.xml`
- [x] Binding folder Browser + setup bersama (SSR Inertia dimatikan, aset hasil build, reset host) — `tests/Pest.php`
- [x] Script `test:e2e` — `composer.json`
- [x] Abaikan screenshot — `.gitignore`
- [x] Helper pindah host — `tests/Browser/Support/hosts.php`
- [x] Tes asap: halaman daftar dan masuk pemohon (dua muatan halaman penuh), login provider di host console lalu `/applicants`; tanpa error JS; sesi bertahan — `tests/Browser/SmokeTest.php`
- [x] Ekstensi `sockets` aktif di php.ini mesin dev (diaktifkan user 2026-10-02), sehingga `php artisan test --compact` lulus tanpa flag

**Selesai bila:** `composer test:e2e` menjalankan tes asap di Chromium dan lulus, sementara `php artisan test --compact` tetap 452 tes lulus tanpa memuat tes browser. Bukti: kedua perintah itu.

### Tahap 2 — Playwright (lingkungan nyata)
- [x] `@playwright/test` ^1.63 (dev) — `package.json`, `package-lock.json`
- [x] Konfigurasi: `testDir`, `webServer`, `globalSetup`, satu worker — `playwright.config.ts`
- [x] Lingkungan suite (port, host, env penimpa, akun seeder) — `tests/E2E/support/env.ts`
- [x] Reset + seed database E2E, dengan dua pengaman terhadap database dev — `tests/E2E/global-setup.ts`
- [x] Uji asap tiga pintu masuk di host masing-masing — `tests/E2E/smoke.spec.ts`
- [x] Alur utama: provider ACC di host console → pemohon login di host pusat → `/beranda`, `/master/sekolah` terbuka, console tertutup bagi sesi sekolah — `tests/E2E/onboarding.spec.ts`
- [x] Script `test:playwright` — `composer.json`, `package.json`
- [x] Abaikan artefak (`/test-results`, `/playwright-report`) — `.gitignore`
- [x] Cek tipe frontend tidak terganggu (`npm run types:check` lulus). `npm run check` gagal format sejak sebelum pekerjaan ini (lihat Temuan)

**Selesai bila:** `composer test:playwright` menyalakan server sendiri, me-reset database E2E, dan semua spec lulus di Chromium; database dev tidak berubah; `php artisan test --compact` dan `composer test:e2e` tetap lulus. Bukti: ketiga perintah itu.

### Tahap 3 — Skenario onboarding (Pest Browser)
- [ ] Helper bersama — `tests/Browser/Support/onboarding.php` (baru)
- [ ] **Alur utama:** daftar → pemberitahuan verifikasi → buka tautan verifikasi → isi data sekolah, pilih paket → status "Menunggu persetujuan" → provider ACC di console → keluar → login pemohon → `/beranda` menampilkan nama sekolah — `tests/Browser/OnboardingJourneyTest.php` (baru)
- [ ] **Tolak → ajukan ulang:** provider menolak dengan catatan → pemohon melihat catatan dengan form terisi → memperbaiki → "Ajukan ulang" → status menunggu lagi — file yang sama
- [ ] **Undangan provider:** undang dari console → buka tautan undangan → buat kata sandi → mendarat di onboarding — `tests/Browser/ApplicantInvitationTest.php` (baru)
- [ ] **Lupa kata sandi:** minta reset → buka tautan → kata sandi baru → login berhasil — file yang sama
- [ ] **Validasi di layar:** mengajukan tanpa memilih paket menampilkan "Pilih salah satu paket." — `OnboardingJourneyTest.php`
- [ ] Cara menjalankan dan men-debug (`--debug`, `--headed`) — bagian baru di `docs/architecture/modular-monolith.md` (atau README bila ada bagian testing)
- [ ] Catat hasil di "Hasil akhir" rencana onboarding — `docs/ai/plan/fase-4/tenant-onboarding-plan.md`

**Selesai bila:** semua skenario lulus di browser dan setiap halaman yang dilewati bebas error JS. Bukti: `composer test:e2e` → lulus; `php artisan test --compact` → tetap lulus.

## Batas tindakan
**Selalu**
- Jalankan `composer test:e2e` **dan** `php artisan test --compact` sebelum menandai tahap selesai.
- `vendor/bin/pint --dirty --format agent` setelah mengubah file PHP.
- Bila tes E2E menemukan bug di aplikasi: laporkan dan perbaiki sebagai perubahan terpisah yang disebut jelas, jangan melonggarkan tes.

**Tanya dulu**
- Dependensi selain tiga yang disetujui (`pest-plugin-browser`, `playwright`, `@playwright/test`), atau menaik/menurunkan versi Pest.
- Mengubah `phpunit.xml` selain yang tertulis, atau mengubah perilaku aplikasi demi tes.
- Menambah `data-test`/atribut khusus tes ke halaman bila pencarian lewat label tidak cukup.

**Jangan**
- Mendaftarkan `tests/Browser` di `phpunit.xml`.
- Meng-commit screenshot atau folder unduhan browser.
- Push atau membuka PR tanpa diminta.

## Pengujian
- **Tahap 1:** tes asap adalah ujinya; ditambah pemeriksaan bahwa suite utama tidak ikut menjalankan tes browser (jumlah tes tetap 452).
- **Tahap 2:** spec Playwright menegaskan yang terlihat di layar dan URL/host tempat pengguna mendarat; keadaan database tidak diperiksa langsung (itu tugas suite Pest).
- **Tahap 3:** setiap skenario menegaskan yang terlihat di layar **dan** keadaan di database (pengajuan `pending`/`approved`, `applicants.tenant_id` terisi, trial pada paket terpilih), supaya tes tidak lulus hanya karena teksnya cocok.

## Verifikasi end-to-end
1. `composer test:e2e` → build aset, lalu semua tes browser lulus.
2. `composer test:playwright` → server menyala sendiri, database E2E di-reset, semua spec lulus; `npx playwright test --ui` untuk menonton.
3. `vendor/bin/pest -c phpunit.e2e.xml --debug` → browser terlihat, bisa dipakai menonton alur utama sekali.
4. `php artisan test --compact` → 452 tes lulus, tidak ada tes dari `tests/Browser`.
5. `vendor/bin/deptrac analyse --no-progress` → 0 pelanggaran (tes root tidak mengubah batas modul).

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-02 — Port suite Playwright 8989, bukan 8123: 8123 dipakai server dev yang sedang berjalan di mesin ini.
- 2026-10-02 — Server dinyalakan langsung (`php -d opcache.enable=0 -S ...` dari `public/`), bukan lewat `php artisan serve`: variabel proses sampai ke aplikasi apa adanya, dan OPcache tidak dipakai bersama server lain (lihat Temuan).
- 2026-10-02 — Satu worker dan spec berurutan: database E2E dan server PHP (satu thread di Windows) dipakai bersama, dan pengajuan contoh hanya bisa disetujui sekali per seed.
- 2026-10-02 — Cakupan direvisi: Playwright (TypeScript) ditambahkan sebagai Tahap 2, skenario Pest Browser bergeser menjadi Tahap 3. Alasan: suite Pest berjalan dalam satu proses dan mensimulasikan host, jadi host/cookie/redirect sungguhan tidak teruji.
- 2026-10-02 — Ekstensi `sockets` diaktifkan user di php.ini mesin dev; flag sementara `-d extension=sockets` dicabut dari script `test:e2e`. **Setiap mesin dan CI yang menjalankan tes (utama maupun E2E) kini butuh `ext-sockets`** (lihat Temuan).
- 2026-10-02 — Host console dipilih lewat helper `onConsoleHost()` / `onCentralHost()` (konfigurasi host plugin, berlaku untuk semua request berikutnya), bukan `visit()->withHost()` yang hanya mencakup muatan halaman pertama.
- 2026-10-02 — SSR Inertia dimatikan khusus di tes browser (`config(['inertia.ssr.enabled' => false])`). Alur SSR tidak tercakup E2E.
- 2026-10-02 — Sesi `array` ternyata cukup; `phpunit.e2e.xml` tetap memakai env yang sama dengan `phpunit.xml`.
- 2026-10-02 — `composer require` memicu hook `boost:update` yang menulis ulang file pedoman Boost (`AGENTS.md`, `boost.json`, skill). Perubahan itu dikembalikan karena tidak termasuk pekerjaan ini.

### Temuan
- **Server PHP baru gagal booting dengan OPcache menyala** di mesin ini: `Cannot instantiate interface RecursiveIterator` di `symfony/finder`, yang muncul ke browser sebagai `A facade root has not been set`. Kode yang sama jalan di CLI dan di server dengan `opcache.enable=0`. Dugaan (belum dibuktikan): cache OPcache envkit (`C:/ProgramData/envkit/opcache/file-cache/8.4.25`) dibuat sebelum `sockets` diaktifkan dan tidak cocok lagi. Server dev yang sudah berjalan sejak sebelum perubahan php.ini tidak terpengaruh, **tetapi mungkin terkena saat di-restart**; bila itu terjadi, kosongkan cache OPcache envkit.
- **Alur onboarding terbukti di browser dan host sungguhan** untuk pertama kali: provider menyetujui di `console.localhost`, pemohon login di `localhost` dengan kata sandi lamanya dan mendarat di `/beranda` sekolahnya; sesi sekolah tidak membuka console.
- `npm run check` (`vp check`) melaporkan masalah format di ±199 file sebelum pekerjaan ini (termasuk komponen Shared). File baru di `tests/E2E` ikut terdaftar. Tidak diperbaiki di sini karena menyentuh hampir semua file frontend.
- `migrate:fresh` di `globalSetup` hanya aman karena variabel proses menimpa `.env`. Config yang di-cache akan mengabaikannya, jadi `globalSetup` menolak jalan bila `bootstrap/cache/config.php` ada.
- **Plugin butuh `ext-sockets` untuk semua tes di `tests/`, bukan hanya tes browser.** Dengan plugin terpasang dan ekstensi mati, `php artisan test` gagal di 12 tes root (`tests/Unit`, `tests/Feature`, `tests/Architecture`) dengan `Call to undefined function socket_create_listen()`; 440 tes modul tetap lulus. Dengan ekstensi dimuat (`php -d extension=sockets vendor/bin/pest --compact`) ke-452 tes lulus dan tes browser tidak ikut. Ekstensinya ada di mesin ini (`php_sockets.dll`), hanya dinonaktifkan di `C:\ProgramData\envkit\services\php\8.4.25\php.ini` baris 943.
- **Server tes berjalan dalam satu proses PHP.** Inertia menyimpan hasil render SSR per "request scope" (`Inertia\Ssr\SsrState`, binding `scoped`) yang di sini tidak pernah berakhir, jadi muatan halaman penuh kedua dijawab dengan HTML halaman pertama. Bukan bug aplikasi (di produksi tiap request proses baru).
- **`public/hot` mengalihkan aset ke Vite dev server** bila `npm run dev` sedang jalan (dan itu pula yang menyalakan SSR). Tes browser memakai `Vite::useHotFile()` ke file yang tidak ada supaya selalu memuat hasil build.
- **Route console terikat domain**, jadi redirect-nya menunjuk origin `console.localhost` sementara browser tetap di `127.0.0.1`. Inertia membaca header lewat XHR lintas-origin, sehingga `onConsoleHost()` juga membuka `cors.exposed_headers`.
- `assertSee` yang gagal menyebut URL **awal** halaman ("initially with the url ..."), bukan URL saat itu; menyesatkan saat men-debug.
- Dua `visit()` dalam satu tes membuka dua halaman terpisah; untuk berpindah halaman pakai `navigate()` atau klik tautan.

### Hasil akhir
Belum diisi.
