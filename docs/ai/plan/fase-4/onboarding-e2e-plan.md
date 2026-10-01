# Tes E2E (browser) untuk alur pendaftaran sekolah

> **Status dokumen:** Berjalan
> **Dibuat:** 2026-10-01 · **Diperbarui:** 2026-10-02 · **Branch:** `feat/tenant-onboarding`

## Context
Alur pendaftaran sekolah (`docs/ai/plan/fase-4/tenant-onboarding-plan.md`, keempat tahap ✅) hanya diuji di tingkat HTTP (`modules/Platform/tests/Feature/Applicant*Test.php`, `modules/Identity/tests/Feature/ApplicantSchoolSessionTest.php`). Tes itu memastikan aturan server, tetapi tidak pernah menjalankan halaman React-nya: form onboarding, `PlanPicker`, dialog review provider, dan perpindahan antar host belum pernah dibuka di browser. Bagian "Hasil akhir" rencana onboarding mencatat itu sebagai sisa pekerjaan.

Proyek belum punya alat E2E. Yang ada: Pest 5.2 (`composer.json`), SQLite in-memory + mail `array` + sesi `array` untuk tes (`phpunit.xml`), dan aset hasil build di `public/build`.

## Goal
Setelah ini, `composer test:e2e` membuka browser sungguhan dan membuktikan alur onboarding dari ujung ke ujung: daftar → verifikasi email → isi data sekolah dan pilih paket → provider menolak lalu menyetujui di host console → pemohon login dan mendarat di `/beranda` sekolahnya. Perintah itu terpisah, jadi `php artisan test` tetap cepat dan tidak butuh browser.

## Batasan masalah

**Dalam cakupan**
- Memasang Pest Browser (Playwright) dan konfigurasi terpisah untuk suite E2E.
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
| 2 | Skenario onboarding: alur utama, tolak → ajukan ulang, undangan provider, lupa kata sandi | ⬜ | |

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

### Tahap 2 — Skenario onboarding
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
- Dependensi selain dua yang disetujui di atas, atau menaik/menurunkan versi Pest.
- Mengubah `phpunit.xml` selain yang tertulis, atau mengubah perilaku aplikasi demi tes.
- Menambah `data-test`/atribut khusus tes ke halaman bila pencarian lewat label tidak cukup.

**Jangan**
- Mendaftarkan `tests/Browser` di `phpunit.xml`.
- Meng-commit screenshot atau folder unduhan browser.
- Push atau membuka PR tanpa diminta.

## Pengujian
- **Tahap 1:** tes asap adalah ujinya; ditambah pemeriksaan bahwa suite utama tidak ikut menjalankan tes browser (jumlah tes tetap 452).
- **Tahap 2:** setiap skenario menegaskan yang terlihat di layar **dan** keadaan di database (pengajuan `pending`/`approved`, `applicants.tenant_id` terisi, trial pada paket terpilih), supaya tes tidak lulus hanya karena teksnya cocok.

## Verifikasi end-to-end
1. `composer test:e2e` → build aset, lalu semua tes browser lulus.
2. `vendor/bin/pest -c phpunit.e2e.xml --debug` → browser terlihat, bisa dipakai menonton alur utama sekali.
3. `php artisan test --compact` → 452 tes lulus, tidak ada tes dari `tests/Browser`.
4. `vendor/bin/deptrac analyse --no-progress` → 0 pelanggaran (tes root tidak mengubah batas modul).

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-02 — Ekstensi `sockets` diaktifkan user di php.ini mesin dev; flag sementara `-d extension=sockets` dicabut dari script `test:e2e`. **Setiap mesin dan CI yang menjalankan tes (utama maupun E2E) kini butuh `ext-sockets`** (lihat Temuan).
- 2026-10-02 — Host console dipilih lewat helper `onConsoleHost()` / `onCentralHost()` (konfigurasi host plugin, berlaku untuk semua request berikutnya), bukan `visit()->withHost()` yang hanya mencakup muatan halaman pertama.
- 2026-10-02 — SSR Inertia dimatikan khusus di tes browser (`config(['inertia.ssr.enabled' => false])`). Alur SSR tidak tercakup E2E.
- 2026-10-02 — Sesi `array` ternyata cukup; `phpunit.e2e.xml` tetap memakai env yang sama dengan `phpunit.xml`.
- 2026-10-02 — `composer require` memicu hook `boost:update` yang menulis ulang file pedoman Boost (`AGENTS.md`, `boost.json`, skill). Perubahan itu dikembalikan karena tidak termasuk pekerjaan ini.

### Temuan
- **Plugin butuh `ext-sockets` untuk semua tes di `tests/`, bukan hanya tes browser.** Dengan plugin terpasang dan ekstensi mati, `php artisan test` gagal di 12 tes root (`tests/Unit`, `tests/Feature`, `tests/Architecture`) dengan `Call to undefined function socket_create_listen()`; 440 tes modul tetap lulus. Dengan ekstensi dimuat (`php -d extension=sockets vendor/bin/pest --compact`) ke-452 tes lulus dan tes browser tidak ikut. Ekstensinya ada di mesin ini (`php_sockets.dll`), hanya dinonaktifkan di `C:\ProgramData\envkit\services\php\8.4.25\php.ini` baris 943.
- **Server tes berjalan dalam satu proses PHP.** Inertia menyimpan hasil render SSR per "request scope" (`Inertia\Ssr\SsrState`, binding `scoped`) yang di sini tidak pernah berakhir, jadi muatan halaman penuh kedua dijawab dengan HTML halaman pertama. Bukan bug aplikasi (di produksi tiap request proses baru).
- **`public/hot` mengalihkan aset ke Vite dev server** bila `npm run dev` sedang jalan (dan itu pula yang menyalakan SSR). Tes browser memakai `Vite::useHotFile()` ke file yang tidak ada supaya selalu memuat hasil build.
- **Route console terikat domain**, jadi redirect-nya menunjuk origin `console.localhost` sementara browser tetap di `127.0.0.1`. Inertia membaca header lewat XHR lintas-origin, sehingga `onConsoleHost()` juga membuka `cors.exposed_headers`.
- `assertSee` yang gagal menyebut URL **awal** halaman ("initially with the url ..."), bukan URL saat itu; menyesatkan saat men-debug.
- Dua `visit()` dalam satu tes membuka dua halaman terpisah; untuk berpindah halaman pakai `navigate()` atau klik tautan.

### Hasil akhir
Belum diisi.
