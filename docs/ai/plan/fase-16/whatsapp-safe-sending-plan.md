# Pengiriman WhatsApp yang aman untuk nomor sekolah: variasi pesan, pacing, pengaman

> **Status dokumen:** Selesai
> **Dibuat:** 2026-10-07 · **Diperbarui:** 2026-10-07 · **Branch:** `feat/whatsapp-safe-sending`

## Context
SIMAS mengirim WhatsApp lewat gateway **OpenWA**, klien tidak resmi (whatsapp-web.js/Baileys), memakai **nomor WhatsApp milik sekolah** yang ditautkan lewat QR (fase 9, `docs/ai/plan/fase-9/whatsapp-integration-plan.md`). Lisensi OpenWA (MIT) tidak bermasalah, tetapi ketentuan WhatsApp melarang pesan otomatis/massal lewat klien tidak resmi; nomor bisa dibatasi atau diblokir permanen, dan dokumentasi OpenWA menyatakan risiko itu tidak pernah nol dan tanggung jawab kepatuhan ada pada pemakai.

Pola yang paling mudah dideteksi WhatsApp adalah yang sekarang dihasilkan SIMAS saat jam masuk/pulang: ratusan pesan berisi sama dari satu nomor dalam hitungan menit. Kode sekarang: `modules/Core/app/Infrastructure/Whatsapp/QueueWhatsappMessage.php` menulis log lalu mengantrekan `DeliverWhatsappMessage` (satu job per pesan, `$tries = 3`, backoff 30/120 detik); job memanggil `WhatsappChannel::sendText()` (`modules/Platform/app/Infrastructure/Whatsapp/DefaultWhatsappChannel.php`) tanpa jeda, tanpa batas, tanpa pemutus saat gateway bermasalah. Teks pesan satu templat tetap per jenis (`NoticeTemplate::fill`, `modules/Core/app/Infrastructure/Whatsapp/NoticeTemplate.php`). Semua jenis pemberitahuan sudah mati secara default (tanpa baris `whatsapp_notice_settings` = mati), tetapi tidak ada peringatan saat sekolah menyalakan jenis berpesan banyak seperti `attendance.gate-in`.

Antrean berjalan dari cron per menit tanpa daemon (`docs/config/cron.md`, target Hostinger shared hosting), jadi pesan memang tiba beberapa menit kemudian; pacing yang menunda pesan puluhan menit masih dapat diterima untuk notifikasi absensi.

## Goal
Setelah ini, pesan WhatsApp sekolah keluar dengan jeda acak per nomor dan batas harian, redaksinya bervariasi tetapi nama dan status tetap sama, dan pengiriman berhenti sendiri bila gateway terus gagal. Admin sekolah harus menyetujui peringatan risiko sebelum QR muncul, diperingatkan saat menyalakan notifikasi berpesan banyak, dan melihat bila pengiriman sedang dijeda.

## Batasan masalah

**Dalam cakupan**
- Pacing per sekolah dengan jitter (jeda acak antar pesan) dan batas harian, di Platform.
- Pemutus sirkuit (circuit breaker) per sekolah saat gateway gagal berturut-turut, di Platform.
- Variasi redaksi lewat sintaks `{a|b|c}` di templat, di Core; templat bawaan Absensi diberi variasi.
- Penanda `highVolume` pada jenis pemberitahuan, peringatan + konfirmasi saat sekolah menyalakannya.
- Persetujuan risiko oleh admin sekolah sebelum QR (dicatat siapa dan kapan).
- Banner "pengiriman dijeda" di halaman Integrasi › WhatsApp.
- Dokumentasi: `docs/config/cron.md`, `CONTRACT.md` Platform/Core/Attendance, `docs/architecture/modular-monolith.md`.

**Di luar cakupan**
- Dedupe pesan (kunci unik `siswa + jenis + tanggal`) dan klaim atomik `pending → sending` — dikerjakan di fase lain bila pesan ganda terbukti terjadi.
- Warm-up nomor baru (batas harian naik bertahap) dan ringkasan harian satu pesan per wali.
- Opt-in/opt-out wali (kata penutup "balas BERHENTI") — itu panduan operasional sekolah, bukan kode.
- Adapter WhatsApp Cloud API/BSP resmi di `WhatsappChannel` — jangka panjang, butuh keputusan biaya.
- Antrean khusus `whatsapp` dan pekerja tambahan di cron — dibahas setelah angka nyata ada.
- Pengaturan pacing di konsol provider — nilai awal dari `.env`/config.
- Memanggil OpenWA sungguhan dari tes atau agen (aturan fase 9: tes memakai `Http::fake` + `Http::preventStrayRequests`).

**Asumsi**
- Nilai awal pacing: jeda 3–10 detik antar pesan per sekolah, batas 500 pesan per hari per sekolah, pemutus terbuka setelah 3 kegagalan berturut-turut selama 5 menit. Semuanya dari config/`.env`; angka final dicek ke halaman "Ban risk & safe sending" di `https://docs.open-wa.org` pada Tahap 0.
- `CACHE_STORE=database` mendukung `Cache::lock` dan `Cache::add` atomik (dipakai untuk slot pacing).
- Satu worker cron per menit tetap satu-satunya pekerja; lock per sekolah tetap dipasang agar aman bila pekerja bertambah.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Urutan lapisan Shared → Platform → Identity → Core → modul fitur; Platform tidak mengimpor Core; Attendance hanya memakai kontrak Core; lintas modul hanya lewat `app/Contracts/**` dengan DTO/skalar (sumber: `AGENTS.md`, `docs/architecture/modular-monolith.md`).
- Tanpa FK selain `tenant_id`; kolom `risk_acknowledged_by` adalah kolom biasa, bukan FK (sumber: `tests/Architecture/ModularMonolithTest.php`).
- Kunci cache tenant lewat `TenantCache` (`modules/Platform/app/Contracts/TenantCache.php`); jangan cache model Eloquent atau hasil `null` (sumber: `AGENTS.md` § Tenancy traps).
- Kunci sesi OpenWA tidak boleh sampai DTO, halaman, log, atau pesan galat (sumber: `AGENTS.md` § WhatsApp).
- Tes WhatsApp: `Http::fake` + `Http::preventStrayRequests`; tes antrean mendaftarkan ulang `app(TenantQueueContext::class)->register()` (sumber: `AGENTS.md`).
- Modul tidak boleh mengecek sakelar WhatsApp sendiri; Absensi hanya memanggil `GuardianNotifier` (sumber: `AGENTS.md` § Attendance).
- Aktifkan skill `modular-monolith`, `laravel-best-practices`, `writing-tests`, `testing-best-practices`; untuk UI juga `inertia-react-development`, `wayfinder-development`, dan baca `.ai/rules/js.md`.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-07 | Variasi pesan acak dengan nama dan status tetap, ditambah pacing per sesi dengan jitter | user |
| 2026-10-07 | Fase ini mengerjakan paket: pacing+jitter, variasi templat, penanda `highVolume`, pemutus sirkuit, persetujuan risiko (usulan, menunggu persetujuan user) | diusulkan |
| 2026-10-07 | Variasi memakai sintaks `{a|b|c}` di dalam satu templat; tidak butuh kolom baru, sekolah menyunting seperti biasa. `\{(\w+)\}` di `NoticeTemplate::fill` tidak cocok dengan `|`, jadi tidak bentrok dengan variabel | hasil baca kode |
| 2026-10-07 | Grup variasi dipilih dulu, baru variabel diisi, agar nilai variabel yang berisi `\|` atau `{` tidak ikut diurai | hasil desain |
| 2026-10-07 | Pacing dan pemutus tinggal di Platform (pemilik gateway); `sendText()` mengembalikan hasil baru `throttled` berisi detik tunggu, Core menjadwal ulang tanpa menghitungnya sebagai kegagalan | hasil desain |
| 2026-10-07 | Penjadwalan ulang karena `throttled` tidak boleh menghabiskan `$tries`; percobaan karena galat gateway dihitung terpisah | hasil desain |

## Desain

### Kontrak antar-aliran (dibekukan di Tahap 0)
Semua aliran paralel bergantung pada bentuk berikut; Tahap 0 menambahkannya tanpa mengubah perilaku, sebelum aliran lain mulai.

- `WhatsappSendResult::throttled(int $retryAfterSeconds, string $reason)` (Platform, `Contracts/DTOs`): `sent=false`, `retryable=true`, properti baru `retryAfter` (int|null) dan `throttled` (bool). Dipakai Core untuk `release()`.
- `WhatsappState` (Platform, `Contracts/DTOs`): tambah `?string $pausedUntil = null` (ISO 8601, pengiriman dijeda sampai kapan), `?string $pauseReason = null`, `bool $riskAcknowledged = false`. Semua parameter baru ber-default sehingga pemanggil lama tidak rusak.
- `WhatsappChannel::acknowledgeRisk(int $userId): WhatsappState` (Platform, `Contracts/WhatsappChannel.php`). `connect()` melempar `WhatsappRiskNotAcknowledgedException` (baru, `Contracts/Exceptions`) bila belum disetujui.
- `NoticeKind` (Core, `Contracts/DTOs`): tambah `bool $highVolume = false` sebagai parameter terakhir.
- Route Core baru `POST integrasi/whatsapp/setujui-risiko` bernama `core.integration.whatsapp.acknowledge` → `WhatsappController::acknowledge`; `updateNotice` menerima `confirm_volume` (boolean).
- Props halaman Integrasi › WhatsApp: `whatsapp.pausedUntil`, `whatsapp.pauseReason`, `whatsapp.riskAcknowledged`, dan `highVolume` pada tiap item `notices`.

### Platform
- Kelas baru `Infrastructure/Whatsapp/SendGuard` yang dipanggil `DefaultWhatsappChannel::sendText()` sebelum `OpenWaClient::sendText`:
  1. cek pemutus (kunci `TenantCache`); terbuka → `throttled` sampai waktu buka kembali;
  2. cek batas harian; terlampaui → `throttled` sampai tengah malam waktu sekolah;
  3. pesan slot berikutnya di bawah `Cache::lock` per sekolah: `next_at = max(sekarang, next_at) + acak(min, max)`; slot masih di masa depan → `throttled(next_at - sekarang)`.
- Setelah panggilan gateway: sukses → reset hitungan gagal; galat `retryable()` (koneksi/5xx) → tambah hitungan, buka pemutus bila mencapai ambang. Galat 4xx tidak menghitung.
- `describe()` mengisi `pausedUntil`/`pauseReason` dari pemutus dan `riskAcknowledged` dari kolom baru.
- Migrasi baru: `whatsapp_instances.risk_acknowledged_by` (unsignedBigInteger nullable, tanpa FK) dan `risk_acknowledged_at` (timestamp nullable).
- Config di `config/services.php` blok `openwa`: `pace_min`, `pace_max`, `daily_limit`, `breaker_threshold`, `breaker_pause` (env `OPENWA_PACE_MIN` dst; tambahkan nama kosong/bawaan ke `.env.example`).

### Core
- `DeliverWhatsappMessage`: bila hasil `throttled` → `release($retryAfter)` tanpa mengubah status dan tanpa menambah hitungan galat; jangan habiskan `$tries` (gunakan `$tries = 0` + `retryUntil()` 12 jam, dan hitungan galat sendiri di kolom `whatsapp_messages.error_attempts` (baru)). Lewat `retryUntil` → status `Unsent` dengan pesan "Pengiriman tertunda terlalu lama".
- `NoticeTemplate::fill`: selesaikan grup `{a|b|c}` secara acak (satu pilihan per grup; bukan bersarang) lalu isi variabel. Tambah `NoticeTemplate::preview()` yang dipakai halaman untuk contoh (deterministik, pilihan pertama) dan validator di `SaveNoticeSetting` (grup harus berisi minimal dua pilihan tidak kosong; `{nama_siswa}` tetap wajib sebagaimana aturan sekarang).
- `WhatsappController::acknowledge` memanggil `WhatsappChannel::acknowledgeRisk(auth id)`; `connect` menampilkan galat bila belum setuju; `updateNotice` menolak penyalaan jenis `highVolume` tanpa `confirm_volume`.
- Halaman `Index.tsx`: dialog persetujuan risiko sebelum tombol Hubungkan aktif, banner dijeda, peringatan + konfirmasi saat menyalakan jenis `highVolume`, petunjuk sintaks `{a|b|c}` pada editor templat.

### Attendance
- `AttendanceNotices::kinds()` (`modules/Attendance/app/Domain/Notifications/AttendanceNotices.php`): templat bawaan diberi variasi pembuka/penutup; `GATE_IN`, `GATE_OUT`, dan `LESSON_ABSENT` memakai `highVolume: true`.

### Alur
1. Admin sekolah membuka Integrasi › WhatsApp, membaca dan menyetujui peringatan risiko, lalu menekan Hubungkan dan memindai QR.
2. Sekolah menyalakan "Siswa masuk sekolah": halaman memperingatkan volume dan risiko, admin mengonfirmasi.
3. Saat jam masuk, tiap scan membuat satu pesan `pending`; worker mengirim satu pesan, lalu pesan berikutnya dijadwalkan ulang oleh `throttled` sesuai jeda acak; redaksi tiap pesan berbeda.
4. Bila OpenWA gagal 3 kali berturut-turut, pengiriman sekolah itu dijeda 5 menit, banner tampil, pesan menunggu di antrean lalu jalan lagi.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Kontrak, migrasi, route, config (kerangka tanpa perilaku baru) — satu agen | ✅ | 114 tes WhatsApp hijau; migrasi dijalankan; Wayfinder digenerate |
| 1A | Platform: `SendGuard`, pacing, batas harian, pemutus, persetujuan risiko | ✅ | 325 tes Platform hijau; hari batas harian = zona waktu sekolah (TenantContext::timezone, fallback config lalu UTC) |
| 1B | Core backend: job `throttled`, `NoticeTemplate` variasi, controller | ✅ | 539 tes `modules/Core` hijau |
| 1C | Attendance: templat bawaan + `highVolume` | ✅ | paralel |
| 1D | Core UI: dialog risiko, banner, peringatan, editor templat | ✅ | 3 tes browser hijau; tsc bersih untuk berkas ini; banner dijeda belum dicakup tes browser |
| 2 | Integrasi, dokumentasi, tes browser, Pint — satu agen | ✅ | 1.405 tes hijau (Platform, Core, Attendance, Ppdb, Architecture); Deptrac: 1 pelanggaran lama di `app/Console/Commands/DemoSeedCommand.php`, bukan dari fase ini; suite penuh belum dijalankan user |

## Pembagian kerja multi-agen

Tahap 0 dikerjakan **satu agen**, lalu dikomit. Tahap 1A–1D berjalan **bersamaan**, tiap agen di worktree sendiri (skill `worktree-feature`), cabang `feat/whatsapp-safe-sending/<aliran>`, dan hanya menyentuh berkas miliknya:

| Aliran | Berkas milik (boleh diedit) | Jangan sentuh |
| --- | --- | --- |
| 1A Platform | `modules/Platform/**`, `config/services.php`, `.env.example` | `modules/Core/**`, `modules/Attendance/**` |
| 1B Core backend | `modules/Core/app/**`, `modules/Core/database/**`, `modules/Core/tests/**`, `modules/Core/routes/web.php` | `modules/Core/resources/**`, `modules/Platform/**` |
| 1C Attendance | `modules/Attendance/**` | modul lain |
| 1D Core UI | `modules/Core/resources/js/Pages/Core/Integration/Whatsapp/**` (dan komponen baru di `modules/Core/resources/js/Components/**`), `tests/Browser/WhatsappIntegrationTest.php` | PHP apa pun |

Aturan koordinasi: kontrak di "Kontrak antar-aliran" tidak boleh diubah oleh aliran; perlu perubahan → berhenti dan lapor. 1B dan 1D yang butuh perilaku Platform memakai `WhatsappChannel` palsu di tes (bind `fake` di container), bukan menunggu 1A. Penggabungan: 1A → 1B → 1C → 1D ke `feat/whatsapp-safe-sending`; tidak ada berkas bersama sehingga tidak ada konflik yang diharapkan.

## Tasks

### Tahap 0 — Kerangka kontrak (satu agen)
- [x] Buka `https://docs.open-wa.org` halaman "Ban risk & safe sending"; catat angka aman yang mereka sarankan di Temuan dan setel ulang asumsi pacing bila berbeda.
- [x] `WhatsappSendResult::throttled()` + properti `retryAfter`, `throttled` — `modules/Platform/app/Contracts/DTOs/WhatsappSendResult.php`
- [x] `WhatsappState`: tambah `pausedUntil`, `pauseReason`, `riskAcknowledged` — `modules/Platform/app/Contracts/DTOs/WhatsappState.php`
- [x] `WhatsappChannel::acknowledgeRisk()` + `WhatsappRiskNotAcknowledgedException` — `modules/Platform/app/Contracts/WhatsappChannel.php`, `modules/Platform/app/Contracts/Exceptions/WhatsappRiskNotAcknowledgedException.php` (baru); implementasi sementara di `DefaultWhatsappChannel` melempar `LogicException` bukan dipakai
- [x] Migrasi `risk_acknowledged_by/at` — `modules/Platform/database/migrations/0009_01_01_000000_add_risk_acknowledgement_to_whatsapp_instances_table.php` (baru; cek penamaan berkas sekitarnya dan modul `0008_…`)
- [x] Migrasi `whatsapp_messages.error_attempts` — `modules/Core/database/migrations/` (baru, ikuti penamaan di sana)
- [x] `NoticeKind::$highVolume` — `modules/Core/app/Contracts/DTOs/NoticeKind.php`
- [x] Route + method `acknowledge` (kosong) di `WhatsappController`; nama `core.integration.whatsapp.acknowledge` — `modules/Core/routes/web.php`, `modules/Core/app/Http/Controllers/WhatsappController.php`
- [x] Config `services.openwa` kunci pacing + `.env.example` — `config/services.php`, `.env.example`
- [x] `php artisan wayfinder:generate` agar Tahap 1D punya fungsi route.

**Selesai bila:** seluruh tes WhatsApp yang ada masih lulus dan tidak ada perilaku baru. Bukti: `php artisan test --compact modules/Platform modules/Core --filter=Whatsapp` → hijau; `php artisan migrate` bersih; `vendor/bin/pint --dirty --format agent` bersih.

### Tahap 1A — Platform (paralel)
- [x] `SendGuard`: pemutus, batas harian, slot pacing dengan lock per sekolah — `modules/Platform/app/Infrastructure/Whatsapp/SendGuard.php` (baru)
- [x] Pasang di `DefaultWhatsappChannel::sendText()`; catat sukses/gagal ke pemutus — `modules/Platform/app/Infrastructure/Whatsapp/DefaultWhatsappChannel.php`
- [x] `describe()` mengisi `pausedUntil`, `pauseReason`, `riskAcknowledged` — file yang sama
- [x] `acknowledgeRisk()` menyimpan `risk_acknowledged_by/at`; `connect()` menolak sebelum disetujui — file yang sama; kolom di `modules/Platform/app/Domain/Models/WhatsappInstance.php`
- [x] Reset persetujuan bila instance dinonaktifkan/ditolak? Tidak — persetujuan tetap (catat di Log keputusan bila diubah).
- [x] Tes: slot pacing menolak panggilan kedua sebelum jeda lewat; batas harian; pemutus terbuka setelah 3 galat retryable dan menutup setelah jeda; 4xx tidak menghitung; `connect()` ditolak tanpa persetujuan; isolasi antar tenant (sekolah A dijeda tidak memengaruhi B); kunci API tidak muncul di state — `modules/Platform/tests/Feature/WhatsappSendGuardTest.php` (baru), `modules/Platform/tests/Feature/WhatsappConnectionTest.php`

**Selesai bila:** sekolah yang melebihi jeda/batas/ambang gagal mendapat `throttled` dengan detik tunggu yang benar. Bukti: `php artisan test --compact modules/Platform` → hijau.

### Tahap 1B — Core backend (paralel)
- [x] `NoticeTemplate::fill` menyelesaikan `{a|b|c}` lalu variabel; `preview()` deterministik — `modules/Core/app/Infrastructure/Whatsapp/NoticeTemplate.php`
- [x] Validasi grup variasi di `SaveNoticeSetting` — cari kelasnya di `modules/Core/app/` (`grep -rn "class SaveNoticeSetting"`)
- [x] `DeliverWhatsappMessage`: tangani `throttled` (release tanpa hitung), `$tries = 0` + `retryUntil()`, hitungan galat sendiri di `error_attempts` — `modules/Core/app/Infrastructure/Whatsapp/DeliverWhatsappMessage.php`
- [x] `WhatsappController`: `acknowledge`, `connect` menampilkan galat, `updateNotice` menolak `highVolume` tanpa `confirm_volume`, props baru di `index()`/`stateProps()`/`kinds()` — `modules/Core/app/Http/Controllers/WhatsappController.php`
- [x] Tes: variasi memilih hanya dari pilihan, variabel tetap terisi, nilai variabel berisi `|` tidak terurai, grup satu pilihan ditolak — `modules/Core/tests/Unit/NoticeTemplateTest.php` (baru)
- [x] Tes: `throttled` → job dijadwalkan ulang, status tetap `pending`, `$tries` tidak habis; galat retryable tetap berhenti setelah 3; melewati `retryUntil` → `unsent` — `modules/Core/tests/Feature/WhatsappSendTest.php` (pakai `WhatsappChannel` palsu)
- [x] Tes: `highVolume` perlu `confirm_volume`; `acknowledge` memanggil kontrak; izin `core.integration.manage` — `modules/Core/tests/Feature/WhatsappPageTest.php`

**Selesai bila:** pesan yang di-`throttled` menunggu tanpa gagal dan variasi teks bekerja. Bukti: `php artisan test --compact modules/Core --filter=Whatsapp` → hijau.

### Tahap 1C — Attendance (paralel)
- [x] Beri variasi pada templat bawaan keempat jenis; `highVolume: true` untuk `GATE_IN`, `GATE_OUT`, `LESSON_ABSENT` — `modules/Attendance/app/Domain/Notifications/AttendanceNotices.php`
- [x] Tes: tiap templat bawaan diselesaikan menjadi kalimat yang selalu memuat nama siswa dan status (jalankan beberapa kali), jenis `highVolume` terdaftar benar — `modules/Attendance/tests/Feature/AttendanceNotificationTest.php`

**Selesai bila:** `php artisan test --compact modules/Attendance --filter=Notification` → hijau.

### Tahap 1D — Core UI (paralel)
- [x] Dialog persetujuan risiko (peringatan ban, saran nomor khusus sekolah, tanggung jawab sekolah); tombol Hubungkan nonaktif sampai `riskAcknowledged` — `modules/Core/resources/js/Pages/Core/Integration/Whatsapp/Index.tsx`
- [x] Banner "Pengiriman dijeda sampai {jam}" dari `pausedUntil`/`pauseReason`
- [x] Konfirmasi saat menyalakan jenis `highVolume` (kirim `confirm_volume`)
- [x] Petunjuk sintaks `{a|b|c}` di editor templat dan pratinjau
- [x] URL dari Wayfinder (`@/actions`/`@/routes`), komponen dari `modules/Shared/resources/js/components/ui` — baca `.ai/rules/js.md` dulu
- [x] Tes browser: dialog muncul sebelum QR, konfirmasi `highVolume` — `tests/Browser/WhatsappIntegrationTest.php` (baca skill `writing-tests`, `.claude/skills/writing-tests/browser-tests.md`)

**Selesai bila:** halaman memuat tanpa galat tipe dan alur persetujuan terlihat. Bukti: `npx tsc --noEmit -p <konfigurasi yang memuat modules/**/resources/js/**/*>` bersih (lihat `.ai/rules/js.md`); `php artisan test --compact tests/Browser/WhatsappIntegrationTest.php` → hijau.

### Tahap 2 — Integrasi & dokumentasi (satu agen)
- [x] Gabungkan 1A→1D; jalankan tes gabungan
- [x] Perbarui `docs/config/cron.md` (pesan bisa tertunda puluhan menit karena pacing; `retryUntil` 12 jam), `modules/Platform/CONTRACT.md`, `modules/Core/CONTRACT.md`, `modules/Attendance/CONTRACT.md`, `docs/architecture/modular-monolith.md`, bagian WhatsApp di `AGENTS.md` (salinan lokal) bila kontrak berubah
- [x] Tambah baris fase 16 di `docs/ai/plan/README.md`
- [x] Tes arsitektur dan Deptrac: `php artisan test --compact tests/Architecture`; `vendor/bin/deptrac`

**Selesai bila:** semua tes terkait hijau dan dokumentasi mutakhir. Bukti: `php artisan test --compact modules/Platform modules/Core modules/Attendance tests/Architecture` → hijau; `vendor/bin/pint --dirty --format agent` bersih. Minta user menjalankan `php artisan test --compact` (seluruh suite).

## Batas tindakan
**Selalu**
- Aktifkan skill yang disebut di Batasan teknis sebelum menulis kode; jalankan Pint dan tes modul sebelum menandai tahap selesai.
- Pakai `Http::fake` + `Http::preventStrayRequests` di setiap tes yang menyentuh OpenWA.
- Perbarui tabel status dan Log keputusan segera setelah tahap/deviasi terjadi.

**Tanya dulu**
- Mengubah "Kontrak antar-aliran" setelah Tahap 0.
- Mengubah nilai awal pacing di luar rentang yang disarankan OpenWA.
- Menambah antrean/pekerja cron baru atau dependensi Composer/npm.
- Menjalankan migrasi di data bersama atau di server nyata.

**Jangan**
- Memanggil gateway OpenWA sungguhan dari tes atau agen.
- Menaruh kunci API sesi di DTO, props, log, atau pesan galat.
- Menghapus tes, menambah FK selain `tenant_id`, memakai `DB::table()` untuk data tenant.
- Commit atau push tanpa diminta user.

## Pengujian
- **Tahap 1A:** jalur utama pacing/batas/pemutus, 4xx tidak menghitung, isolasi antar tenant, persetujuan risiko, kunci API tidak bocor.
- **Tahap 1B:** variasi templat, `throttled` tidak gagal, batas percobaan galat, `retryUntil`, `highVolume` perlu konfirmasi, izin halaman.
- **Tahap 1C:** templat bawaan selalu memuat nama dan status, penanda `highVolume`.
- **Tahap 1D:** dialog persetujuan, konfirmasi `highVolume`, banner dijeda.
- **Tahap 2:** arsitektur dan Deptrac.

## Verifikasi end-to-end
1. `php artisan test --compact modules/Platform modules/Core modules/Attendance tests/Architecture` → hijau.
2. Di aplikasi (user): sekolah uji belum menyetujui risiko → tombol Hubungkan nonaktif; setelah menyetujui, QR muncul.
3. Nyalakan "Siswa masuk sekolah" → muncul peringatan volume; setelah konfirmasi tersimpan.
4. Pindai belasan siswa berturut-turut dengan worker menyala → pesan keluar satu per satu dengan jeda acak dan redaksi berbeda, riwayat menunjukkan waktu kirim yang tersebar.
5. Matikan gateway (alamat salah di `.env`) → setelah 3 kegagalan banner "dijeda" tampil dan pesan tetap `pending`.

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-07 — Plan dibuat; user menyetujui eksekusi semua tahap ("oke process semua").
- 2026-10-07 — Props halaman ternyata `state` dan `kinds` (bukan `whatsapp`/`notices`): field baru ada di `state.pausedUntil`, `state.pauseReason`, `state.riskAcknowledged`, dan `kinds[].highVolume`.
- 2026-10-07 — Aliran 1A–1D berjalan di satu working tree tanpa worktree/commit (berkas milik tidak tumpang tindih; tes memakai sqlite `:memory:` sehingga aman paralel). Plan melarang commit tanpa diminta.
- 2026-10-07 — Tahap 0: `DefaultWhatsappChannel::acknowledgeRisk()` dan `WhatsappController::acknowledge()` hanya stub sampai 1A/1B; migrasi dijalankan di DB dev.

- 2026-10-07 — Perintah worker cron berubah dari `--stop-when-empty --max-time=50` menjadi `--sleep=2 --max-time=50` (di `docs/config/cron.md`, `docs/architecture/modular-monolith.md`) — job `throttled` adalah job tertunda; `--stop-when-empty` membuat worker keluar seketika sehingga hanya ~1 pesan per sekolah per menit yang keluar. Masih tanpa daemon dan tanpa tumpang tindih (maks 50 detik). Disetujui user 2026-10-08. Hostinger tidak mendokumentasikan batas waktu cron (PHP `max_execution_time` 360 dtk), jadi perlu diuji di paket nyata (±20 pesan uji ke satu sekolah dalam 2–3 menit); bila worker terpotong, turunkan `--max-time`.

- 2026-10-07 — Tambahan atas permintaan user setelah tahap 2: templat bawaan Absensi diperluas (4 grup × 3–4 pilihan), `VariationPicker` mencegah pilihan grup yang sama dengan pesan sekolah sebelumnya, dan opsi "ajakan membalas" per jenis pemberitahuan (`reply_footer`, `reply_footer_text`; bawaan atau teks sendiri). Per jenis, bukan satu pengaturan sekolah, agar tanpa tabel baru; bisa dipindah ke tingkat sekolah bila diminta.

### Temuan
- Semua jenis pemberitahuan sudah mati secara default (tanpa baris setelan), jadi "hanya kirim yang penting" tidak butuh perubahan default, hanya peringatan saat menyalakan.
- Notice Absensi dipanggil di luar transaksi (`SaveDailyAttendance`), jadi job tidak berpacu dengan commit.

### Hasil akhir
Pacing, variasi redaksi, penanda `highVolume`, pemutus sirkuit, dan persetujuan risiko selesai di Platform, Core, Attendance, dan UI Integrasi › WhatsApp. Tersisa: (1) user memutuskan perintah cron baru (`--sleep=2 --max-time=50`, lihat Log keputusan); (2) banner "dijeda" belum punya tes browser; (3) user menjalankan `php artisan test --compact` penuh; (4) hal di luar cakupan (dedupe, warm-up, ringkasan harian, opt-in wali, Cloud API resmi). Dua error `tsc` lama ada di `Academic/Placement/Index.tsx` dan `Ppdb/FormBuilder.tsx`.
