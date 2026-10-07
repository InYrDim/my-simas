# WhatsApp sekolah lewat OpenWA: ajukan, setujui, hubungkan, kirim

> **Status dokumen:** Selesai
> **Dibuat:** 2026-10-02 · **Diperbarui:** 2026-10-02 · **Branch:** `feat/whatsapp-integration`

## Context
Halaman Integrasi › WhatsApp (`/integrasi/whatsapp`) masih mockup: `modules/Core/app/Http/Controllers/MasterDataController.php::whatsapp()` mengirim data tetap dari `modules/Core/app/Infrastructure/Mock/IntegrationMockData.php` (sambungan, sakelar, templat, riwayat), dan `modules/Core/resources/js/Pages/Core/Integration/Whatsapp/Index.tsx` menampilkan QR tiruan. Route-nya hanya di balik `auth`, tanpa izin, sehingga menunya ikut tampil untuk siswa.

User sudah menjalankan gateway WhatsApp tidak resmi **OpenWA** (`https://docs.open-wa.org`) di `https://bot-wa.lab-jtik.id` dan memegang admin token-nya. Alur yang diminta: (1) admin sekolah mengajukan instance WA, (2) admin provider menyetujui — atau otomatis, bisa dinyalakan/dimatikan di pengaturan provider, (3) admin sekolah menghubungkan nomor WA ke sesinya. Batasan user: **API key sesi tiap sekolah tidak boleh terlihat oleh admin sekolah.** Cakupan yang dipilih user: instance **dan** pengiriman pesan sungguhan.

Fase ini juga menyiapkan kontrak pengirim yang dipakai Absensi (fase 10, draf di `docs/ai/plan/fase-10/attendance-plan.md`); bagian log WA di draf itu pindah ke sini.

Fakta OpenWA yang dipakai (dibaca dari dokumentasi 2026-10-02; `GET /api/health` di bot user menjawab `ok`):
- Semua route di bawah `/api`, autentikasi header `X-API-Key`. Key berperan `viewer` < `operator` < `admin` dan bisa dibatasi ke sesi tertentu (`allowedSessions`).
- `POST /api/auth/api-keys` (hanya key admin tanpa batasan sesi) mengembalikan isi key **sekali** di `apiKey`.
- Sesi: `POST /api/sessions` `{name}` (3–50 karakter: huruf, angka, tanda hubung; nama ganda → 409) → `POST /api/sessions/{id}/start` → `GET /api/sessions/{id}` (status `created`, `initializing`, `qr_ready`, `authenticating`, `ready`, `action_required`, `disconnected`, `failed`; `phone`, `pushName`, `lastError`) → `GET /api/sessions/{id}/qr` → `{qrCode: "data:image/png;base64,…", status}` (400 bila bukan `qr_ready`). Mulai sesi, QR, dan kirim butuh peran `operator`.
- Kirim teks: `POST /api/sessions/{id}/messages/send-text` `{chatId: "628…@c.us", text}` → 201 `{messageId, timestamp}`. 201 berarti diserahkan ke WhatsApp, bukan sampai.

## Goal
Setelah ini, admin sekolah bisa mengajukan WhatsApp dari halaman Integrasi, admin provider menyetujuinya di konsol (atau sistem menyetujui sendiri bila pengaturannya menyala), lalu admin sekolah memindai QR di SIMAS dan melihat nomornya terhubung; ia bisa mengirim pesan uji, mengatur jenis pemberitahuan dan isi pesannya, dan melihat riwayat pesan yang benar-benar keluar. Modul lain mengirim pesan ke wali murid lewat satu kontrak Core. API key sesi tidak pernah sampai ke browser admin sekolah.

## Batasan masalah

**Dalam cakupan**
- Klien OpenWA di server SIMAS; admin token di `.env`.
- Instance per sekolah: ajukan, setujui/tolak, setujui otomatis (pengaturan provider), nonaktifkan/aktifkan oleh provider.
- Hubungkan lewat QR dengan polling status; putuskan sambungan.
- Kirim teks sungguhan lewat antrean; log pesan; pesan uji.
- Jenis pemberitahuan yang didaftarkan modul (registry), sakelar dan templat per sekolah, kontrak `GuardianNotifier`.
- Izin baru `core.integration.manage`; gate route dan menu Integrasi.
- Tes Pest (feature, browser) dengan HTTP tiruan; pembersihan mock; dokumentasi.

**Di luar cakupan**
- Status terkirim/dibaca — butuh webhook OpenWA ke URL publik; riwayat berhenti di "Terkirim ke WhatsApp" atau "Gagal".
- Pairing code (alternatif QR), pesan media, grup, pesan masuk.
- Kuota pesan per sekolah dan pembatasan laju — batang kuota di mockup dihapus.
- Lebih dari satu nomor per sekolah.
- Pemicu pemberitahuan nyata (tidak hadir, PPDB) — didaftarkan modul pemiliknya di fasenya; di sini hanya ditampilkan "Segera hadir".
- Memanggil bot sungguhan dari tes atau dari agen — user yang mencoba alur nyata (keputusan user).
- Spec Playwright — server sungguhan tidak bisa memalsukan OpenWA.

**Asumsi**
- Versi OpenWA di bot user menyediakan endpoint di atas apa adanya. Endpoint yang **belum** saya lihat bentuk pastinya — menghentikan/logout sesi, menghapus sesi, mencabut key (`/api/auth/api-keys/{id}`), daftar sesi (`GET /api/sessions`) — diperiksa di `https://docs.open-wa.org/api-reference` pada Tahap 0 sebelum dipakai; bila berbeda, catat di Temuan.
- Admin token user adalah key `admin` tanpa batasan sesi (syarat membuat key).
- Worker antrean berjalan (`QUEUE_CONNECTION=database` di `.env.example`); tanpa worker pesan tetap berstatus menunggu.
- Nomor wali disimpan bebas (`0812-…`, `+62…`); dinormalkan ke `62…` saat mengirim.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Urutan lapisan Shared → Platform → Identity → Core; Platform tidak mengimpor Core; lintas modul hanya lewat `app/Contracts/**` dengan DTO/skalar (sumber: `AGENTS.md`, `docs/architecture/modular-monolith.md`).
- Tanpa FK selain `tenant_id`; data tenant lewat Eloquent, bukan `DB::table()` (sumber: `tests/Architecture/ModularMonolithTest.php`, `AGENTS.md` § Tenancy traps).
- Kunci `TenantCache` mengikuti tenant ambient — jangan menyimpan status instance di cache dari konsol provider (sumber: `docs/architecture/modular-monolith.md` § Fase 8).
- Job antrean membawa tenant otomatis; tes antrean mendaftarkan ulang `TenantQueueContext` (sumber: `AGENTS.md` § Tenancy traps).
- Konsol provider: route `Route::domain(config('tenancy.console_domain'))`, guard `provider` (sumber: `modules/Platform/routes/web.php`).
- UI dasar dari `modules/Shared/resources/js/components/ui`; URL dari Wayfinder (sumber: `.ai/rules/js.md`). Halaman modul tidak diperiksa `npx tsc --noEmit` biasa — periksa dengan konfigurasi yang memuat `modules/**/resources/js/**/*`.
- Aktifkan skill `modular-monolith`, `laravel-best-practices`, `inertia-react-development`, `wayfinder-development`, `writing-tests`, `testing-best-practices`.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-02 | Gateway = OpenWA milik user di `https://bot-wa.lab-jtik.id` | user |
| 2026-10-02 | Alur: sekolah mengajukan → provider menyetujui (atau otomatis lewat pengaturan provider) → sekolah menghubungkan | user |
| 2026-10-02 | API key sesi sekolah tidak boleh terlihat admin sekolah; tidak ada batasan lain | user |
| 2026-10-02 | Cakupan: instance + pengiriman pesan (log, sakelar, templat, pesan uji, kontrak pengirim) | user |
| 2026-10-02 | Pesan dikirim sungguhan; menggantikan keputusan lama "pengirim stub" | user |
| 2026-10-02 | Tes memakai HTTP tiruan; bot sungguhan tidak dipanggil | user |
| 2026-10-02 | Instance, klien OpenWA, persetujuan, dan pengaturan provider milik **Platform** (konsol dan menu provider ada di Platform; pola billing). Log, jenis pemberitahuan, templat, dan halaman sekolah tetap milik **Core**, memakai kontrak Platform | hasil baca kode |
| 2026-10-02 | Saat disetujui, SIMAS membuat sesi OpenWA dan satu key `operator` berbatas sesi itu; key disimpan terenkripsi, tidak pernah masuk DTO, props, atau log | hasil desain |
| 2026-10-02 | Variabel `.env`: `OPENWA_API_BASE_URL`, `OPENWA_ADMIN_API_KEY`, `OPENWA_CREDENTIALS_KEY` (nama dari user). Nilainya hanya di `.env`; `.env.example` berisi nama kosong | user |
| 2026-10-02 | `OPENWA_CREDENTIALS_KEY` tidak ada di dokumentasi OpenWA; bentuknya (64 hex) kunci AES-256, jadi dipakai SIMAS untuk mengenkripsi API key sesi yang disimpan (bukan `APP_KEY`). Tanpa kunci yang sah, tidak ada key yang disimpan | hasil cek dokumentasi, disetujui user |
| 2026-10-02 | "Putuskan" oleh sekolah = `logout` (tautan perangkat dilepas, berikutnya perlu QR baru); "Nonaktifkan" oleh provider = `stop` (tautan disimpan) | hasil cek dokumentasi |
| 2026-10-02 | Semua panggilan ke OpenWA dari server; halaman sekolah hanya menerima status dan gambar QR | hasil desain |
| 2026-10-02 | Nama sesi `simas-{tenant id huruf kecil}` (32 karakter, selalu sah dan unik) | hasil desain |
| 2026-10-02 | Status sambungan dibaca dengan polling dari halaman (3 detik selama menunggu QR), bukan webhook | hasil desain |
| 2026-10-02 | Satu izin baru `core.integration.manage` (admin sekolah) untuk halaman dan menu Integrasi | hasil desain |
| 2026-10-02 | Jenis pemberitahuan memakai pola registry Statistik & Laporan: modul mendaftar dari providernya; yang belum ada tampil "Segera hadir" dari config | hasil desain |

## Desain

### Platform — permukaan publik baru (`modules/Platform/app/Contracts`)
- `WhatsappChannel` — untuk sekolah yang sedang aktif (tenant context); gagal tertutup tanpa konteks:
  - `state(bool $refresh = false): WhatsappState` — `refresh` menanyakan OpenWA dan menyimpan hasilnya.
  - `request(int $requestedBy): WhatsappState` — membuat pengajuan; disetujui langsung bila pengaturan otomatis menyala.
  - `connect(): WhatsappState` — memulai sesi bila belum berjalan.
  - `disconnect(): WhatsappState`.
  - `sendText(string $phone, string $text): WhatsappSendResult` — `phone` berupa digit `62…`; tidak melempar untuk kegagalan gateway.
- `DTOs/WhatsappState` — `stage` (`none`, `pending`, `rejected`, `disabled`, `active`), `?connection` (status OpenWA), `?phone`, `?pushName`, `?note` (alasan tolak/nonaktif), `?qrCode` (data URL, hanya saat `qr_ready`), `?lastError`, `?requestedAt`. **Tanpa** key, id key, atau id sesi.
- `DTOs/WhatsappSendResult` — `sent`, `?messageId`, `?error`, `retryable` (dibuat di Tahap 3 bersama `sendText`).

### Platform — internal
- Config: `config/services.php` → `openwa` (`base_url` ← `OPENWA_API_BASE_URL`, `admin_api_key` ← `OPENWA_ADMIN_API_KEY`, `credentials_key` ← `OPENWA_CREDENTIALS_KEY`, `timeout`); `.env.example` memuat ketiga nama dengan nilai kosong.
- Tabel (migrasi baru di `modules/Platform/database/migrations/`):
  - `whatsapp_instances` (`0006_01_01_000000_…`): `tenant_id` (unik), `status`, `requested_by`, `requested_at`, `decided_by`, `decided_at`, `note`, `session_id`, `session_name`, `api_key` (teks terenkripsi), `api_key_id`, `connection_status`, `phone`, `push_name`, `connected_at`, `last_checked_at`, `last_error`, timestamps.
  - `provider_settings` (`0006_01_01_000001_…`): `key` (primary), `value` (json) — tabel pusat; kunci pertama `whatsapp.auto_approve`.
- `Domain/Models/WhatsappInstance.php` (+ enum status, factory; `api_key` memakai `Infrastructure/Whatsapp/CredentialCast` (di atas `CredentialVault`: AES-256-GCM dengan `OPENWA_CREDENTIALS_KEY`) + `$hidden`; pola skop tenant mengikuti `modules/Platform/app/Domain/Models/Subscription.php`), `Domain/Models/ProviderSetting.php`.
- `Infrastructure/Whatsapp/OpenWaClient.php` — pembungkus `Http::` : `createSession`, `findSessionByName`, `deleteSession`, `createSessionKey`, `revokeKey`, `startSession`, `stopSession`, `logoutSession`, `session`, `qr`, `sendText`. Admin key hanya untuk membuat/menghapus sesi dan key; sisanya memakai key sesi. Kegagalan → `OpenWaException` tanpa header atau key di pesannya.
- `Infrastructure/Whatsapp/` aksi: `RequestWhatsappInstance`, `ApproveWhatsappInstance` (buat sesi; 409 → pakai sesi bernama sama; buat key berbatas sesi; simpan; `active`. OpenWA gagal → tetap `pending`, galat ditampilkan ke provider), `RejectWhatsappInstance`, `DisableWhatsappInstance` (hentikan sesi), `EnableWhatsappInstance`, `DefaultWhatsappChannel` (implementasi kontrak; bind di `PlatformServiceProvider`).
- Konsol: `Http/Controllers/WhatsappInstanceController.php` — `index` (daftar per status + pengaturan), `approve`, `reject`, `disable`, `enable`, `updateSettings`; halaman `resources/js/Pages/Platform/Whatsapp/Index.tsx`; entri "WhatsApp" di `resources/js/Components/ProviderLayout.tsx`. Key tidak ditampilkan di konsol juga.

**Route konsol** (`modules/Platform/routes/web.php`, grup `auth:provider`, nama `platform.whatsapp.`)
| Method & URL | Nama |
| --- | --- |
| `GET whatsapp` | `index` |
| `POST whatsapp/{instance}/approve` | `approve` |
| `POST whatsapp/{instance}/reject` | `reject` |
| `POST whatsapp/{instance}/disable` | `disable` |
| `POST whatsapp/{instance}/enable` | `enable` |
| `PUT whatsapp/settings` | `settings` |

### Core — permukaan publik baru (`modules/Core/app/Contracts`)
- `GuardianNotifier` — `notify(GuardianNotice $notice): void`.
- `NoticeRegistry` — `register(string $module, NoticeKind $kind): void`.
- `DTOs/GuardianNotice` (`studentId`, `kind`, `variables: array<string, string>`), `DTOs/NoticeKind` (`key`, `title`, `description`, `recipient`, `template`, `variables: array<string, string>` = nama variabel → contoh nilai untuk pratinjau).
- `Exceptions/UnknownNoticeKindException`.

### Core — internal
- Izin `core.integration.manage` (+ label; nama ditambahkan ke `admin-sekolah` di `modules/Identity/config/roles.php`).
- Tabel: `whatsapp_messages` di `modules/Core/database/migrations/0004_01_01_000006_create_whatsapp_messages_table.php`; `whatsapp_notice_settings` di migrasi tersendiri pada Tahap 4. `whatsapp_messages` (`tenant_id`, `student_id` nullable, `recipient_name`, `phone` nullable, `kind`, `body`, `status`, `gateway_message_id`, `error`, `sent_at`, timestamps) dan `whatsapp_notice_settings` (`tenant_id`, `kind`, `enabled`, `template` nullable; unik `(tenant_id, kind)`).
- Status pesan: `pending` → `sent` | `failed` | `unsent` (WhatsApp sekolah belum terhubung) | `no_recipient` (nomor kosong/tidak sah).
- `Infrastructure/Whatsapp/`: `DefaultNoticeRegistry` (pola `Infrastructure/Insight/DefaultReportRegistry.php`; placeholder dari `config/notices.php`), `DefaultGuardianNotifier` (jenis terdaftar + menyala → isi templat → nama dan nomor wali dari `Student` → baris log → job), `DeliverWhatsappMessage` (job; `WhatsappChannel::sendText`; gagal `retryable` dicoba lagi 3 kali), `PhoneNumber` (normalisasi ke `62…`).
- `Domain/Actions/SendTestMessage`, `SaveNoticeSetting`.
- `Http/Controllers/WhatsappController.php` menggantikan `MasterDataController::whatsapp()`; `MasterDataController.php` dan `IntegrationMockData.php` dihapus di Tahap 5.

**Route sekolah** (`modules/Core/routes/web.php`, `auth` + `can:core.integration.manage`, prefix `integrasi/whatsapp`, nama `core.integration.whatsapp`)
| Method & URL | Nama | Aksi |
| --- | --- | --- |
| `GET /` | `core.integration.whatsapp` (tetap) | halaman; props `state`, `kinds`, `history`, `pagination` |
| `POST ajukan` | `.request` | mengajukan |
| `POST hubungkan` | `.connect` | memulai sesi |
| `POST putuskan` | `.disconnect` | memutus |
| `PUT pemberitahuan/{kind}` | `.notices.update` | sakelar + templat |
| `POST uji` | `.test` | pesan uji ke satu nomor |

Halaman memakai `usePoll` (hanya prop `state`) selama `connection` bernilai `initializing`, `qr_ready`, atau `authenticating`; controller memanggil `state(refresh: true)` untuk prop itu.

**Dipakai ulang**
- Platform: pola `ApplicationReviewController` + `TenantApplications` (antrean tunggu, setujui/tolak), `ProviderLayout.tsx`, `ConsoleParts.tsx`, `TenantDirectory` (nama sekolah di daftar), `TenantContext`.
- Core: `RendersMasterPage`, `MasterPage`, `FormDialog`, `ConfirmAction`, `@shared/components/ListPager`, `@shared/components/page-parts`; pola registry Insight; `roles:sync` untuk izin baru.
- Tes: `schoolAs()`, `inSchool()` (`modules/Core/tests/Feature/Support/helpers.php`); `providerSignsIn()` (`tests/Browser/Support/onboarding.php`), `schoolMemberSignsIn()` (`tests/Browser/Support/school.php`); `Http::fake()` + `Http::preventStrayRequests()`.

### Identity
- Hanya `modules/Identity/config/roles.php` (nama izin baru).

### Alur
1. Admin sekolah membuka Integrasi › WhatsApp → "Ajukan WhatsApp" → status "Menunggu persetujuan".
2. Admin provider membuka konsol › WhatsApp → "Setujui" (atau menolak dengan catatan). Dengan "Setujui otomatis" menyala, langkah ini terlewati.
3. Admin sekolah melihat "Disetujui" → "Hubungkan" → QR tampil dan berganti sendiri → memindai dari WhatsApp di HP sekolah → "Terhubung · 62812…".
4. "Kirim pesan uji" ke nomornya sendiri → pesan masuk; riwayat mencatat "Terkirim ke WhatsApp".
5. Admin mengatur jenis pemberitahuan dan isi pesannya; modul lain memanggil `GuardianNotifier`.
6. Provider bisa menonaktifkan WhatsApp satu sekolah; halaman sekolah menampilkan alasannya dan pesan berikutnya tercatat "belum terhubung".

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Fondasi: branch, config, klien OpenWA, tabel instance dan pengaturan provider | ✅ | `config/services.php` + `.env.example`; `OpenWaClient`, `OpenWaException`, `CredentialVault`, `CredentialCast`; tabel `whatsapp_instances` dan `provider_settings` dengan model dan factory. `OpenWaClientTest` (26 kasus) dengan HTTP tiruan. |
| 1 | Ajukan dan setujui: kontrak, konsol provider, setujui otomatis, halaman sekolah menampilkan status | ✅ | Kontrak `WhatsappChannel` (`state`, `request`) + `WhatsappState`; aksi `Request/Approve/Reject`; konsol `/whatsapp` dengan sakelar setujui otomatis dan entri menu; izin `core.integration.manage`; `WhatsappController` + panel status di halaman sekolah. `WhatsappInstanceTest` (22 kasus) dan `WhatsappPageTest` (11 kasus). Pemberitahuan, isi pesan, dan riwayat di halaman sekolah masih contoh sampai Tahap 3-4. |
| 2 | Hubungkan: mulai sesi, QR dengan polling, putuskan, nonaktifkan oleh provider | ✅ | `connect`, `disconnect`, `state(refresh)` di kontrak; `SyncWhatsappSession`; aksi `Disable/Enable`; route `hubungkan`/`putuskan` dan `disable`/`enable`; halaman sekolah menampilkan QR dan memperbarui diri tiap 3 detik selama menautkan; tombol Nonaktifkan/Aktifkan di konsol. `WhatsappConnectionTest` (28 kasus) dan tambahan di `WhatsappPageTest` (total 19). Belum dicoba dengan bot sungguhan. |
| 3 | Kirim: log pesan, job, pesan uji, riwayat nyata | ✅ | `sendText` + `WhatsappSendResult` di kontrak; tabel `whatsapp_messages` + model + factory; `PhoneNumber`, `QueueWhatsappMessage`, job `DeliverWhatsappMessage`, aksi `SendTestMessage`; route `uji` (5 pesan uji per menit per admin); riwayat nyata berpaginasi dengan nomor disamarkan. `PhoneNumberTest` (16), `WhatsappSendTest` (23), tambahan `sendText` di `WhatsappConnectionTest` (11). Pemberitahuan dan isi pesan masih contoh sampai Tahap 4. |
| 4 | Pemberitahuan: registry jenis, sakelar, templat, `GuardianNotifier` | ✅ | Kontrak `NoticeRegistry`, `GuardianNotifier`, DTO `NoticeKind`, `GuardianNotice`, `UnknownNoticeKindException`; `DefaultNoticeRegistry`, `DefaultGuardianNotifier`, `NoticeTemplate`, `SaveNoticeSetting`; tabel `whatsapp_notice_settings`; `config/notices.php`; route `pemberitahuan/{kind}`; daftar jenis dan penyunting isi pesan dengan pratinjau di halaman. `GuardianNotifierTest` (26 kasus). Belum ada modul yang mendaftarkan jenis, jadi halaman hanya menampilkan "Segera hadir". |
| 5 | Tes browser, pembersihan mock, dokumentasi | ✅ | `tests/Browser/WhatsappIntegrationTest.php` (ajukan → setujui di konsol → QR → terhubung → pesan uji terkirim) lulus; `IntegrationMockData.php` dihapus; CONTRACT.md Platform dan Core, dokumen arsitektur, `AGENTS.md`, panduan tes browser, dan draf fase 10 diperbarui. |

## Tasks

### Tahap 0 — Fondasi
- [x] Buat branch `feat/whatsapp-integration`.
- [x] Periksa endpoint yang belum pasti (hentikan/hapus sesi, cabut key, daftar sesi) di `https://docs.open-wa.org/api-reference`; catat bentuknya di Temuan.
- [x] Config — `config/services.php`, `.env.example`.
- [x] Migrasi + model + factory — `modules/Platform/database/migrations/0006_01_01_000000_create_whatsapp_instances_table.php`, `0006_01_01_000001_create_provider_settings_table.php`, `modules/Platform/app/Domain/Models/{WhatsappInstance,ProviderSetting}.php`, `modules/Platform/database/factories/WhatsappInstanceFactory.php` (baru); entri `deptrac.baseline.yaml` bila model memakai `BelongsToTenant`.
- [x] `OpenWaClient` + `OpenWaException` — `modules/Platform/app/Infrastructure/Whatsapp/` (baru).
- [x] Tes — `modules/Platform/tests/Feature/OpenWaClientTest.php` (baru): tiap method mengirim URL, method, header `X-API-Key`, dan badan yang benar (`Http::assertSent`); key admin hanya dipakai untuk sesi/key; 4xx/5xx/timeout menjadi `OpenWaException` tanpa key di pesannya; `api_key` tersimpan terenkripsi (nilai mentah di tabel ≠ nilai asli) dan tidak ikut `toArray()`.

**Selesai bila:** klien OpenWA teruji terhadap respons tiruan dan tabel tersedia. Bukti: `php artisan test --compact modules/Platform tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 1 — Ajukan dan setujui
- [x] Kontrak + DTO — `modules/Platform/app/Contracts/WhatsappChannel.php`, `Contracts/DTOs/{WhatsappState,WhatsappSendResult}.php` (baru).
- [x] Aksi `Request/Approve/Reject` + `DefaultWhatsappChannel` (`state`, `request`) + binding — `modules/Platform/app/Infrastructure/Whatsapp/`, `PlatformServiceProvider.php`.
- [x] Konsol: controller, route, halaman, entri menu, pengaturan setujui otomatis — `WhatsappInstanceController.php`, `modules/Platform/routes/web.php`, `Pages/Platform/Whatsapp/Index.tsx` (baru), `Components/ProviderLayout.tsx`.
- [x] Izin `core.integration.manage` — `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`, `modules/Core/config/permission_labels.php`, `modules/Identity/config/roles.php`; gate route dan menu Integrasi.
- [x] `WhatsappController@index,request` + panel status di halaman (belum mengajukan / menunggu / ditolak + catatan / disetujui / dinonaktifkan) — `modules/Core/app/Http/Controllers/WhatsappController.php` (baru), `modules/Core/routes/web.php`, `Pages/Core/Integration/Whatsapp/Index.tsx`.
- [x] Tes — `modules/Platform/tests/Feature/WhatsappInstanceTest.php` (baru): ajukan → `pending`; ajukan dua kali tidak menggandakan; setujui membuat sesi + key berbatas sesi dan menyimpannya; 409 nama sesi → sesi yang ada dipakai; OpenWA gagal → tetap `pending`; tolak dengan catatan lalu ajukan lagi; setujui otomatis; hanya guard `provider` di host konsol; sekolah lain tidak terlihat dari kontrak. `modules/Core/tests/Feature/WhatsappPageTest.php` (baru): admin 200, guru/staf/siswa 403 dan menu hilang, props `state` tanpa key (cari isi key di seluruh JSON respons), isolasi tenant.

**Selesai bila:** pengajuan sekolah muncul di konsol dan keputusan provider terlihat di halaman sekolah. Bukti: `php artisan test --compact modules/Platform modules/Core tests/Architecture` → hijau.

### Tahap 2 — Hubungkan
- [x] `connect`, `disconnect`, `state(refresh)` + aksi `Disable/Enable` — `DefaultWhatsappChannel.php`, `Infrastructure/Whatsapp/`.
- [x] Route `hubungkan`, `putuskan`; polling prop `state`; tampilan QR, "Menghubungkan…", "Terhubung", galat; tombol nonaktifkan/aktifkan di konsol.
- [x] Tes (tambahan di kedua berkas tes): `connect` memanggil `start` sekali walau ditekan berulang; `qr_ready` → `qrCode` berisi data URL; `ready` → nomor dan nama tersimpan, QR hilang; `failed` → `lastError`; putuskan; dinonaktifkan provider → `connect` ditolak; instance belum disetujui → `connect` ditolak; key tidak ada di respons mana pun.

**Selesai bila:** dengan respons tiruan, halaman sekolah berpindah dari QR ke "Terhubung". Bukti: `php artisan test --compact modules/Platform modules/Core` → hijau.

### Tahap 3 — Kirim
- [x] `sendText` di kontrak dan klien; `PhoneNumber` — `DefaultWhatsappChannel.php`, `modules/Core/app/Infrastructure/Whatsapp/PhoneNumber.php` (baru).
- [x] Migrasi `whatsapp_messages`, model, factory; `DeliverWhatsappMessage`; `SendTestMessage`; route `uji`; riwayat berpaginasi — `modules/Core/database/migrations/0004_01_01_000006_create_whatsapp_message_tables.php` (baru) dan berkas terkait.
- [x] Tes — `modules/Core/tests/Unit/PhoneNumberTest.php` (baru): `0812-…`, `+62 812…`, `62812…`, `812…`, kosong, terlalu pendek. `modules/Core/tests/Feature/WhatsappSendTest.php` (baru): pesan uji → baris `pending` → job → `sent` dengan `gateway_message_id`; belum terhubung → `unsent` tanpa memanggil OpenWA; gateway 5xx → dicoba lagi lalu `failed`; nomor tidak sah → `no_recipient`; riwayat menyamarkan nomor; isolasi tenant (ingat `TenantQueueContext::register()`).

**Selesai bila:** pesan uji tercatat terkirim dan muncul di riwayat. Bukti: `php artisan test --compact modules/Core modules/Platform` → hijau.

### Tahap 4 — Pemberitahuan
- [x] Kontrak + DTO — `modules/Core/app/Contracts/{GuardianNotifier,NoticeRegistry}.php`, `Contracts/DTOs/{GuardianNotice,NoticeKind}.php` (baru).
- [x] Registry, notifier, config placeholder, pengaturan per sekolah, `SaveNoticeSetting`, route `pemberitahuan/{kind}` — `modules/Core/app/Infrastructure/Whatsapp/`, `modules/Core/config/notices.php` (baru).
- [x] Halaman: daftar jenis (sakelar, "Segera hadir"), penyunting templat dengan pratinjau.
- [x] Tes — `modules/Core/tests/Feature/GuardianNotifierTest.php` (baru; jenis tiruan di `tests/Feature/Support/`): jenis menyala → satu baris berisi templat terisi, nama dan nomor wali; jenis mati → tidak ada baris; templat sekolah mengalahkan templat bawaan; variabel tak dikenal dibiarkan; siswa tanpa nomor wali → `no_recipient`; jenis tak terdaftar ditolak; placeholder hilang saat jenis terdaftar; isolasi tenant.

**Selesai bila:** sebuah modul bisa mengirim pemberitahuan ke wali murid lewat satu panggilan kontrak. Bukti: `php artisan test --compact modules/Core tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 5 — Penutup
- [x] Tes browser — `tests/Browser/WhatsappIntegrationTest.php` (baru): admin sekolah mengajukan; provider menyetujui di konsol; admin sekolah menekan Hubungkan, melihat QR tiruan, lalu "Terhubung" setelah respons tiruan berganti; pesan uji muncul di riwayat; `assertNoJavaScriptErrors()`.
- [x] Bersihkan: hapus `MasterDataController.php`, `IntegrationMockData.php`, banner mock; pindahkan/hapus tes "pages still on mock data" di `modules/Core/tests/Feature/MasterDataPagesTest.php`; `npm run build`.
- [x] Dokumentasi: `modules/Platform/CONTRACT.md`, `modules/Core/CONTRACT.md`, `docs/architecture/modular-monolith.md`, `AGENTS.md`, memori; perbarui `docs/ai/plan/fase-10/attendance-plan.md` (Tahap 4-nya sudah dikerjakan di sini; pemicu memakai `NoticeRegistry` + `GuardianNotifier`).

**Selesai bila:** alur tiga langkah lulus di browser dan tidak ada sisa mock Integrasi. Bukti: `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/WhatsappIntegrationTest.php` → hijau; `php artisan test --compact` → hijau; `composer deptrac` → 0 pelanggaran; `npm run build` → sukses.

## Batas tindakan
**Selalu**
- `Http::preventStrayRequests()` di setiap tes yang menyentuh WhatsApp.
- `vendor/bin/pint --dirty --format agent` dan tes modul yang disentuh sebelum menandai tahap ✅.
- Perbarui tabel status, centang task, catat penyimpangan di Log keputusan; berhenti setelah tiap tahap.

**Tanya dulu**
- Memanggil `https://bot-wa.lab-jtik.id` selain `GET /api/health`.
- Menambah dependensi (mis. SDK OpenWA), webhook, atau izin selain `core.integration.manage`.
- Mengubah kode Identity selain `config/roles.php`, atau `deptrac.php`.

**Jangan**
- Menulis admin token atau key sesi ke repo, log, pesan galat, props Inertia, atau DTO.
- Memanggil OpenWA dari browser; mengimpor Core dari Platform.
- FK selain `tenant_id`; `DB::table()` untuk data tenant.
- Menghapus tes; commit/push tanpa diminta.

## Pengujian
- **Tahap 0:** klien OpenWA (permintaan yang dikirim, galat), enkripsi key.
- **Tahap 1:** siklus pengajuan, setujui otomatis, guard dan izin, key tidak bocor, isolasi tenant.
- **Tahap 2:** tiap status sambungan, tombol berulang, nonaktif.
- **Tahap 3:** normalisasi nomor; siklus pesan lewat job; kegagalan gateway.
- **Tahap 4:** registry, sakelar, templat, notifier.
- **Tahap 5:** satu alur browser lintas host.

Factory untuk semua data. Setelah Tahap 5, minta user menjalankan suite penuh dan mencoba alur nyata.

## Verifikasi end-to-end
1. `.env` memuat `OPENWA_API_BASE_URL`, `OPENWA_ADMIN_API_KEY`, `OPENWA_CREDENTIALS_KEY`; `php artisan migrate`; `php artisan roles:sync`; worker antrean berjalan.
2. Login admin sekolah → Integrasi › WhatsApp → "Ajukan WhatsApp".
3. Login provider di konsol → WhatsApp → "Setujui" → sesi `simas-…` muncul di dashboard OpenWA.
4. Admin sekolah → "Hubungkan" → pindai QR dari HP → "Terhubung" dengan nomor yang benar.
5. "Kirim pesan uji" ke nomor sendiri → pesan diterima; riwayat "Terkirim ke WhatsApp".
6. Buka DevTools › Network pada halaman sekolah: tidak ada `owa_k1_` di respons mana pun.
7. Nyalakan "Setujui otomatis", ajukan dari sekolah lain → langsung disetujui. Nonaktifkan satu sekolah → halaman sekolah menampilkan alasannya.
8. Login guru/siswa: menu Integrasi tidak ada, URL → 403.

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-02 (Tahap 0) — `WhatsappInstance` dan `ProviderSetting` model biasa tanpa `BelongsToTenant` (pola `Subscription`): dibaca dari konsol provider lintas sekolah dan selalu disaring dengan `tenant_id` eksplisit. `deptrac.baseline.yaml` tidak berubah.
- 2026-10-02 (Tahap 4) — Jenis pemberitahuan **mati secara bawaan**: tanpa baris pengaturan, tidak ada pesan dan tidak ada log. Alasan: pesan keluar dari nomor sekolah ke wali murid, jadi sekolah yang memutuskan. Sakelar bisa diubah walau WhatsApp belum terhubung (pesannya tercatat `unsent`).
- 2026-10-02 (Tahap 4) — `NoticeKind::variables` berupa peta nama → contoh nilai (bukan daftar nama), supaya pratinjau di halaman punya isi. Core mengisi sendiri `{nama_siswa}`, `{nama_wali}`, `{nama_sekolah}`; pemanggil tidak bisa menimpanya.
- 2026-10-02 (Tahap 4) — Jenis yang tidak terdaftar atau modulnya tidak aktif untuk sekolah itu → `UnknownNoticeKindException` (kontrak). Siswa yang tidak ditemukan (termasuk milik sekolah lain) → diam, tanpa log.
- 2026-10-02 (Tahap 4) — Templat yang kosong atau sama dengan bawaan tidak disimpan (`template = null`), supaya perubahan bawaan dari modul sampai ke sekolah.
- 2026-10-02 (Tahap 4) — Placeholder `config/notices.php`: `attendance.absent`, `attendance.gate`, `ppdb.result`. Pengingat tagihan dan pengumuman dari mockup lama dibuang: penerimanya bukan wali murid dan tidak ada modul pemiliknya.
- 2026-10-02 (Tahap 5) — Prop `mock` di `Components/MasterPage.tsx` dibiarkan: semua halaman sudah mengirim `mock={false}`, dan menghapusnya menyentuh belasan halaman di luar cakupan fase ini.
- 2026-10-02 (Tahap 5) — Suite Playwright (`tests/E2E`) tidak dijalankan dan tidak ditambah: tidak ada spec yang menyentuh Integrasi, dan server sungguhan tidak bisa memalsukan OpenWA (sudah di "Di luar cakupan").
- 2026-10-02 (Tahap 3) — Migrasi dipecah: `…000006_create_whatsapp_messages_table.php` sekarang, `whatsapp_notice_settings` di Tahap 4, supaya `migrate` di antara tahap tetap aman.
- 2026-10-02 (Tahap 3) — Satu pintu keluar pesan: `Infrastructure/Whatsapp/QueueWhatsappMessage` (tulis log → antrekan job). Pesan uji memakainya sekarang, `GuardianNotifier` memakainya di Tahap 4. Nomor tak sah dicatat `no_recipient` tanpa job; untuk pesan uji, nomor tak sah ditolak sebagai galat form.
- 2026-10-02 (Tahap 3) — "Belum terhubung" diputuskan dari status tersimpan (`connection_status = ready`), tanpa memanggil gateway; pesan dicatat `unsent`.
- 2026-10-02 (Tahap 3) — Job mencoba lagi dengan `release()` (jeda 30 lalu 120 detik, 3 percobaan) hanya untuk galat `retryable`; di driver `sync` tidak ada percobaan ulang, pesan langsung `failed`. Job yang berjalan dua kali tidak mengirim dua kali (hanya pesan `pending` yang diproses).
- 2026-10-02 (Tahap 3) — Pesan uji dibatasi 5 per menit per admin (`whatsapp-test:{tenant}:{user}:{ip}`), karena tiap pesan benar-benar keluar dari nomor sekolah.
- 2026-10-02 (Tahap 3) — Riwayat menampilkan nama penerima, nomor tersamar (`62812••••7890`), jenis, status, dan galat; isi pesan disimpan di tabel tetapi tidak dikirim ke halaman. Halaman memperbarui riwayat tiap 3 detik selama ada pesan "Dalam antrean".
- 2026-10-02 (Tahap 3) — `deptrac.baseline.yaml` bertambah satu entri untuk `WhatsappMessage` (pemakai `BelongsToTenant`, satu-satunya alasan yang diizinkan).
- 2026-10-02 (Tahap 2) — "Putuskan" memanggil `logout` lalu `stop` (400 pada keduanya dianggap selesai), supaya mesin sesi tidak tetap berjalan menunggu QR baru. Tombol "Batal" selama menautkan memakai jalur yang sama.
- 2026-10-02 (Tahap 2) — "Hubungkan" membaca status sesi dulu dan hanya memanggil `start` bila sesi tidak sedang berjalan (`initializing`, `qr_ready`, `authenticating`, `ready`); jawaban 400 dari `start` dianggap "sudah berjalan".
- 2026-10-02 (Tahap 2) — Kontrak tidak melempar untuk kegagalan gateway: galat disimpan di `last_error` dengan kalimat untuk sekolah (`OpenWaException::forSchool()`: tanpa URL, id sesi, atau key) dan kembali lewat `WhatsappState::lastError`. Sekolah yang belum disetujui atau dinonaktifkan mendapat `WhatsappUnavailableException` (kontrak baru di `Contracts/Exceptions`).
- 2026-10-02 (Tahap 2) — "Nonaktifkan" tetap berlaku di SIMAS walau gateway tidak menjawab `stop`; konsol menampilkan galatnya. "Aktifkan" tidak memanggil gateway: sekolah menekan Hubungkan sendiri.
- 2026-10-02 (Tahap 2) — `InstanceNotPendingException` diganti `InstanceStatusException` (`notPending`, `notActive`, `notDisabled`); klien OpenWA mendapat `connectTimeout(5)` karena halaman sekolah kini membaca gateway saat dimuat.
- 2026-10-02 (Tahap 1) — Kontrak tumbuh per tahap: `WhatsappChannel` baru memuat `state()` dan `request()`; `connect`/`disconnect`/`state(refresh)` masuk di Tahap 2, `sendText` + `WhatsappSendResult` di Tahap 3. Alasan: tidak ada method kontrak tanpa implementasi.
- 2026-10-02 (Tahap 1) — Setujui otomatis hanya dicoba saat pengajuan baru menjadi `pending` (baru, atau diajukan lagi setelah ditolak). Bila gateway gagal, pengajuan tetap `pending` dan galatnya disimpan di `last_error` untuk konsol; mengajukan ulang saat `pending` tidak memanggil gateway lagi. Pengajuan yang sudah menunggu saat sakelar dinyalakan tetap disetujui manual.
- 2026-10-02 (Tahap 1) — `last_error` dari persetujuan yang gagal hanya tampil di konsol provider; `WhatsappState::lastError` baru terisi saat instance `active`.
- 2026-10-02 (Tahap 1) — Catatan penolakan disimpan saat sekolah mengajukan lagi (tampil di konsol sebagai "pernah ditolak"), tetapi tidak lagi dikirim ke halaman sekolah.
- 2026-10-02 (Tahap 1) — `MasterDataController.php` dihapus sekarang, bukan di Tahap 5: route satu-satunya pindah ke `WhatsappController`. `IntegrationMockData.php` tetap dipakai `WhatsappController` untuk bagian yang masih contoh. Baris tentang `MasterDataController` di `modules/Core/CONTRACT.md` diperbaiki di Tahap 5.
- 2026-10-02 (Tahap 1) — Konsol menampilkan peringatan bila `OPENWA_API_BASE_URL`, `OPENWA_ADMIN_API_KEY`, atau `OPENWA_CREDENTIALS_KEY` belum sah (prop `gatewayReady`); persetujuan tanpa kunci kredensial ditolak sebelum ada panggilan ke gateway.
- 2026-10-02 (Tahap 1) — Ekspektasi tes lama diubah karena perilaku memang berubah: guru tidak lagi melihat menu Integrasi (`TenantNavigationTest`), daftar izin Core dan izin `admin-sekolah` bertambah `core.integration.manage` (`MasterPermissionsTest`, `DefaultRolesTest`).
- 2026-10-02 (Tahap 0) — `OpenWaException` membawa `status` dan `retryable()` (tanpa jawaban, 429, 5xx) supaya job pengirim di Tahap 3 tahu kapan mencoba lagi.

### Temuan
- (Tahap 0) Endpoint yang tadinya belum pasti, dari `https://docs.open-wa.org/guides/sessions` dan `/guides/authentication`: `GET /api/sessions?name=` (daftar; disaring lagi dengan nama persis di klien), `POST /api/sessions/{id}/stop` (200; sesi yang sudah berhenti tidak dianggap galat; tautan perangkat tetap), `POST /api/sessions/{id}/logout` (200; 400 bila sesi belum dimulai; tautan dilepas), `DELETE /api/sessions/{id}` (204; riwayat dan kredensial dihapus), `POST /api/auth/api-keys/{id}/revoke` (200), `POST /api/sessions/{id}/pairing-code` (ada, tidak dipakai). `POST …/start` pada sesi yang sudah berjalan menjawab 400.
- (Setelah selesai, dari percobaan user dengan worker sungguhan) Job `DeliverWhatsappMessage` gagal dengan `TenantNotSetException`: `TenantQueueContext` memulihkan tenant lewat `TenantContext::set($id)`, yang hanya mengisi id; `current()` / `currentOrFail()` tetap kosong di worker. Tes tidak menangkapnya karena memakai driver `sync` di dalam `TenantContext::run()`, yang menghidrasi sendiri. Perbaikan di Platform: `DefaultTenantContext::set()` sekarang ikut memuat data tenant bila id-nya berbeda. Ini juga menutup bug lain yang ikut terlihat: `run()` bersarang ke tenant lain dulu menjawab `current()` dengan tenant luar. Tes baru: `WhatsappSendTest` "delivers from a queue worker" (driver `database` + `queue:work --once`) dan dua kasus di `TenantQueueTest`; ketiganya gagal sebelum perbaikan.
- (Setelah selesai) Job yang rusak sendiri (bukan ditolak gateway) dulu meninggalkan baris log "Dalam antrean" selamanya; `DeliverWhatsappMessage::failed()` sekarang menutupnya sebagai `failed` dengan pesan umum. Penyebabnya tetap di `failed_jobs`.
- (Tahap 5) Di suite browser, `auth:provider` meninggalkan `provider` sebagai guard bawaan untuk sisa proses; kembali dari konsol ke halaman sekolah perlu `app('auth')->shouldUse('web')` + `refreshSignedInUsers()`. Bukan bug aplikasi (server sungguhan memulai tiap permintaan dari bawaan); dicatat di `.claude/skills/writing-tests/browser-tests.md`.
- (Tahap 4) Nama helper tes di satu namespace harus unik lintas berkas (`studentIn` sudah dipakai `StudentAccountsTest.php`); bentrok baru terlihat saat suite penuh dijalankan.
- (Tahap 3) Tanpa worker antrean, pesan tetap "Dalam antrean". Dev: `composer run dev` (`php artisan dev`) sudah menjalankan `queue:listen`. **Shared hosting Hostinger (rencana deploy user, ditandai penting 2026-10-02):** tidak ada proses yang hidup terus, jadi satu cron tiap menit: `* * * * * cd /path/ke/simas && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1` (diganti di fase 16 menjadi `--sleep=2 --max-time=50`, karena pesan yang ditunda pacing tidak ditunggu `--stop-when-empty`). Pesan keluar paling lambat sekitar satu menit; tidak lewat `schedule:run` karena butuh `proc_open`. Belum dicek di paket user: interval cron minimum, path PHP 8.4 untuk cron, status `proc_open`. Rinciannya di `docs/architecture/modular-monolith.md` § WhatsApp per school. Di `.env` dev `QUEUE_CONNECTION=database`.
- (Tahap 3) Belum dipastikan dengan bot sungguhan: bentuk `chatId` (`62…@c.us`) dan field `messageId` di jawaban `send-text`.
- (Tahap 2) Belum dipastikan dengan bot sungguhan: (a) key `operator` berbatas sesi boleh memanggil `stop` dan `logout`; (b) status sesi setelah `logout`; (c) nama field `phone`/`pushName`/`lastError` di jawaban `GET /api/sessions/{id}` persis seperti di dokumentasi. Bila berbeda, yang berubah hanya `SyncWhatsappSession::record()` dan `DefaultWhatsappChannel::disconnect()`.
- (Tahap 2) Halaman sekolah membaca gateway sekali tiap dimuat selama instance aktif (dan tiap 3 detik selama menautkan). Gateway yang mati menambah jeda sampai batas waktu sambungan (5 detik).
- (Tahap 2) `Http::fake([...])` dengan peta URL memanggil **semua** stub untuk tiap permintaan dan memakai jawaban pertama yang tidak kosong, sehingga `Http::sequence()` di salah satu pola ikut terpakai oleh permintaan pola lain. Tes yang butuh urutan memakai satu closure.
- (Tahap 2) `AssertableInertia::reloadOnly()` membuat permintaan baru tanpa sesi sekolah (tenant ada di sesi), jadi tes muat-ulang sebagian mengirim header `X-Inertia-*` sendiri.
- (Tahap 1) Saat server Vite dev berjalan (`public/hot` ada), pemuatan halaman penuh di tes meminta render SSR lewat HTTP ke server itu, sehingga `Http::preventStrayRequests()` menggagalkannya. Tes WhatsApp mematikan `inertia.ssr.enabled` di `beforeEach`.
- (Tahap 1) Sekolah yang sudah ada belum punya izin `core.integration.manage`: jalankan `php artisan roles:sync` setelah menarik perubahan ini, kalau tidak admin sekolah mendapat 403 dan menu Integrasi hilang.
- (Tahap 0) Laravel `Http::send()` meneruskan kata kerja apa adanya; klien mengubahnya ke huruf besar supaya `Request::method()` konsisten.
- Admin token yang dikirim user di obrolan berupa `XXXX` (disamarkan); nilainya tidak pernah ada di repo.
- Dokumentasi OpenWA memperingatkan risiko blokir nomor pada gateway tidak resmi; pakai nomor khusus sekolah, bukan nomor pribadi.

### Hasil akhir
Selesai 2026-10-02 di branch `feat/whatsapp-integration`, belum di-commit.

- **Yang jadi:** sekolah mengajukan WhatsApp, provider menyetujui/menolak (atau otomatis), sekolah menautkan nomor lewat QR, mengirim pesan uji, mengatur jenis pemberitahuan dan isi pesannya, dan melihat riwayat pesan nyata. Provider bisa menonaktifkan/mengaktifkan WhatsApp satu sekolah. Modul lain mengirim ke wali murid lewat `GuardianNotifier`. API key sesi tidak pernah keluar dari server.
- **Bukti:** `php artisan test --compact` → 891 lulus (setelah perbaikan worker antrean); `vendor/bin/pest -c phpunit.e2e.xml` → 20 lulus (termasuk `WhatsappIntegrationTest`); `vendor/bin/deptrac analyse` → 0 pelanggaran; PHPStan 29 galat lama, tidak ada yang baru; pemeriksaan tipe halaman modul hanya menyisakan galat lama di halaman Penempatan; `npm run build` sukses.
- **Belum dibuktikan:** alur dengan bot sungguhan (keputusan user: tes memakai HTTP tiruan). Hal yang diambil dari dokumentasi dan perlu dilihat saat mencoba: key `operator` berbatas sesi boleh `stop`/`logout`; status sesi setelah `logout`; nama field `phone`/`pushName`/`lastError`; bentuk `chatId` dan `messageId`.
- **Langkah user sebelum mencoba:** `php artisan migrate`, `php artisan roles:sync`, worker antrean berjalan.
- **Sisa untuk nanti:** belum ada `NoticeKind` yang terdaftar (Absensi fase 10, PPDB); status terkirim/dibaca butuh webhook; prop `mock` di `MasterPage`; temuan fase 8 yang belum diputuskan (cache flag modul, `tsc` tidak mencakup halaman modul).
