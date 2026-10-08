# Billing siap-nyata: fungsi trial, pembayaran, langganan, dan invoice untuk operasional provider

> **Status dokumen:** Berjalan
> **Dibuat:** 2026-10-08 · **Diperbarui:** 2026-10-08 · **Branch:** `feat/billing-siap-nyata`

## Context
Billing provider sudah ada di `modules/Platform` (fase 3): `plans`, `subscriptions`, `invoices`, dan layanan di `modules/Platform/app/Infrastructure/Billing/` (`SubscriptionManager`, `InvoiceIssuer`, `PaymentGateway`, `BillingSummary`). Tetapi hampir semuanya masih "demo": gateway `AlwaysSucceedsPaymentGateway` selalu `true`; tidak ada yang menerbitkan invoice perpanjangan, mengirim pengingat, atau menyuspend sekolah (status `due`/`overdue`/`trial_expired` hanya dihitung dari tanggal); invoice tidak punya dokumen atau pengiriman; `plans.max_users` tidak pernah dihitung atau dipakai; dan panel "Paket & Langganan" serta "Tagihan & Invoice" di `modules/Shared/resources/js/components/AccountMenu.tsx` masih mockup (angka dan invoice tulisan tangan).

Pemilik produk ingin fungsi-fungsinya disiapkan lebih dulu, lalu fase berikutnya memasang data nyata dan UI di atasnya. Seluruh desain diputuskan lewat peta wayfinder `InYrDim/my-simas#4` (tiket #5–#13, semua ditutup; resolusi tiap tiket ada di komentarnya). Dokumen ini menerjemahkan keputusan itu menjadi tahap kerja; riset pasar pendukung ada di `docs/research/reports/Penawaran plan SaaS sekolah.md` (angka vendor perlu dicek ulang sebelum dikutip sebagai harga final). Istilah baku ada di `CONTEXT.md`.

Kode sekarang memuat dua jebakan yang harus ditutup: (1) `SubscriptionManager::payInvoice` menulis ulang `plan_id` dan modul dari snapshot invoice, jadi membayar invoice lama membatalkan ganti plan; (2) `syncModules()` mematikan semua modul yang tidak ada di plan pada tiap pembayaran, sehingga modul tambahan yang dinyalakan provider per sekolah ikut mati saat perpanjangan. Tabel `invoices`/`subscriptions` juga tidak memakai `BelongsToTenant` (tabel pusat), jadi tiap query dari sisi sekolah wajib menyaring `tenant_id` sendiri.

## Goal
Setelah ini, provider dapat menjalankan siklus langganan sekolah dari trial sampai suspend tanpa kerja manual berulang: invoice perpanjangan terbit sendiri, pengingat dan invoice dikirim lewat email dengan PDF, pembayaran (awalnya transfer manual) dikonfirmasi lewat form yang tercatat dan hanya sekali berlaku, dan sekolah yang menunggak melewati masa tenggang disuspend dengan aman. Admin sekolah dapat melihat langganan, pemakaian, dan invoice, berlangganan, ganti plan, dan memulai pembayaran lewat fungsi `TenantBilling`, dan panel mockup di `AccountMenu` memakai data nyata dari fungsi itu.

## Batasan masalah

**Dalam cakupan**
- Model pembayaran asinkron (`payments`) dan satu pintu penyelesaian `settle()` yang idempoten; konfirmasi manual oleh provider.
- Siklus hidup: tenggang, suspend karena billing dengan alasan tercatat, buka kembali otomatis, `cancel` sebagai "tidak diperpanjang", `billing_exempt`, halaman netral "Akses dihentikan", dan berhentinya pekerjaan antrean sekolah yang disuspend.
- Dokumen invoice/kuitansi PDF, enam jenis email billing, catatan pengiriman, kontak tagihan per sekolah, perbaikan penomoran invoice.
- Perintah terjadwal `billing:daily` (terbit perpanjangan, pengingat, suspend, kedaluwarsa payment) dengan pengaman dan penanda cron mati.
- Pemakaian dan batas plan (`students`, `staff_accounts`, `storage`) lewat registry, hanya ditampilkan; `plans.limits` menggantikan `max_users`; `plans.is_public`.
- Flag modul bersumber (`plan` atau `manual`) dan aturan ganti plan (upgrade prorata, downgrade terjadwal).
- Kontrak sisi sekolah `TenantBilling`, izin `platform.billing.view`/`pay`, prop Inertia `billing`, dan penggantian mockup `AccountMenu`.
- Dokumentasi kontrak dan arsitektur.

**Di luar cakupan**
- Gateway pembayaran nyata dan webhook — fase "data real"; seam sudah siap (`initiate()` + `settle()`).
- Penegakan batas plan (menolak melebihi batas) dan pengecualian kuota per sekolah — setelah ada data nyata.
- PPN pada invoice (bergantung status PKP provider), kupon/diskon, kredit saldo, pengembalian dana, tagihan semesteran.
- Mode terbatas untuk sekolah yang disuspend, unggah bukti transfer, sekolah menghentikan langganan sendiri, admin sekolah mengubah kontak tagihan sendiri, WhatsApp dari provider ke sekolah.
- Mengisi data nyata (harga final, batas siswa/penyimpanan per plan, rekening provider) — diisi provider lewat console dan `.env`.
- Konfirmasi ke dinas pendidikan soal PPDB dan langganan SaaS terhadap Pasal 66 Permendikdasmen 8/2026.

**Asumsi**
- `barryvdh/laravel-dompdf` (atau pustaka PDF setara) kompatibel dengan Laravel dan PHP 8.4 yang terpasang; diperiksa dengan `composer show` di Tahap 5. Bila tidak, **Tanya dulu**.
- Flag `tenant_modules` yang sudah ada diberi `source = 'plan'` (perilaku sekarang tidak berubah); provider menyalakan ulang modul tambahan secara manual setelah migrasi bila perlu.
- `billing_email` sekolah lama diisi dari aplikasi terakhirnya; sekolah tanpa aplikasi tidak punya kontak tagihan sampai provider mengisinya (email billing dicatat gagal, bukan error).
- Cron hosting hanya menjalankan perintah `php artisan` biasa (tanpa `schedule:run`), seperti `docs/config/cron.md`.
- Bila job antrean tidak bisa dibatalkan dari event `JobProcessing`, pelepasan job untuk sekolah yang disuspend dikerjakan di titik pengiriman (lihat Tahap 4).

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Urutan lapisan Shared → Platform → Identity → Core → modul fitur; billing tetap internal Platform; lintas modul hanya lewat `app/Contracts/**` dengan DTO/skalar, tanpa model Eloquent (sumber: `AGENTS.md`, `docs/architecture/modular-monolith.md`).
- Shared tidak boleh mengimpor modul; URL aksi dan data sampai ke Shared lewat prop Inertia, bukan impor Wayfinder Platform (sumber: `tests/Architecture/ModularMonolithTest.php`, `.ai/rules/js.md`).
- Core dan Identity mendaftarkan meter dari provider masing-masing; Platform tidak mengimpor Core/Identity (sumber: `AGENTS.md` § Layer order; pola `TenantNavigation`).
- Tanpa FK selain `tenant_id`; `invoice_id`, `plan_id`, `scheduled_plan_id`, `confirmed_by` adalah kolom biasa (sumber: arch test "no cross-module FKs").
- `DB::table()` melewati scope tenant; tabel pusat (`invoices`, `subscriptions`, `payments`) disaring `tenant_id` eksplisit (sumber: `AGENTS.md` § Tenancy traps).
- Email billing berupa Mailable teks yang diantrekan dengan nilai skalar siap pakai, tanpa mesin Notification; URL lewat `TenantUrl` (sumber: `AGENTS.md` § Identity; contoh `modules/Platform/app/Infrastructure/Onboarding/ApplicantDecisionNotifier.php`).
- Kunci cache lewat `TenantCache`; jangan cache model Eloquent atau hasil `null` (sumber: `AGENTS.md`).
- UI: komponen dasar dari `modules/Shared` (shadcn), modal untuk perpanjangan halaman, tidak ada modal di dalam modal, tombol tulis memakai `useCan()`/`<Can>` (sumber: `.ai/rules/js.md`).
- Roles/permission berubah → jalankan `php artisan roles:sync` setelah rilis (sumber: `AGENTS.md`).
- Dependensi Composer hanya bertambah dengan persetujuan; persetujuan untuk satu pustaka PDF sudah ada (lihat Keputusan).
- Aktifkan skill `modular-monolith`, `laravel-best-practices`, `writing-tests`, `testing-best-practices`; untuk UI juga `inertia-react-development`, `wayfinder-development`, `tailwindcss-development`; baca `modules/Platform/CONTRACT.md` sebelum mulai.

**Keputusan** (tiket = issue `InYrDim/my-simas`)
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-08 | Pembayaran: tabel `payments` (pending/paid/failed/expired); `charge()` diganti `initiate()` + `settle()` idempoten dengan kunci baris invoice; satu invoice unpaid per langganan; periode di invoice adalah perkiraan sampai dibayar; hanya nominal pas diterima; konfirmasi manual mencatat metode, referensi, tanggal bayar, catatan, dan pengonfirmasi (#5) | user |
| 2026-10-08 | Siklus hidup: tenggang 7 hari (trial dan periode, dua nilai config); suspend dengan alasan `billing`/`manual`, hanya `billing` yang dibuka otomatis; `cancel` = tidak diperpanjang, akses sampai akhir periode, lalu suspend tanpa tenggang; sisa trial hangus saat bayar; `billing_exempt` (#6) | user |
| 2026-10-08 | Dokumen invoice: kontak tagihan di tenant (`billing_email`, `billing_name`); email saja, enam jenis pesan; PDF dirender saat diminta, satu dokumen untuk invoice dan kuitansi; identitas/rekening provider dari `config/billing.php` kunci `issuer`; tabel `billing_notices`; penomoran dikunci (#8) | user |
| 2026-10-08 | Menambah satu pustaka PDF (rekomendasi `barryvdh/laravel-dompdf`) disetujui | user |
| 2026-10-08 | Plan: kuota siswa + modul sebagai pembeda; harga tetap per plan; `plans.is_public` untuk plan khusus; flag modul bersumber `plan`/`manual`; harga tahunan 10× bulanan; trial 14 hari fitur penuh; PPDB hanya di Pro; akun staf dan penyimpanan batas lunak (#13) | user |
| 2026-10-08 | Pemakaian: semua baris siswa dihitung apa pun statusnya; akun staf = pengguna aktif tanpa peran `siswa`; penyimpanan = byte di `tenants/{id}` (cache 15 menit); `plans.limits` json menggantikan `max_users`; registry `UsageMeters`; hanya ditampilkan (#7) | user |
| 2026-10-08 | Terjadwal: satu `billing:daily` lewat crontab (tanpa `schedule:run`), empat langkah, `BillingClock` Asia/Jakarta, idempoten lewat `billing_notices`, pengaman suspend massal, penanda cron mati (#11) | user |
| 2026-10-08 | Sisi sekolah: kontrak `TenantBilling`, prop Inertia opsional `billing` dengan URL aksi, izin `platform.billing.view`/`pay` untuk `admin-sekolah`, transfer manual dengan laporan sekolah, `subscribe` hanya plan publik saat trial/expired/cancelled (#10) | user |
| 2026-10-08 | Halaman "Akses dihentikan": satu halaman Blade netral (nama sekolah dan kontak provider saja), tetap 403, pekerjaan antrean sekolah yang disuspend dihentikan; menggantikan usulan awal di #6 (#12) | user |
| 2026-10-08 | Ganti plan: upgrade = invoice selisih prorata, aktif setelah dibayar; downgrade dijadwalkan di akhir periode; invoice usang di-void saat plan berubah; provider tetap bisa mengganti langsung tanpa biaya (#9) | user |

## Desain

### Platform (pemilik billing)
- **Data baru/berubah** (migrasi baru berawalan `0010_01_01_...`): `payments` (baru); `plans.limits` json + `plans.is_public` (menggantikan `max_users`); `tenants.billing_email`, `billing_name`, `billing_exempt`, `suspended_reason`; `subscriptions.scheduled_plan_id`; `invoices.kind` (`activation`/`renewal`/`upgrade`); `tenant_modules.source`; `billing_notices` (baru).
- **Layanan** di `modules/Platform/app/Infrastructure/Billing/`: `BillingClock` (baru di `Domain/Support`, `today()` zona `billing.timezone`), `SubscriptionManager` (`settle`, `requestPlanChange`, `cancelScheduledChange`, `changeCycle`, `cancel`; `payInvoice` dipensiunkan), `InvoiceIssuer` (pakai ulang atau void invoice unpaid, `issueUpgrade`, penomoran dikunci), `ManualTransferGateway` (baru, menggantikan `AlwaysSucceedsPaymentGateway`), `BillingNotifier` dan `InvoiceDocument` (baru), serta langkah harian di `Billing/Daily/` (baru).
- **Kontrak publik baru** di `modules/Platform/app/Contracts/`: `TenantBilling`, `UsageMeters`, `TenantUsage`, DTO `BillingOverview`, `InvoiceSummary`, `PlanOffer`, `PaymentInstructions`, `PlanChangeResult`, `UsageLine`. Implementasinya internal dan diikat di `PlatformServiceProvider`.
- **Suspend**: `TenantSuspendedException` (internal) dilempar `ResolveTenant`, dirender view Blade `Platform::tenant-suspended` (folder `modules/Platform/mail/`, namespace `Platform` lewat `loadViewsFrom`).
- **Rute**: console memakai grup billing yang ada di `modules/Platform/routes/web.php` (`billing.*`, ditambah `billing.invoices.confirm`/`pdf`/`resend`); sisi sekolah grup baru `school.billing.*` di file yang sama, `Route::middleware(['web','auth'])` dengan `can:platform.billing.*`.

### Core dan Identity (hanya mendaftar)
- Core mendaftarkan meter `students` (semua baris `Student` di tenant aktif) dari `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`.
- Identity mendaftarkan `staff_accounts` (pengguna dengan `deactivated_at` kosong, tanpa peran `siswa`, lewat `HasTenantRoles`, bukan Spatie langsung) dari `modules/Identity/app/Infrastructure/Providers/IdentityServiceProvider.php`, dan menambahkan izin billing ke `admin-sekolah` di `modules/Identity/config/roles.php`.

### Shared
- `modules/Shared/resources/js/components/AccountMenu.tsx`: panel Paket & Langganan dan Tagihan & Invoice membaca prop `billing` (dimuat lewat partial reload saat dialog dibuka); mockup dihapus. Aksi (bayar, lapor transfer, berlangganan, ganti plan) berjalan sebagai langkah di dalam dialog yang sama, bukan dialog bersarang.

### Alur
1. Persetujuan aplikasi memulai trial 14 hari (sudah ada). Saat sekolah atau provider mengaktifkan langganan, invoice `activation` terbit dan diemail dengan PDF.
2. Sekolah transfer; sekolah boleh melapor transfer; provider mengonfirmasi lewat form → `settle()` menandai Payment dan Invoice `paid`, memperpanjang periode, menyinkronkan modul bersumber `plan`, membuka kembali suspend `billing`, dan mengirim kuitansi.
3. `billing:daily` tiap pagi WIB: terbitkan invoice perpanjangan H-14, kirim pengingat, suspend sekolah yang tenggangnya lewat, kedaluwarsakan Payment gateway.
4. Ganti plan oleh sekolah: upgrade → invoice selisih; downgrade → terjadwal ke akhir periode.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 1 | Fondasi: skema, `BillingClock`, config, plan (`limits`, `is_public`) di console | ✅ | Tiga migrasi, `BillingClock` di `Domain/Support`, plan publik/khusus, batas di console dan pemilih plan. Suite Platform 335/335 hijau. `BillingClock` pindah dari `Infrastructure/Billing` ke `Domain/Support` (model Domain memakainya) |
| 2 | Pembayaran asinkron, `settle()`, satu invoice unpaid, form konfirmasi manual | ✅ | `payments`, `ManualTransferGateway`, `settle()` idempoten dengan kunci baris invoice, `InvoiceIssuer::issueActivation/issueRenewal`, form konfirmasi di `Invoices.tsx`. Dikerjakan di worktree terpisah, digabung `ba669e5`. Platform 369 tes hijau |
| 3 | Flag modul bersumber dan ganti plan (upgrade prorata, downgrade terjadwal) | ⬜ | Setelah 2 |
| 4 | Siklus hidup: tenggang, suspend bersebab, halaman "Akses dihentikan", `billing_exempt` | ⬜ | Setelah 2 |
| 5 | Dokumen invoice PDF, email billing, catatan pengiriman, kontak tagihan | ⬜ | Setelah 1 (memakai `settle()` dari 2 untuk kuitansi) |
| 6 | `billing:daily` dengan pengaman dan penanda cron | ⬜ | Setelah 2, 4, 5 |
| 7 | Pemakaian dan batas plan (registry, meter, tampilan console) | ✅ | `UsageMeters`/`TenantUsage`/`UsageLine`, meter `students` (Core), `staff_accounts` (Identity), `storage` (Platform), panel Pemakaian dan tanda melebihi batas di console. Dikerjakan paralel di worktree, digabung `ef5e007`. Core 550 + Identity 163 + Platform 369 tes hijau |
| 8 | Sisi sekolah: `TenantBilling`, izin, prop `billing`, `AccountMenu` nyata | ⬜ | Setelah 2, 3, 5, 7 |
| 9 | Dokumentasi, pemeriksaan batas modul, penutupan | ⬜ | Terakhir |

Tahap 2, 5 (sebagian), dan 7 saling bebas setelah Tahap 1 dan boleh dikerjakan paralel di worktree terpisah **bila user memberi izin**; tetap berhenti dan minta persetujuan di akhir tiap tahap.

## Tasks

### Tahap 1 — Fondasi
- [x] Migrasi `plans`: tambah `limits` json nullable dan `is_public` bool default true, pindahkan `max_users` ke `limits.staff_accounts`, hapus `max_users` — `modules/Platform/database/migrations/0010_01_01_000000_add_limits_and_visibility_to_plans.php` (baru)
- [x] Migrasi `tenants`: `billing_email`, `billing_name` (nullable), `billing_exempt` (bool default false), `suspended_reason` (nullable string) — `modules/Platform/database/migrations/0010_01_01_000001_add_billing_fields_to_tenants.php` (baru)
- [x] Migrasi `subscriptions.scheduled_plan_id` (kolom biasa, indeks), `invoices.kind` (default `renewal`), `tenant_modules.source` (default `plan`) — `modules/Platform/database/migrations/0010_01_01_000002_add_billing_lifecycle_columns.php` (baru)
- [x] Model: `Plan` (cast `limits`, `is_public`, scope `public`), `Tenant`, `Subscription`, `Invoice` (+ enum `InvoiceKind`, baru), `TenantModules` — `modules/Platform/app/Domain/Models/`; perbarui factory — `modules/Platform/database/factories/`
- [x] `BillingClock::today()` (baru) dan ganti semua `Carbon::today()` billing — `modules/Platform/app/Domain/Support/BillingClock.php` (baru, bukan di `Infrastructure/Billing`: model `Subscription`/`Invoice` memakainya), `SubscriptionManager.php`, `InvoiceIssuer.php`, `BillingSummary.php`, `InvoiceController.php`, `ProviderHomeController.php`, `modules/Platform/app/Domain/Models/Subscription.php`, `Invoice.php`
- [x] Config: `timezone`, `trial_grace_days`, `overdue_grace_days`, `renewal_invoice_days_before`, `reminders`, `suspend_cap`, `cron_stale_days`, `issuer` (nama, email, WhatsApp/telepon, alamat, rekening dari env) — `config/billing.php`, `.env.example`
- [x] Console plan: validasi `limits.*` dan `is_public`, form modal (tiga batas + visibilitas), tabel menampilkan batas — `modules/Platform/app/Http/Controllers/PlanController.php`, `modules/Platform/app/Http/Support/ConsoleResources.php`, `modules/Platform/resources/js/Pages/Platform/Billing/Plans.tsx`, `modules/Platform/resources/js/types/console.ts`
- [x] Onboarding: ganti `maxUsers` dengan batas dari `limits`; hanya plan publik tampil — `modules/Platform/app/Http/Controllers/Applicant/OnboardingController.php`, `modules/Platform/resources/js/Components/PlanPicker.tsx`
- [x] Seeder: batas awal (Starter 300 siswa/25 staf, Standard 1.000/100, Pro tanpa batas), PPDB hanya di Pro — `modules/Platform/database/seeders/BillingMasterDataSeeder.php`
- [x] Tes: migrasi memindahkan `max_users`; `BillingClock` memakai zona billing; plan tidak publik tidak tampil di pemilih; form plan menyimpan batas — `modules/Platform/tests/Feature/BillingFoundationTest.php` (baru), sesuaikan `ProviderConsoleTest.php`

**Selesai bila:** `php artisan migrate` berjalan bersih, provider dapat mengisi batas dan visibilitas plan di console, dan pemilih plan hanya menampilkan plan publik. Bukti: `php artisan test --compact modules/Platform` → hijau; `npm run types:check` → tanpa galat.

### Tahap 2 — Pembayaran asinkron
- [x] Migrasi `payments` (invoice_id, tenant_id, amount, method, status, reference, gateway, external_id, paid_on, paid_at, confirmed_by, note, meta, expires_at; unik `gateway`+`external_id`) — `modules/Platform/database/migrations/0010_01_01_000003_create_payments_table.php` (baru)
- [x] Model `Payment`, enum `PaymentStatus`, `PaymentFactory` — `modules/Platform/app/Domain/Models/`, `modules/Platform/database/factories/` (baru)
- [x] Seam: ganti `PaymentGateway::charge()` dengan `initiate(Invoice): Payment`; `ManualTransferGateway` (instruksi dari `config('billing.issuer')`); hapus `AlwaysSucceedsPaymentGateway`; ikat di provider — `modules/Platform/app/Infrastructure/Billing/PaymentGateway.php`, `ManualTransferGateway.php` (baru), `modules/Platform/app/Infrastructure/Providers/PlatformServiceProvider.php`
- [x] `PaymentOutcome` (baru) dan `SubscriptionManager::settle(Payment, PaymentOutcome)`: transaksi, `lockForUpdate` pada invoice, no-op bila sudah `paid`, tolak nominal tak pas, periode dihitung saat bayar, ambil plan/siklus dari snapshot invoice; hapus `payInvoice`
- [x] `InvoiceIssuer`: satu invoice `unpaid` per langganan (pakai ulang bila plan+siklus sama, void bila beda), kolom `kind`, jatuh tempo perpanjangan = akhir periode (aktivasi = terbit + 7 hari)
- [x] Console: ganti aksi "Tandai lunas" dengan form konfirmasi (metode, referensi wajib untuk transfer, tanggal bayar tidak di masa depan, catatan); route `billing.invoices.confirm`; regenerasi Wayfinder — `modules/Platform/app/Http/Controllers/InvoiceController.php`, `modules/Platform/routes/web.php`, `modules/Platform/resources/js/Pages/Platform/Billing/Invoices.tsx`
- [x] Test double: `FakePaymentGateway` dengan hasil yang bisa diatur — `modules/Platform/tests/Support/FakePaymentGateway.php` (baru)
- [x] Tes: konfirmasi dua kali memperpanjang sekali; nominal salah ditolak; `external_id` ganda ditolak; failed/expired tidak mengubah invoice; satu invoice unpaid per langganan; periode lanjut dari akhir periode bila aktif; form konfirmasi mencatat pengonfirmasi — `modules/Platform/tests/Feature/BillingSubscriptionTest.php` (sesuaikan), `modules/Platform/tests/Feature/PaymentSettleTest.php` (baru)

**Selesai bila:** provider mengonfirmasi pembayaran lewat form yang tercatat lengkap, dan konfirmasi ulang tidak mengubah apa pun. Bukti: `php artisan test --compact modules/Platform --filter=Payment` dan `--filter=BillingSubscription` → hijau.

### Tahap 3 — Flag modul bersumber dan ganti plan
- [ ] `ModuleFlagManager::enable/disable` menerima `source`; `syncModules()` hanya menyentuh flag `plan`; aksi manual console dan `tenant:modules` menulis `manual` — `modules/Platform/app/Infrastructure/Modules/ModuleFlagManager.php`, `SubscriptionManager.php`, `modules/Platform/app/Http/Controllers/TenantConsoleController.php`, `modules/Platform/app/Infrastructure/Commands/TenantModulesCommand.php`
- [ ] `requestPlanChange(Subscription, planKey)`: masa trial langsung; upgrade → void invoice unpaid usang + `InvoiceIssuer::issueUpgrade` (prorata: selisih harga siklus sama × sisa hari ÷ hari periode, dibulatkan ke atas); downgrade → `scheduled_plan_id`; `cancelScheduledChange()`; `changeCycle` void invoice usang
- [ ] `settle()` untuk invoice `upgrade`: ganti plan dan modul tanpa menggeser periode; invoice perpanjangan memakai `scheduled_plan_id` lalu mengosongkannya
- [ ] Console: `changePlan` tetap sebagai penggantian langsung tanpa biaya dengan label jelas — `modules/Platform/app/Http/Controllers/TenantSubscriptionController.php`, `modules/Platform/resources/js/Pages/Platform/Tenants/Show.tsx`
- [ ] Tes: modul manual tidak tertimpa perpanjangan/upgrade/downgrade; bayar invoice usang tidak membalikkan plan; rumus prorata (termasuk pembulatan); downgrade terjadwal berlaku saat perpanjangan; batal jadwal; trial ganti plan langsung — `modules/Platform/tests/Feature/PlanChangeTest.php` (baru), `ModuleFlagTest.php`

**Selesai bila:** upgrade dan downgrade berperilaku sesuai keputusan dan modul tambahan manual bertahan. Bukti: `php artisan test --compact modules/Platform --filter=PlanChange` dan `--filter=ModuleFlag` → hijau.

### Tahap 4 — Siklus hidup dan suspend
- [ ] `Subscription`: `graceEndsAt()`, `accessEndsAt()` (trial/periode + tenggang; `cancelled` tanpa tenggang), dan `displayState` untuk `cancelled` yang masih punya akses — `modules/Platform/app/Domain/Models/Subscription.php`
- [ ] `TenantLifecycle::suspend(Tenant, reason)` menyimpan `suspended_reason`; `activateIfBillingSuspended()` dipanggil `settle()` dan `extendTrial` (hanya alasan `billing`) — `modules/Platform/app/Infrastructure/Tenancy/TenantLifecycle.php`, `TenantSuspendCommand.php` (alasan `manual`)
- [ ] `cancel()` = tidak diperpanjang (akses sampai akhir periode) dan `extendTrial` boleh dari trial, tenggang, atau suspend `billing` — `SubscriptionManager.php`
- [ ] `TenantSuspendedException` (baru) dilempar `ResolveTenant`; renderer menghasilkan view Blade `Platform::tenant-suspended` (403, `Cache-Control: no-store`, `X-Robots-Tag: noindex`; JSON → 403 JSON netral); kontak dari `config('billing.issuer')` — `modules/Platform/app/Http/Middleware/ResolveTenant.php`, `modules/Platform/app/Domain/Exceptions/TenantSuspendedException.php` (baru), `modules/Platform/mail/tenant-suspended.blade.php` (baru)
- [ ] Spike pelepasan pekerjaan antrean sekolah yang disuspend: event `JobProcessing` di `TenantQueueContext` tidak bisa membatalkan job; pilih titik pengiriman (mis. guard di `modules/Platform/app/Infrastructure/Whatsapp/DefaultWhatsappChannel.php::sendText` dengan hasil `skipped`) dan catat pilihan di Log keputusan. **Tanya dulu** bila perlu mengubah DTO publik `WhatsappSendResult` atau kode Core
- [ ] `billing_exempt`: aksi di console (route + tombol dengan `<Can>`), dipakai penegak dan `BillingSummary` (tidak dihitung di MRR) — `TenantConsoleController.php`, `Tenants/Show.tsx`, `BillingSummary.php`
- [ ] Tes: sekolah suspend melihat halaman netral tanpa data tagihan (HTML dan JSON); suspend `manual` tidak dibuka pembayaran; suspend `billing` dibuka `settle()` dan `extendTrial`; `cancelled` punya akses sampai akhir periode; sekolah `billing_exempt` tidak dihitung MRR; pekerjaan WhatsApp tidak keluar saat disuspend — `modules/Platform/tests/Feature/TenantLifecycleBillingTest.php` (baru), `TenantResolutionTest.php`

**Selesai bila:** sekolah yang disuspend melihat halaman netral dan terbuka kembali otomatis hanya bila alasannya `billing`. Bukti: `php artisan test --compact modules/Platform --filter=TenantLifecycleBilling` → hijau.

### Tahap 5 — Dokumen invoice dan pemberitahuan
- [ ] `composer show` untuk memeriksa kompatibilitas, lalu `composer require barryvdh/laravel-dompdf` (disetujui) — `composer.json`, `composer.lock`
- [ ] Migrasi `billing_notices` dan model/factory (`tenant_id`, `invoice_id` nullable, `kind`, `channel`, `recipient`, `status`, `anchor_date`, `sent_at`; unik per langganan+`kind`+`anchor_date` untuk pesan terjadwal) — `modules/Platform/database/migrations/0010_01_01_000004_create_billing_notices_table.php` (baru), `modules/Platform/app/Domain/Models/BillingNotice.php` (baru)
- [ ] `InvoiceDocument` (render PDF dari invoice + `config('billing.issuer')`; satu dokumen, stempel status) dan view PDF — `modules/Platform/app/Infrastructure/Billing/InvoiceDocument.php` (baru), `modules/Platform/mail/invoice-pdf.blade.php` (baru)
- [ ] Enam Mailable teks + view: invoice terbit, pengingat jatuh tempo, terlambat/tenggang, pembayaran diterima, akses dihentikan, akses dibuka kembali; lampiran PDF pada "invoice terbit" dan "pembayaran diterima" — `modules/Platform/app/Infrastructure/Mail/` (baru), `modules/Platform/mail/` (baru); contoh pola: `ApplicationApprovedMail.php`
- [ ] `BillingNotifier` (baru): menulis `billing_notices`, mengantrekan setelah transaksi (`DB::afterCommit`), mencatat gagal bila `billing_email` kosong; dipanggil dari `InvoiceIssuer` (terbit) dan `settle()` (diterima, dibuka kembali) — `modules/Platform/app/Infrastructure/Billing/BillingNotifier.php`
- [ ] Kontak tagihan: isi saat persetujuan aplikasi, backfill dari aplikasi terakhir, ubah di console — `modules/Platform/app/Infrastructure/Onboarding/DefaultTenantApplications.php`, migrasi backfill, `TenantConsoleController.php`, `Tenants/Show.tsx`
- [ ] Penomoran invoice dikunci (transaksi + kunci atau ulang saat bentrok unik) — `InvoiceIssuer.php`
- [ ] Console: unduh PDF (`billing.invoices.pdf`), kirim ulang (`billing.invoices.resend`), riwayat pemberitahuan di `Invoices.tsx`
- [ ] Tes: `Mail::fake` untuk tiap jenis; PDF menghasilkan berkas `%PDF` dengan nomor dan nominal; tanpa `billing_email` → notice `failed` tanpa error; nomor invoice tidak ganda saat penerbitan beruntun — `modules/Platform/tests/Feature/BillingNoticesTest.php` (baru)

**Selesai bila:** invoice terbit mengirim email ber-PDF ke kontak tagihan, provider dapat mengunduh dan mengirim ulang, dan riwayat tercatat. Bukti: `php artisan test --compact modules/Platform --filter=BillingNotices` → hijau.

### Tahap 6 — `billing:daily`
- [ ] Perintah dengan opsi `--only`, `--date`, `--dry-run`, `--force`; daftarkan di `registerCommands()` — `modules/Platform/app/Infrastructure/Commands/BillingDailyCommand.php` (baru), `PlatformServiceProvider.php`
- [ ] Empat langkah terpisah, tiap langkah try/catch dan log: terbit perpanjangan H-14 (aktif, bukan `cancelled`, tanpa invoice unpaid, bukan `billing_exempt`); pengingat (trial H-3; jatuh tempo H-7/H-1; terlambat H+1/H+5, hanya dalam jendela, tanpa pesan basi); suspend setelah tenggang (alasan `billing`, lewati `billing_exempt`); kedaluwarsakan Payment gateway pending — `modules/Platform/app/Infrastructure/Billing/Daily/` (baru)
- [ ] Pengaman: suspend dibatalkan bila lebih dari `suspend_cap` (5 sekolah atau 20%) tanpa `--force`; `--dry-run` hanya mencantumkan
- [ ] Penanda cron: simpan `billing.last_run_at` lewat `ProviderSetting::write`; halaman Billing console menampilkan "Terakhir dijalankan" dan peringatan bila lebih dari `cron_stale_days` — `modules/Platform/app/Domain/Models/ProviderSetting.php`, `modules/Platform/app/Http/Controllers/BillingController.php`, `modules/Platform/resources/js/Pages/Platform/Billing/Index.tsx`
- [ ] Dokumentasi cron: baris crontab harian dan baris tabel — `docs/config/cron.md`
- [ ] Tes dengan `travelTo`/`--date`: hari-14 menerbitkan sekali (jalan ulang tak menggandakan); jendela pengingat dan lewati yang basi; suspend setelah tenggang, bukan sebelum; exempt dilewati; batas massal berhenti dan `--force` melanjutkan; dry-run tanpa efek; satu langkah gagal tak menghentikan yang lain — `modules/Platform/tests/Feature/BillingDailyCommandTest.php` (baru)

**Selesai bila:** menjalankan `billing:daily` dua kali pada hari yang sama menghasilkan efek yang sama dengan sekali, dan console menunjukkan waktu jalan terakhir. Bukti: `php artisan test --compact modules/Platform --filter=BillingDaily` → hijau; `php artisan billing:daily --dry-run` → daftar rencana tanpa perubahan data.

### Tahap 7 — Pemakaian dan batas plan
- [x] Kontrak `UsageMeters` (daftar: key, label, unit, penghitung; sembunyikan meter modul nonaktif), `TenantUsage::forTenant($id)`, DTO `UsageLine` (state `ok`/`near` ≥ 90%/`over`) beserta implementasi internal dan ikatan — `modules/Platform/app/Contracts/UsageMeters.php` (baru), `modules/Platform/app/Contracts/TenantUsage.php` (baru), `modules/Platform/app/Contracts/DTOs/UsageLine.php` (baru), `modules/Platform/app/Infrastructure/Usage/` (baru), `PlatformServiceProvider.php`
- [x] Meter `storage` milik Platform: jumlah byte berkas di `tenants/{id}` pada disk privat, di-cache 15 menit lewat `TenantCache` — `modules/Platform/app/Infrastructure/Usage/StorageMeter.php` (baru)
- [x] Core mendaftarkan `students`; Identity mendaftarkan `staff_accounts` — `CoreServiceProvider.php`, `IdentityServiceProvider.php`
- [x] `isOverLimit(key)` dan `remaining(key)` di `TenantUsage` (tidak dipakai untuk memblokir)
- [x] Console: pemakaian di halaman detail tenant dan tanda "melebihi batas" di daftar — `TenantConsoleController.php`, `Tenants/Index.tsx`, `Tenants/Show.tsx`
- [x] Tes: semua siswa dihitung apa pun statusnya; akun staf mengecualikan `siswa` dan akun nonaktif, mencakup yang diundang; meter modul nonaktif tersembunyi; batas kosong = tanpa batas; isolasi antar tenant; cache penyimpanan — `modules/Platform/tests/Feature/TenantUsageTest.php` (baru), tes meter di `modules/Core/tests/Feature/` dan `modules/Identity/tests/Feature/`

**Selesai bila:** console menampilkan pemakaian per sekolah dan menandai yang melebihi batas, tanpa memblokir apa pun. Bukti: `php artisan test --compact modules/Platform --filter=TenantUsage` → hijau; `composer deptrac` → tanpa pelanggaran.

### Tahap 8 — Sisi sekolah
- [ ] Kontrak `TenantBilling` + DTO + `BillingActionRefusedException` dan implementasi `DefaultTenantBilling`, selalu menyaring `tenant_id` dari `TenantContext` — `modules/Platform/app/Contracts/TenantBilling.php` (baru), `modules/Platform/app/Contracts/DTOs/` (baru), `modules/Platform/app/Infrastructure/Billing/DefaultTenantBilling.php` (baru)
- [ ] `subscribe` (plan publik; hanya `trial`/`trial_expired`/`cancelled`), `startPayment` (memakai ulang satu Payment `pending` per invoice), `reportTransfer` (tanggal, bank, nama pengirim, referensi; status tetap `pending`; terlihat di form konfirmasi provider), `changePlan`, `cancelScheduledChange`, `changeCycle`, `invoicePdf`
- [ ] Izin `platform.billing.view` dan `platform.billing.pay` didaftarkan lewat `PermissionRegistry`; tambahkan ke `admin-sekolah` — `PlatformServiceProvider.php`, `modules/Identity/config/roles.php`
- [ ] Controller dan rute sekolah `school.billing.*` — `modules/Platform/app/Http/Controllers/School/BillingController.php` (baru), `modules/Platform/routes/web.php`
- [ ] `ShareTenantContext`: prop `billing` opsional (`Inertia::optional`), hanya untuk pemegang `view`, berisi data dan URL aksi; periksa API Inertia v3 dengan `search-docs` — `modules/Platform/app/Http/Middleware/ShareTenantContext.php`
- [ ] `AccountMenu.tsx`: ganti mockup dengan data `billing`, partial reload saat dialog dibuka, menu hanya untuk pemegang `view` (`useCan`), aksi sebagai langkah di dalam dialog; keadaan `exempt`/`none` hanya keterangan — `modules/Shared/resources/js/components/AccountMenu.tsx`, `TenantShell.tsx`
- [ ] Tes: tiap fungsi kontrak; sekolah A tidak bisa melihat/membayar invoice sekolah B (nomor invoice tetangga → ditolak); aksi ditolak pada status yang salah; izin (guru tidak melihat); prop `billing` hanya dimuat saat diminta; PDF hanya milik tenant sendiri — `modules/Platform/tests/Feature/TenantBillingTest.php` (baru), `ShareTenantContextTest.php`
- [ ] Verifikasi UI di browser: panel Paket & Langganan dan Tagihan & Invoice tampil dengan data sekolah uji — `tests/Browser/` bila ada pola serupa, atau manual (lihat Verifikasi end-to-end)

**Selesai bila:** admin sekolah melihat langganan, pemakaian, dan invoice nyata di `AccountMenu`, dan dapat berlangganan, memulai bayar, melapor transfer, serta mengunduh PDF; sekolah lain tidak bisa mengaksesnya. Bukti: `php artisan test --compact modules/Platform --filter=TenantBilling` → hijau; `npm run types:check` → tanpa galat.

### Tahap 9 — Dokumentasi dan penutupan
- [ ] Perbarui bagian billing di `modules/Platform/CONTRACT.md` (tabel baru, kontrak baru, config, hapus keterangan "always-true stub", tabel asumsi master data); Core dan Identity (meter, izin) — `modules/Core/CONTRACT.md`, `modules/Identity/CONTRACT.md`
- [ ] Perbarui `docs/architecture/modular-monolith.md` § Provider-console billing, `docs/config/cron.md`, dan tambahkan Fase 17 ke `docs/ai/plan/README.md`; selaraskan salinan lokal `AGENTS.md` (tidak dilacak git) bila ada perubahan permukaan
- [ ] Jalankan seluruh pemeriksaan: `vendor/bin/pint --dirty --format agent`, `composer deptrac`, `vendor/bin/pest`, `composer types:check`, `npm run types:check`, `npm run check`
- [ ] Catatan rilis: `php artisan migrate`, `php artisan roles:sync`, tambah baris cron `billing:daily`, isi `BILLING_ISSUER_*` di `.env`, isi batas siswa/penyimpanan dan kontak tagihan di console

**Selesai bila:** semua pemeriksaan hijau dan dokumentasi sesuai kode. Bukti: `composer test` → lulus.

## Batas tindakan
**Selalu**
- Baca `modules/Platform/CONTRACT.md` dan skill `modular-monolith` sebelum menyentuh billing; jalankan `vendor/bin/pint --dirty --format agent` dan tes modul yang disentuh sebelum menandai tahap selesai.
- Saring `tenant_id` eksplisit pada setiap query `invoices`/`subscriptions`/`payments`/`billing_notices` dari jalur sekolah.
- Tes pembayaran dan HTTP memakai `Http::fake` + `Http::preventStrayRequests`; tes antrean mendaftarkan ulang `app(TenantQueueContext::class)->register()`.
- Perbarui tabel status dan Log keputusan segera saat tahap mulai atau selesai.

**Tanya dulu**
- Mengubah DTO atau signature kontrak publik yang sudah ada (mis. `WhatsappSendResult`, `TenantApplications`).
- Menambah dependensi selain pustaka PDF yang disetujui, atau bila pustaka itu tidak kompatibel.
- Menjalankan migrasi pada data nyata, atau mengubah isi plan yang sudah ada di basis data.
- Mengubah keputusan terkunci di tabel Keputusan, atau menarik item "Di luar cakupan" ke dalam fase ini.
- Mengerjakan tahap secara paralel dengan beberapa agen.

**Jangan**
- Menghapus atau melemahkan tes yang ada tanpa persetujuan (menyesuaikan tes `payInvoice`/gateway lama dicatat di Log keputusan).
- Menambah FK selain `tenant_id`, atau mengimpor Core/Identity dari Platform, atau modul mana pun dari Shared.
- Menampilkan nominal, nomor invoice, rekening, atau alasan suspend pada halaman "Akses dihentikan".
- Memblokir penambahan siswa/akun berdasarkan batas plan (penegakan di luar cakupan).
- Memanggil gateway pembayaran atau WhatsApp sungguhan dari tes atau agen; menjalankan `git push` tanpa diminta.

## Pengujian
- **Tahap 1:** migrasi data (`max_users` → `limits`), zona `BillingClock`, visibilitas plan, form plan.
- **Tahap 2:** idempotensi `settle()`, nominal salah, `external_id` ganda, satu invoice unpaid, periode, pencatatan pengonfirmasi.
- **Tahap 3:** flag manual bertahan, plan tidak terbalik, prorata dan pembulatan, downgrade terjadwal, trial.
- **Tahap 4:** halaman netral (HTML dan JSON), buka kembali hanya alasan `billing`, `cancelled`, `billing_exempt`, pekerjaan antrean terhenti.
- **Tahap 5:** email tiap jenis, PDF valid, kontak kosong, nomor invoice tak ganda.
- **Tahap 6:** idempotensi harian, jendela pengingat, tenggang, pengaman massal, dry-run, isolasi kegagalan langkah.
- **Tahap 7:** definisi tiap meter, modul nonaktif, isolasi tenant, cache.
- **Tahap 8:** tiap fungsi kontrak, isolasi antar tenant, status salah, izin, prop opsional.
- **Tahap 9:** `composer test` penuh, termasuk arch test dan Deptrac.

## Verifikasi end-to-end
1. `php artisan migrate --seed` pada basis data uji → migrasi bersih; plan Starter/Standard/Pro punya batas, PPDB hanya di Pro.
2. Console: setujui aplikasi → sekolah trial; aktifkan → invoice terbit dan email ber-PDF terkirim (antrean); konfirmasi pembayaran lewat form → langganan aktif, kuitansi terkirim; konfirmasi kedua → tidak ada perubahan.
3. `php artisan billing:daily --date=<H-14 sebelum akhir periode> --dry-run` lalu tanpa `--dry-run` → invoice perpanjangan terbit sekali; ulangi → tidak ada duplikat.
4. `php artisan billing:daily --date=<akhir periode + 8 hari>` → sekolah disuspend; buka `/{kode-sekolah}/login` → halaman netral 403 tanpa data tagihan; konfirmasi pembayaran → sekolah terbuka kembali otomatis.
5. Login sebagai admin sekolah → menu avatar › Paket & Langganan dan Tagihan & Invoice menampilkan data nyata (bukan mockup); lakukan Bayar → instruksi tampil; lapor transfer → provider melihatnya di form konfirmasi.
6. Login sebagai guru → kedua menu billing tidak muncul.

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-08 — Plan ditulis dari peta wayfinder #4 (tiket #5–#13) dan riset pasar — semua keputusan produk sudah dikunci; tidak ada penyimpangan.
- 2026-10-08 — `BillingClock` ditaruh di `modules/Platform/app/Domain/Support/` (bukan `Infrastructure/Billing/`) — model Domain (`Subscription`, `Invoice`) memanggilnya, dan Domain tidak seharusnya bergantung pada Infrastructure; preseden: `Attendance/Domain/Support/ScanWindow`.
- 2026-10-08 — `BillingClock::today()` mengembalikan tanggal zona billing sebagai tengah malam zona aplikasi (UTC), bentuk yang sama dengan atribut ber-cast `date` — agar membandingkan tanggal sama tidak bergeser 7 jam.
- 2026-10-08 — Peran seeder: Pro memuat `ppdb` (keputusan #13), jadi `AttendanceForEverySchoolTest::lists Absensi in every seeded plan` diubah dari "semua plan identik" menjadi "semua plan memuat attendance, hanya Pro memuat ppdb" — perubahan sengaja.
- 2026-10-08 — Form plan di console memakai kolom datar `limit_students`, `limit_staff_accounts`, `limit_storage_mb` (bukan array bersarang); controller menyusunnya menjadi `plans.limits` dan membuang yang kosong.
- 2026-10-08 — Pendaftar hanya boleh memilih plan publik: `DefaultTenantApplications::assertPlanSelectable` mendapat `publicOnly` untuk jalur `submit`; provider tetap boleh memilih plan khusus saat menyetujui.
- 2026-10-08 — **Tahap 2:** `PaymentGateway` kini hanya `initiate(Invoice): Payment`; `payments.gateway` berisi `manual_transfer` (atau `fake` di tes) sedangkan `payments.method` berisi `bank_transfer`/`cash`/`other` — tiket #5 memakai "method" untuk kunci gateway, plan punya kedua kolom.
- 2026-10-08 — **Tahap 2:** `InvoiceIssuer::issue()` diganti `issueActivation()` dan `issueRenewal()`; dua `renew()` berturut-turut pada plan dan siklus sama memakai ulang satu invoice, jadi tes penomoran berurutan menyisipkan `changeCycle` di antaranya (tes dimodifikasi sengaja, tidak dihapus).
- 2026-10-08 — **Tahap 2:** `ManualTransferGateway::initiate()` memakai ulang Payment manual `pending` yang terbuka dan menyimpan rekening dari `config('billing.issuer.bank')` di `meta.instructions`. `invoice.paid_at`/`payment.paid_at` = `now()`; `payment.paid_on` = tanggal yang diisi provider (default `BillingClock::today()`).
- 2026-10-08 — **Tahap 2:** tepi `settle()`: Payment `pending` yang diselesaikan pada invoice yang sudah `paid` dikembalikan apa adanya tanpa galat; invoice `void` melempar `BillingException`; hasil `failed`/`expired` yang terlambat tidak membatalkan Payment `paid`; mengonfirmasi invoice yang bukan `unpaid` lewat console memberi galat form `billing`.
- 2026-10-08 — **Tahap 2:** `Tenants/Show.tsx` ikut diubah (impor aksi `pay` yang dihapus dan tombol "Tandai lunas" diganti tautan ke halaman invoice terfilter) — di luar daftar tugas tetapi build gagal tanpanya.
- 2026-10-08 — **Tahap 7:** meter `storage` melaporkan MB bulat ke atas (unit `MB`, kunci batas `storage_mb`); byte mentah di-cache 15 menit. `UsageMeters::register(module, key, label, unit, counter, ?limitKey)` menerima kunci batas opsional. Meter milik `platform` tidak dicek terhadap modul aktif (`platform` bukan modul terdaftar). `near` = pakai ≥ 90% batas (termasuk tepat di batas), `over` = pakai > batas; `remaining` minimal 0 dan null tanpa batas.
- 2026-10-08 — **Tahap 7:** akun staf mengikuti resolusi #7: pengguna aktif dikecualikan hanya bila `siswa` satu-satunya perannya; pengguna dengan `siswa` plus peran lain, atau tanpa peran, dihitung staf. Dihitung dengan `whereDoesntHave('roles', ...)` karena scope `withoutRole` Spatie melempar galat bila peran tidak ada.
- 2026-10-08 — **Tahap 7:** daftar tenant menghitung pemakaian per tenant per halaman (15); hanya penyimpanan yang di-cache. Tes Platform memakai meter palsu (bukan impor Core) agar Platform tidak bergantung pada Core.

### Temuan
- Sebelum menulis: `payInvoice` mengembalikan plan lama (jebakan 1) dan `syncModules()` menimpa modul manual (jebakan 2); keduanya ditutup di Tahap 2–3.
- Event `JobProcessing` tidak dapat membatalkan job; mekanisme pelepasan pekerjaan sekolah yang disuspend ditentukan lewat spike di Tahap 4.
- `composer deptrac` sudah gagal sebelum fase ini: `App\Console\Commands\DemoSeedCommand` bergantung pada `Database\Seeders\DemoSchoolSeeder` (1 pelanggaran, tidak terkait billing; terbukti dengan `git stash`). Tidak diperbaiki di fase ini; Tahap 9 jangan menganggapnya regresi.
- `npm run check:fix` memformat ulang berkas di luar yang disentuh (`Attendance/Monthly.tsx`, `Core/Integration/Whatsapp/Index.tsx`); dikembalikan dengan `git checkout`. Gunakan pemeriksaan tanpa `--fix` atau periksa `git status` sesudahnya.
- Form plan di console kini punya sekitar 11 kolom di dalam modal (batas aturan UX ≈ 10): ditoleransi karena semuanya satu entitas master data; pertimbangkan memecahnya bila ditambah lagi.
- Agen dengan `isolation: "worktree"` memulai dari commit lama (`88f33c0`, bukan HEAD branch fitur) — kedua agen harus `git reset --hard` ke commit Tahap 1 sebelum bekerja. Cek basis (`git merge-base --is-ancestor`) sebelum menggabung.
- Worktree tidak membawa berkas yang di-ignore: `vendor/` (jangan junction — autoloader memuat kode repo utama), `.env`, `AGENTS.md`, dan `public/build`; tes yang merender halaman Inertia butuh `public/build/manifest.json` (salin atau `npm run build`). `npm install` menulis ulang `package-lock.json`: kembalikan sebelum commit.
- Berkas Wayfinder hasil generate (`resources/js/actions`, `resources/js/routes`) di-ignore git: jalankan `php artisan wayfinder:generate` setelah menambah rute, sebelum `npm run types:check`.
- `pint --test` pada pohon gabungan menandai satu berkas di luar fase ini: `modules/Attendance/app/Http/Controllers/MyScheduleController.php` (impor tak terpakai) — sudah ada sebelumnya, tidak diperbaiki.
- Modul Attendance dan Ppdb belum dijalankan setelah Tahap 1, 2, dan 7 (hanya Platform, Core, Identity, arsitektur).

### Hasil akhir
Diisi saat semua tahap selesai.
