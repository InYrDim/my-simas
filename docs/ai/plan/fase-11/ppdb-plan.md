# PPDB dari data nyata: akun calon siswa yang bergabung ke satu sekolah, verifikasi, seleksi per jalur, pengumuman, dan daftar ulang menjadi siswa

> **Status dokumen:** Selesai
> **Dibuat:** 2026-10-02 · **Diperbarui:** 2026-10-03 · **Branch:** `feat/ppdb`

## Context
Modul **Ppdb** adalah modul terakhir yang masih mockup. `modules/Ppdb/app/Http/Controllers/PpdbController.php` mengirim data tetap dari `modules/Ppdb/app/Infrastructure/Mock/PpdbMockData.php` ke tiga halaman (`/ppdb`, `/ppdb/pendaftar`, `/ppdb/seleksi`). Tidak ada tabel, izin, atau kontrak; route hanya di balik `auth` + `module:ppdb`, sehingga menu PPDB ikut tampil untuk siswa dan guru (temuan fase 10).

Fondasinya sudah ada: akun siswa (fase 8), Statistik & Laporan dengan registry (fase 7; kunci `ppdb-applicants` dan `ppdb-result` sudah diumumkan di `modules/Core/config/insight.php`), dan pola modul fitur berbasis data dari Absensi (fase 10, `docs/ai/plan/fase-10/attendance-plan.md`).

Yang belum ada: (1) cara bagi modul fitur untuk **membuat siswa** di Core — `modules/Ppdb/CONTRACT.md` sudah menetapkan bahwa itu tugas Core lewat kontrak milik Core; (2) **akun untuk calon siswa**. Pengguna sekolah hidup di dalam satu sekolah (`users.tenant_id` wajib, tanpa registrasi publik — `modules/Identity/CONTRACT.md`), padahal calon siswa belum menjadi bagian sekolah mana pun. Platform sudah punya pola akun pusat untuk kasus serupa: `applicants` (calon sekolah), guard `applicant`, `/daftar-sekolah` dan `/pemohon/*` (`modules/Platform/CONTRACT.md` § Applicant accounts, `modules/Platform/routes/web.php`). Fase ini membuat akun pusat sejenis untuk calon siswa.

Gambaran alurnya, seperti "gabung kelas" di Classroom tetapi hanya satu sekolah: calon siswa membuat akun dan login → memasukkan **kode sekolah** (kode yang sama dengan yang diketik di form login sekolah) atau membuka tautan dari sekolah → bergabung ke sekolah itu → mengisi formulir pendaftaran → memantau status dan hasil di halamannya sendiri.

## Goal
Setelah ini, admin sekolah mengatur periode PPDB, gelombang, serta jalur dan kuotanya, lalu membagikan kode/tautan PPDB sekolah; calon siswa membuat akun, bergabung ke sekolah itu, mengisi formulir, dan melihat status verifikasi serta hasil seleksi di halaman akunnya; panitia juga bisa menginput pendaftar yang datang langsung, memverifikasi, dan admin mengisi nilai, menetapkan Diterima/Cadangan/Tidak diterima sesuai kuota per jalur, lalu mengumumkan hasil; panitia mencatat daftar ulang sehingga pendaftar menjadi siswa di Master Data. Ringkasan, laporan, dan angka PPDB berasal dari database, dan banner "Tampilan contoh" hilang.

## Batasan masalah

**Dalam cakupan**
- Kontrak Core baru untuk menerima siswa baru (`StudentAdmission`).
- Akun calon siswa (pusat, di luar sekolah): daftar, verifikasi email, masuk/keluar, lupa kata sandi; guard `ppdb`.
- Bergabung ke satu sekolah dengan kode/tautan; keluar dari sekolah selama belum mengirim formulir.
- Formulir pendaftaran oleh calon siswa; halaman akun dengan status verifikasi, perbaikan data saat "Perlu perbaikan", dan hasil seleksi setelah diumumkan.
- Pengaturan PPDB: satu periode aktif per sekolah, gelombang, jalur + kuota; kode dan tautan sekolah.
- Pendaftar untuk panitia: input langsung (tanpa akun), daftar dengan pencarian dan filter, detail, ubah, verifikasi, pembatalan pendaftaran.
- Seleksi: satu nilai per pendaftar, peringkat per jalur, keputusan dengan batas kuota per jalur, pengumuman hasil.
- Daftar ulang: pendaftar diterima → siswa Core (tanpa kelas), `student_id` disimpan.
- Izin `ppdb.*`, gate route dan menu; laporan `ppdb-applicants` dan `ppdb-result`; angka dan panel PPDB; seeder contoh; tes Pest (feature, browser), spec Playwright; pembersihan mock; dokumentasi.

**Di luar cakupan** (keputusan user: dikerjakan setelah inti PPDB jadi; untuk sekarang berupa fungsi tiruan yang mengembalikan `true`)
- Pemberitahuan WA hasil seleksi (`ppdb.result`) — entri "Segera hadir" di `modules/Core/config/notices.php` tetap. Butuh kontrak Core baru nanti karena `GuardianNotifier` hanya mengenal `student_id`.
- Unggah berkas — verifikasi berkas untuk sekarang adalah keputusan panitia di halaman detail. Setelah ada akun, fitur ini bisa dibangun di portal calon siswa.
- Daftar sekolah/pencarian sekolah di portal — keputusan user: sekolah ditemukan lewat kode/tautan saja.
- Halaman cek hasil **publik** (tanpa login) — tidak perlu lagi: status dan hasil ada di halaman akun.
- Mengganti sekolah setelah formulir dikirim — keputusan user: terkunci; salah pilih diselesaikan panitia lewat pembatalan pendaftaran.
- PPDB di semua paket langganan — tidak dipilih user; sekolah mendapat modul ini hanya bila provider menyalakannya.
- Penghapusan akun oleh pemiliknya; login dengan nomor HP/WA; login sosial; akun untuk wali selain pendaftar; pembayaran biaya pendaftaran; tes masuk online; lebih dari satu komponen nilai.
- Memilih kelas saat daftar ulang — penempatan tetap di Akademik › Penempatan Siswa; akun login siswa tetap lewat alur fase 8.
- Pengunduran diri setelah diterima dan pembatalan pengumuman — lihat "Tanya dulu".

**Asumsi**
- Siswa aktif tanpa kelas sah di Core (`students.class_id` nullable; `StudentForm.tsx` punya pilihan "Belum ditempatkan"). Tahap 0 memastikan Penempatan Siswa menampilkan siswa tanpa kelas; bila tidak, catat di Temuan dan tanya user.
- Calon siswa memakai email (milik sendiri atau orang tua) sebagai nama pengguna, seperti akun pemohon di Platform. Email verifikasi dikirim lewat antrean (cron per menit di shared hosting: bisa tertunda sampai satu menit).
- Semua tahap dikerjakan berurutan tanpa berhenti, mengikuti keputusan user di fase 5–10. Bila user ingin berhenti per tahap, ubah legenda di "Tahapan & status".
- Sekolah dev `sekolah-a` punya modul `ppdb` (`modules/Platform/database/seeders/PlatformDevSeeder.php`); Tahap 0 memeriksa bahwa paket langganannya tidak mematikannya.

## Batasan teknis & keputusan

**Aturan yang mengikat**
- Ppdb hanya memakai `CorePublic`, `IdentityPublic`, `PlatformPublic`, `Shared`; tidak pernah Attendance atau internal modul lain (sumber: `AGENTS.md` § Public surface, `deptrac.php`). Akun pendaftar **tidak** memakai `Applicant` milik Platform (internal) — Ppdb punya model akun sendiri.
- Tanpa FK selain `tenant_id`; tabel pusat `ppdb_accounts` boleh punya FK `tenant_id → tenants`, sisanya kolom polos (sumber: `tests/Architecture/ModularMonolithTest.php`).
- Data tenant lewat Eloquent + `BelongsToTenant`; model tenant baru masuk `deptrac.baseline.yaml`; seeder tanpa `WithoutModelEvents`; tanggal sebagai teks `Y-m-d` pada jam sekolah (`TenantContext::timezone()`) (sumber: `AGENTS.md` § Tenancy traps, `modules/Attendance/CONTRACT.md`).
- `DB::table()` melewati scope tenant — Eloquent saja untuk data tenant (sumber: `AGENTS.md`).
- Rate limit di controller. Halaman akun adalah halaman **pusat** (tanpa tenant), jadi kunci berbasis IP/email aman di sana; permintaan yang menyentuh data sekolah tetap menyertakan tenant id di kuncinya (sumber: komentar di `modules/Platform/routes/web.php`, `AGENTS.md` § Identity).
- Jangan percaya `ResolveTenant` di halaman akun: ia mengadopsi sekolah yang diingat sesi bahkan untuk tamu. Halaman akun selalu masuk ke sekolah secara eksplisit lewat `TenantContext::run($account->tenant_id, …)` (sumber: `modules/Platform/app/Http/Middleware/ResolveTenant.php`).
- Guard memakai middleware sendiri, bukan `auth:ppdb`: redirect tamu aplikasi ini bergantung host dan akan mengirim calon siswa ke login sekolah (sumber: `modules/Platform/app/Http/Middleware/AuthenticateApplicant.php`).
- Nama tabel berawalan `ppdb_`: Platform sudah punya tabel `applicants`.
- UI dasar dari `modules/Shared/resources/js/components/ui`; URL dari Wayfinder (sumber: `.ai/rules/js.md`).
- Aktifkan skill `modular-monolith`, `laravel-best-practices`, `inertia-react-development`, `wayfinder-development`, `writing-tests`, `testing-best-practices` saat menyentuh bagiannya.

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| 2026-10-02 | Pendaftaran oleh panitia untuk pendaftar yang datang langsung (tanpa akun) | user |
| 2026-10-02 | Seleksi: satu nilai per pendaftar, peringkat dan kuota per jalur | user |
| 2026-10-02 | Daftar ulang membuat siswa di Master Data (tanpa kelas) lewat kontrak milik Core | user |
| 2026-10-02 | WA hasil seleksi dan unggah berkas ditunda; fungsinya ditiru dulu dan mengembalikan `true` | user |
| 2026-10-03 | Formulir publik tanpa login **dibatalkan**; calon siswa membuat akun, login, lalu bergabung ke satu sekolah ("seperti join kelas di Classroom, hanya satu sekolah") | user |
| 2026-10-03 | Sekolah ditemukan lewat kode/tautan dari sekolah; tanpa daftar sekolah publik | user |
| 2026-10-03 | Terkunci di sekolah itu sejak formulir dikirim; sebelum itu boleh keluar dan bergabung ke sekolah lain | user |
| 2026-10-03 | "Kode sekolah" = kode yang sudah dipakai di form login sekolah (`tenant id`); tautan = `/calon-siswa/gabung?school=<kode>` yang dibuat `TenantUrl::root()` dan ditampilkan di Pengaturan PPDB | hasil baca kode |
| 2026-10-03 | Akun calon siswa = tabel pusat `ppdb_accounts` milik **modul Ppdb**, bukan Platform: Platform adalah lapisan dasar dan tidak boleh mengenal bisnis penerimaan siswa. Ini tabel pusat pertama di modul fitur — perlu persetujuan user saat plan disetujui | hasil desain |
| 2026-10-03 | Akun hanya menyimpan nama, email, kata sandi, dan sekolah yang dimasuki; data pendaftar (tanggal lahir, alamat, dsb.) hanya ada di tabel sekolah | hasil desain |
| 2026-10-03 | Hasil seleksi hanya terlihat di halaman akun setelah `results_published_at` terisi; sebelumnya halaman hanya berkata "belum diumumkan" | hasil desain |
| 2026-10-03 | Saat status "Perlu perbaikan", pemilik akun boleh mengubah datanya; menyimpan mengembalikan status ke "Menunggu verifikasi" | hasil desain |
| 2026-10-03 | Salah pilih sekolah setelah mengirim: panitia/admin membatalkan pendaftaran (`CancelApplication`, hanya sebelum ada keputusan), yang membebaskan akun | hasil desain dari jawaban user |
| 2026-10-02 | Fungsi tiruan mengikuti pola `modules/Platform/app/Infrastructure/Billing/AlwaysSucceedsPaymentGateway.php`: antarmuka + implementasi selalu-`true`, di-bind di provider | hasil baca kode |
| 2026-10-02 | `StudentAdmission` tidak memeriksa izin; pemanggil (Ppdb) menggate dengan `ppdb.applicants.manage` | hasil desain |
| 2026-10-02 | Laporan PPDB untuk satu tahun ajaran = pendaftar yang tanggal daftarnya di dalam rentang tahun ajaran itu | hasil desain |

## Desain

### Core — permukaan publik baru (`modules/Core/app/Contracts`)
- `StudentAdmission` (baru) — `admit(NewStudent $student): int` (id siswa baru; aktif, tanpa kelas, tanpa akun).
- `DTOs/NewStudent` (baru, `final readonly`) — `name`, `nis`, `gender` (`L`/`P`), `?nisn`, `?birthDate` (`Y-m-d`), `?guardianName`, `?guardianPhone`.
- `Exceptions/StudentAdmissionRefusedException` (baru) — NIS atau NISN sudah dipakai; membawa nama kolom (`nis` / `nisn`) dan pesan.

### Core — internal
- `Infrastructure/Admission/DefaultStudentAdmission.php` (baru) di atas `modules/Core/app/Domain/Actions/SaveStudent.php` (`handle(null, [...])`), memeriksa keunikan NIS/NISN dalam sekolah lebih dulu; di-bind di `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`.
- Tes Core yang memakai `ppdb-applicants` / `ppdb-result` sebagai entri "Segera hadir" pindah ke kunci `schedule` (Tahap 7) — perubahan perilaku yang disengaja, sama seperti fase 10.

### Ppdb — tabel (`modules/Ppdb/database/migrations`, baru, awalan `0009_01_01_00000x_`)
Pusat (tanpa `BelongsToTenant`):
- `ppdb_accounts` — `name`, `email` (unique), `password`, `email_verified_at` (nullable), `remember_token`, `tenant_id` (nullable, FK ke `tenants`: sekolah yang dimasuki), `timestamps`. Pola: `modules/Platform/database/migrations/0005_01_01_000000_create_applicants_table.php`.

Tenant (semua `tenantId()`):
- `ppdb_periods` — `name`, `entry_year` (smallint), `status` (`draft` | `active` | `closed`), `results_published_at` (nullable). Satu `active` per sekolah (dijaga di Action).
- `ppdb_waves` — `period_id`, `name`, `opens_on`, `closes_on` (teks `Y-m-d`); status Akan datang/Dibuka/Ditutup dihitung dari hari sekolah, tidak disimpan.
- `ppdb_paths` — `period_id`, `name`, `quota`, `sort_order`. Periode baru diisi Zonasi, Prestasi, Afirmasi, Mutasi (kuota 0).
- `ppdb_applicants` — `period_id`, `wave_id`, `path_id`, `account_id` (nullable: pendaftar input panitia tidak punya akun; kolom polos), `number`, `name`, `gender`, `birth_place` (nullable), `birth_date`, `nisn` (nullable), `origin_school`, `address` (nullable), `guardian_name`, `guardian_phone`, `source` (`online` | `staff`), `registered_on` (`Y-m-d`), `status` (`submitted` | `revision` | `verified`), `verification_note` (nullable), `score` (decimal 5,2, nullable), `decision` (`pending` | `accepted` | `waitlist` | `rejected`), `enrolled_at` (nullable), `student_id` (nullable), `recorded_by` (nullable). Unique `(tenant_id, number)` dan `(tenant_id, account_id)`; indeks `(tenant_id, period_id, path_id)` dan `(tenant_id, period_id, status)`.

### Ppdb — internal (`modules/Ppdb/app`)
- `Domain/Enums/{PeriodStatus,ApplicantStatus,Decision,ApplicantSource}.php`; `Domain/Models/{PpdbAccount,AdmissionPeriod,AdmissionWave,AdmissionPath,Applicant}.php` + factory. `PpdbAccount` mengikuti `modules/Platform/app/Domain/Models/Applicant.php` (verifikasi email, hash verifikasi). `AdmissionPeriod::active()`.
- `Domain/Support/SchoolDay.php` — hari sekolah di atas `TenantContext::timezone()`. `SchoolClock` milik Attendance tidak boleh diimpor; duplikasi kecil ini dicatat di Temuan sebagai calon helper `PlatformPublic`.
- Akun (`Domain/Actions/Account/`): `RegisterAccount`, `JoinSchool`, `LeaveSchool`.
  - `JoinSchool` — kode dinormalkan (huruf kecil, dipangkas) dan dicek bentuknya; `TenantDirectory::findMany([$kode])` memberi `TenantData`; sekolah ditolak bila tidak ada, ditangguhkan, `TenantModules::isEnabled('ppdb', $id)` salah, atau tidak punya periode aktif (dicek di dalam `TenantContext::run`). **Semua** penolakan dijawab dengan pesan yang sama ("Kode sekolah tidak dikenali atau PPDB sekolah itu belum dibuka.") supaya form tidak bisa dipakai menebak sekolah. Akun yang sudah mengirim formulir ditolak.
  - `LeaveSchool` — hanya bila tidak ada baris `ppdb_applicants` dengan `account_id` akun itu di sekolahnya; mengosongkan `tenant_id`.
- Pendaftaran (`Domain/Actions/`):
  - `SavePeriod` (mengaktifkan satu periode menutup yang lain; periode baru mendapat jalur bawaan), `SaveWave` (tutup ≥ buka; gelombang satu periode tidak tumpang-tindih), `SavePaths` (nama unik per periode; kuota tidak boleh di bawah jumlah yang sudah diterima).
  - `RegisterApplicant` — dipakai portal dan panitia. Periode aktif; jalur milik periode itu; sumber `online` butuh akun yang `tenant_id`-nya sekolah ini, belum punya pendaftaran, dan gelombang yang sedang dibuka; sumber `staff` boleh memilih gelombang mana pun dan tanpa akun; NISN (bila diisi) unik per periode; nomor `PPDB-{yy}-{0001}` = urutan berikutnya untuk awalan itu di sekolah, diulang bila indeks unik menolak.
  - `UpdateApplicant` — panitia: data diri, jalur terkunci setelah ada keputusan; pemilik akun: hanya saat status `revision`, lalu status kembali `submitted`.
  - `VerifyApplicant` (status + catatan; `Terverifikasi` memanggil `DocumentCheck::complete()`; yang sudah punya keputusan tidak bisa diturunkan).
  - `CancelApplication` — hanya sebelum ada keputusan selain `pending` dan sebelum daftar ulang; menghapus baris dan mengosongkan `tenant_id` akun (jalan di dalam tenant, lalu memperbarui akun pusat).
  - `SaveSelection` — satu jalur: `applicant_id → score, decision`; hanya terverifikasi; jumlah `accepted` ≤ kuota jalur; setelah pengumuman hanya `waitlist → accepted` yang diterima.
  - `PublishResults` — menolak bila masih ada pendaftar terverifikasi berkeputusan `pending`; mengisi `results_published_at`; memanggil `ResultAnnouncer::announce()` per pendaftar setelah transaksi.
  - `EnrollApplicant` — hasil sudah diumumkan, keputusan `accepted`, belum daftar ulang; memanggil `StudentAdmission::admit()` dengan NIS dari panitia; menyimpan `student_id`, `enrolled_at`. `StudentAdmissionRefusedException` → galat validasi pada `nis`.
- Fungsi tiruan (keputusan user): `Domain/Support/DocumentCheck.php` (`complete(Applicant): bool`) dan `Domain/Support/ResultAnnouncer.php` (`announce(Applicant): bool`), implementasi `Infrastructure/Stubs/{AlwaysCompleteDocumentCheck,SilentResultAnnouncer}.php` yang mengembalikan `true`, di-bind di provider.
- `Domain/Queries/{AdmissionFunnel,PathRanking,PortalState}.php` — corong (Mendaftar, Terverifikasi, Diterima, Daftar ulang), peringkat satu jalur (nilai turun, nilai kosong terakhir, lalu nomor pendaftaran), dan keadaan halaman akun (belum bergabung / belum mengisi / terkirim + status / hasil). `PortalState` membuka keputusan hanya bila hasil sudah diumumkan.
- `Domain/Reports/{ApplicantListReport,SelectionResultReport}.php` (key `ppdb-applicants`, `ppdb-result`; izin `ppdb.view`; pola `modules/Attendance/app/Domain/Reports/ClassAttendanceReport.php`) dan `Domain/Statistics/AdmissionStatistics.php` (angka `ppdb-applicants-total`, panel `ppdb-by-path` jenis `bars`, periode aktif).
- Surat: `Infrastructure/Mail/{AccountVerificationMail,AccountPasswordResetMail}.php` (Mailable antrean; tautan bertanda tangan relatif seperti `modules/Platform/app/Infrastructure/Onboarding/ApplicantVerification.php`; tanpa mesin Notification — keputusan terkunci Identity), tampilan di folder mail modul dengan namespace `Ppdb::` (ikuti lokasi `modules/Platform/mail`).
- `Http/Middleware/AuthenticatePpdbAccount.php` — pola `AuthenticateApplicant`; parameter `verified`.
- Controller tipis dan FormRequest per tulis. Panitia: `OverviewController`, `ApplicantController`, `VerificationController`, `SelectionController`, `EnrollmentController`, `SettingsController`. Akun: `Account/{RegisterController,SessionController,VerificationController,PasswordController,PortalController,JoinSchoolController,ApplicationController}`. `PpdbController` dan `PpdbMockData` dihapus di Tahap 7.
- `Infrastructure/Providers/PpdbServiceProvider.php` (ada): tambah `mergeConfigFrom` (`config/permission_labels.php`, baru), `loadMigrationsFrom`, `loadViewsFrom`, izin, binding tiruan, laporan + statistik, menu berizin. Pola: `modules/Attendance/app/Infrastructure/Providers/AttendanceServiceProvider.php`.
- `config/auth.php` (ada, root): guard `ppdb` (provider `ppdb_accounts`, model `PpdbAccount`) — pola guard `applicant` di berkas yang sama.

**Izin** (nama ditambahkan ke `modules/Identity/config/roles.php`; sekolah lama lewat `php artisan roles:sync`)
| Izin | Arti | admin-sekolah | staf-tu | guru | siswa |
| --- | --- | --- | --- | --- | --- |
| `ppdb.view` | ringkasan, daftar dan detail pendaftar, seleksi (baca), laporan | ✓ | ✓ | | |
| `ppdb.applicants.manage` | input, ubah, verifikasi, batalkan pendaftaran, daftar ulang | ✓ | ✓ | | |
| `ppdb.selection.manage` | nilai, keputusan, pengumuman | ✓ | | | |
| `ppdb.settings.manage` | periode, gelombang, jalur dan kuota, kode/tautan sekolah | ✓ | | | |

Akun calon siswa tidak memakai izin: ia bukan pengguna sekolah.

**Menu sekolah**: "PPDB" → Ringkasan (`ppdb.view`), Pendaftar (`ppdb.view`), Seleksi & Pengumuman (`ppdb.view`), Pengaturan (`ppdb.settings.manage`). Guru dan siswa tidak lagi melihat PPDB.

**Route panitia** — `modules/Ppdb/routes/web.php`, `auth` + `module:ppdb`, prefix `ppdb`, nama `ppdb.`
| Method & URL | Nama | Izin |
| --- | --- | --- |
| `GET /` | `overview` (tetap) | `ppdb.view` |
| `GET pendaftar` | `applicants` (tetap) | `ppdb.view`; `?cari=`, `?jalur=`, `?status=`, 20 per halaman |
| `GET pendaftar/tambah`, `POST pendaftar` | `applicants.create`, `.store` | `ppdb.applicants.manage` |
| `GET pendaftar/{applicant}` | `applicants.show` | `ppdb.view` |
| `PUT pendaftar/{applicant}` | `applicants.update` | `ppdb.applicants.manage` |
| `PUT pendaftar/{applicant}/verifikasi` | `applicants.verify` | `ppdb.applicants.manage` |
| `DELETE pendaftar/{applicant}` | `applicants.cancel` | `ppdb.applicants.manage` |
| `POST pendaftar/{applicant}/daftar-ulang` | `applicants.enroll` | `ppdb.applicants.manage` |
| `GET seleksi` | `selection` (tetap) | `ppdb.view`; `?jalur=` |
| `PUT seleksi`, `POST seleksi/umumkan` | `selection.update`, `.publish` | `ppdb.selection.manage` |
| `GET pengaturan`; `POST`/`PUT` periode, gelombang, jalur | `settings*` | `ppdb.settings.manage` |

**Route akun** — halaman **pusat** (tanpa tenant), grup `web`, prefix `calon-siswa`, nama `ppdb.account.`; didaftarkan di berkas route yang sama, sebelum grup panitia
| Method & URL | Nama | Syarat |
| --- | --- | --- |
| `GET daftar`, `POST daftar` | `register`, `register.store` | tamu; throttle `5,10` + honeypot (pola `RegisterController` Platform) |
| `GET masuk`, `POST masuk` | `login`, `login.attempt` | tamu; limiter `ppdb-account:{email}:{ip}`, galat generik |
| `POST keluar` | `logout` | akun |
| `GET lupa-sandi`, `POST lupa-sandi` | `password.request`, `password.email` | tamu; throttle `5,10` |
| `GET/POST atur-ulang/{account}/{hash}` | `password.reset`, `password.update` | `signed:relative` |
| `GET verifikasi/{account}/{hash}` | `verify` | `signed` |
| `GET verifikasi`, `POST verifikasi/kirim-ulang` | `verify.notice`, `verify.resend` | akun; throttle `3,10` |
| `GET /` | `home` | akun terverifikasi; isinya mengikuti `PortalState` |
| `GET gabung`, `POST gabung` | `join`, `join.store` | akun terverifikasi; `?school=` mengisi kolom kode; limiter `ppdb-join:{account}:{ip}` 10 per 10 menit |
| `DELETE gabung` | `join.leave` | akun terverifikasi; ditolak bila formulir sudah dikirim |
| `GET formulir`, `POST formulir`, `PUT formulir` | `form`, `form.store`, `form.update` | akun yang sudah bergabung; `PUT` hanya saat status `revision` |

Tamu yang membuka tautan `…/calon-siswa/gabung?school=<kode>` dialihkan ke masuk/daftar lalu kembali ke tautan itu (intended URL); kodenya ikut terbawa.

**Halaman** (`modules/Ppdb/resources/js/Pages/Ppdb/`; berkas berada di `Pages/Ppdb/`, nama komponen `Ppdb/...`)
- Panitia: `Overview.tsx` (ada; props tetap `period`, `funnel`, `waves` dari database; `EmptyState` + tautan Pengaturan bila belum ada periode aktif; tautan lewat Wayfinder), `Applicants.tsx` (ada; pencarian/filter pindah ke server, paginasi, "Tambah pendaftar"; tombol "Unduh daftar" dihapus karena unduhan ada di Statistik & Laporan), `ApplicantForm.tsx` dan `ApplicantShow.tsx` (baru; detail, ubah, verifikasi, batalkan, daftar ulang dengan kolom NIS), `Selection.tsx` (ada; pilihan jalur, nilai, keputusan lewat `useForm`, kartu Kuota/Diterima/Cadangan per jalur, "Umumkan hasil" dengan `AlertDialog`; hanya-baca bagi yang tanpa `ppdb.selection.manage`), `Settings.tsx` (baru; periode, gelombang, jalur + kuota, kode dan tautan sekolah dengan tombol salin).
- Akun (`Account/`, baru): `Register`, `Login`, `VerifyNotice`, `ForgotPassword`, `SetPassword` dalam kerangka `@shared/components/AuthShell` (pola `modules/Platform/resources/js/Pages/Platform/Applicant/*`); `Home`, `Join`, `Form` dalam `Components/PortalPage.tsx` (baru; judul, nama akun, tombol Keluar — tanpa `TenantShell`). Ramah HP.
- `Components/PpdbPage.tsx` — banner mock dihapus di Tahap 7; `Components/status.ts` dipakai apa adanya.

**Dipakai ulang**
- Platform Contracts: `TenantContext`, `TenantModules`, `TenantDirectory`, `TenantUrl`, `PermissionRegistry`, `TenantNavigation`, `ModuleRegistry`. Core Contracts: `ReportRegistry`, `StatisticsRegistry` + DTO, dan `StudentAdmission` yang baru.
- Frontend: `@shared/components/page-parts` (`StatCard`, `Panel`, `OptionSelect`, `DataTable`, `EmptyState`, `PageHeader`), `@shared/components/ui/*` (`field`, `alert-dialog`, `pagination`, `badge`), `AuthShell`.
- Tes: `school()` di `tests/Pest.php`; `ppdbTenant()` di `modules/Ppdb/tests/Feature/PpdbPagesTest.php` (dipindah ke `Support/helpers.php`, pola `modules/Attendance/tests/Feature/Support/helpers.php`); `schoolMemberSignsIn()` di `tests/Browser/Support/school.php`; `tests/E2E/support/database.ts` untuk e2e.

### Identity / Platform
- Identity: hanya `modules/Identity/config/roles.php`. Platform: tidak berubah (hanya `config/auth.php` di root yang menambah satu guard).

### Alur
1. Admin membuka PPDB › Pengaturan: membuat periode, gelombang, dan kuota tiap jalur; menyalin tautan/kode PPDB sekolah dan membagikannya.
2. Calon siswa membuka tautan → membuat akun → memverifikasi email → masuk. Ia berada di halamannya sendiri, belum terikat sekolah mana pun.
3. Ia memasukkan kode sekolah (atau sudah terisi dari tautan) → bergabung. Salah sekolah? Ia bisa keluar selama belum mengirim formulir.
4. Ia mengisi formulir (jalur, data diri, asal sekolah, wali) dan mengirim; mendapat nomor pendaftaran. Sejak itu terkunci di sekolah ini. Yang datang langsung diinput panitia tanpa akun.
5. Panitia memverifikasi: Terverifikasi, atau Perlu perbaikan dengan catatan — calon siswa melihat catatannya dan memperbaiki data. Salah sekolah setelah mengirim: panitia membatalkan pendaftaran.
6. Admin membuka Seleksi & Pengumuman, memilih jalur, mengisi nilai, menetapkan keputusan (ditolak bila melebihi kuota), lalu "Umumkan hasil". Calon siswa baru melihat hasilnya setelah itu.
7. Pendaftar diterima datang daftar ulang: panitia mengisi NIS → siswa muncul di Warga Sekolah › Siswa, belum berkelas. Admin menempatkannya di Akademik › Penempatan Siswa dan membuat akunnya (alur fase 8).
8. Laporan dan angka PPDB muncul di Statistik & Laporan.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Semua tahap dikerjakan berurutan tanpa berhenti (lihat Asumsi); tiap tahap ditandai ✅ hanya setelah buktinya lulus.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 0 | Fondasi: dokumen plan, branch, kontrak `StudentAdmission` di Core, izin PPDB, gate route dan menu | ✅ | Kontrak `StudentAdmission` + `NewStudent` + `StudentAdmissionRefusedException` dan `DefaultStudentAdmission` di Core; empat izin `ppdb.*` dengan label; semua route PPDB bergate `ppdb.view`; menu PPDB hanya untuk pemegang `ppdb.view`. `StudentAdmissionTest` (5 kasus), `PpdbAccessTest` (21 kasus). Siswa tanpa kelas diberi kelas lewat form siswa, bukan Penempatan Siswa (lihat Temuan). |
| 1 | Pengaturan (periode, gelombang, jalur + kuota, kode/tautan) dan Ringkasan dari database | ✅ | Tabel `ppdb_periods`, `ppdb_waves`, `ppdb_paths`; `SavePeriod` (jalur bawaan, satu periode aktif), `SaveWave` (tidak mundur, tidak tumpang-tindih), `SavePaths`; halaman Pengaturan (kode + tautan dengan tombol salin, periode, gelombang, jalur + kuota) dan Ringkasan dari database; banner contoh kini opsional per halaman. `PpdbSettingsTest` (17 kasus); `modules/Ppdb` 47 tes hijau. Hapus gelombang/jalur ditunda ke Tahap 2 (lihat Log keputusan). |
| 2 | Pendaftar untuk panitia: input, daftar, detail, ubah, verifikasi, pembatalan | ✅ | Tabel `ppdb_applicants`; `RegisterApplicant` (nomor `PPDB-yy-0001`, ulang bila bentrok), `UpdateApplicant`, `VerifyApplicant` (`DocumentCheck` tiruan), `CancelApplication`, `DeleteWave`, `DeletePath`; halaman Pendaftar (cari, filter jalur/status, 20 per halaman), Tambah pendaftar, dan detail dengan ubah/verifikasi/batalkan; Ringkasan menampilkan corong dan pendaftar per gelombang. `ApplicantTest` (28 kasus); `modules/Ppdb` 85 tes hijau; Deptrac 0 pelanggaran. |
| 3 | Akun calon siswa (pusat): daftar, verifikasi email, masuk/keluar, lupa kata sandi | ✅ | Tabel pusat `ppdb_accounts` (FK hanya `tenant_id`), model `PpdbAccount`, guard `ppdb` di `config/auth.php`, `AuthenticatePpdbAccount`, surat verifikasi dan atur ulang (antrean, tautan bertanda tangan relatif), daftar/masuk/keluar/lupa kata sandi/verifikasi, halaman akun sementara. `PpdbAccountTest` (23 kasus); `modules/Ppdb` 108 tes hijau; Deptrac 0 pelanggaran. Platform + Identity + Arsitektur: 453 dari 454 lulus; satu gagal di `ProviderAuthTest` karena lingkungan, gagal juga di pohon bersih (lihat Temuan). |
| 4 | Bergabung ke sekolah, formulir, dan status di halaman akun | ✅ | `JoinSchool` (kode sekolah, satu pesan galat untuk semua penolakan, batas 10 percobaan gagal per 10 menit), `LeaveSchool` (hanya sebelum formulir terkirim), `RegisterApplicant` cabang online (gelombang yang sedang dibuka, satu pendaftaran per akun), `UpdateOwnApplication` (hanya saat "Perlu perbaikan"), `PortalState` (empat keadaan; keputusan tersembunyi sampai diumumkan), `CancelApplication` membebaskan akun. Halaman Home, Join, Form. `JoinSchoolTest` (10 kasus), `PortalApplicationTest` (17 kasus); `modules/Ppdb` 135 tes hijau; Deptrac 0 pelanggaran. |
| 5 | Seleksi per jalur dan pengumuman (hasil tampil di halaman akun) | ✅ | `PathRanking`, `SaveSelection` (semua-atau-tidak-sama-sekali, kuota per jalur, keputusan wajib bernilai, setelah pengumuman hanya cadangan → diterima), `PublishResults`, `ResultAnnouncer` + `SilentResultAnnouncer` tiruan; halaman Seleksi nyata (pilih jalur, nilai, keputusan, kuota, umumkan); `PpdbController` dan `PpdbMockData` dihapus. `SelectionTest` (23 kasus); `modules/Ppdb` + Identity + Arsitektur 318 tes hijau; Deptrac 0 pelanggaran. |
| 6 | Daftar ulang → siswa Core | ✅ | `EnrollApplicant` (setelah pengumuman, hanya yang diterima, sekali; siswa dibuat lewat `StudentAdmission` Core dengan NIS dari panitia, dalam satu transaksi), `EnrollmentController`, panel Daftar ulang dan panel Hasil seleksi di halaman detail pendaftar. `EnrollmentTest` (19 kasus); `modules/Ppdb` + Core + Arsitektur 622 tes hijau; Deptrac 0 pelanggaran. |
| 7 | Laporan, statistik, seeder, tes browser, pembersihan mock, dokumentasi | ✅ | Laporan `ppdb-applicants` dan `ppdb-result`, figur `ppdb-applicants-total` + panel `ppdb-by-path`, `PpdbDemoSeeder`, `tests/Browser/PpdbTest.php` (2 skenario), `tests/E2E/ppdb-account.spec.ts`, banner mock dan data tiruan dihapus, `CONTRACT.md` Ppdb/Core/Identity/Platform, dokumen arsitektur, `AGENTS.md`, memori. `PpdbInsightTest` (12 kasus), `PpdbDemoSeederTest` (3 kasus). Bukti: lihat Hasil akhir. |

## Tasks

### Tahap 0 — Fondasi
- [x] Buat branch `feat/ppdb` dari `main`; tulis dokumen ini ke `docs/ai/plan/fase-11/ppdb-plan.md` (baru), status "Berjalan".
- [x] Kontrak + DTO + exception — `modules/Core/app/Contracts/StudentAdmission.php`, `modules/Core/app/Contracts/DTOs/NewStudent.php`, `modules/Core/app/Contracts/Exceptions/StudentAdmissionRefusedException.php` (baru).
- [x] Implementasi + binding — `modules/Core/app/Infrastructure/Admission/DefaultStudentAdmission.php` (baru), `modules/Core/app/Infrastructure/Providers/CoreServiceProvider.php`.
- [x] Izin `ppdb.*` + label — `modules/Ppdb/app/Infrastructure/Providers/PpdbServiceProvider.php`, `modules/Ppdb/config/permission_labels.php` (baru), `modules/Identity/config/roles.php`.
- [x] Gate route dan menu yang ada (halaman masih mock) — `modules/Ppdb/routes/web.php`, provider.
- [x] Periksa: Penempatan Siswa menampilkan siswa aktif tanpa kelas; `sekolah-a` tetap punya modul `ppdb` setelah `migrate:fresh --seed`. Hasilnya ke Temuan.
- [x] Tes — `modules/Core/tests/Feature/StudentAdmissionTest.php` (baru): siswa aktif tanpa kelas dan tanpa akun, NIS/NISN ganda ditolak dengan nama kolom, isolasi tenant. `modules/Ppdb/tests/Feature/PpdbAccessTest.php` (baru): tiap peran per halaman, guru dan siswa 403 dan tanpa menu PPDB, modul mati 403. Pindahkan `ppdbTenant()` ke `modules/Ppdb/tests/Feature/Support/helpers.php` (baru, dengan parameter peran). Sesuaikan ekspektasi izin di `modules/Identity/tests/Feature/{DefaultRolesTest,RolesSyncTest,RolesPageTest}.php`.

**Selesai bila:** Ppdb bisa membuat siswa lewat kontrak Core dan halaman PPDB mengikuti izin. Bukti: `php artisan test --compact modules/Core modules/Ppdb modules/Identity tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 1 — Pengaturan dan Ringkasan
- [x] Migrasi `ppdb_periods`, `ppdb_waves`, `ppdb_paths`; `loadMigrationsFrom` — `modules/Ppdb/database/migrations/` (baru).
- [x] Enum, model, factory, `SchoolDay`; entri `deptrac.baseline.yaml` — `modules/Ppdb/app/Domain/{Enums,Models,Support}/`, `modules/Ppdb/database/factories/` (baru).
- [x] `SavePeriod`, `SaveWave`, `SavePaths` + FormRequest — `modules/Ppdb/app/Domain/Actions/`, `modules/Ppdb/app/Http/Requests/` (baru).
- [x] `SettingsController`, `OverviewController`; route `ppdb.settings*`; menu "Pengaturan"; kode dan tautan sekolah di Pengaturan (`TenantContext::currentOrFail()->id`, `TenantUrl::root()`).
- [x] Halaman `Settings.tsx` (baru), `Overview.tsx` nyata.
- [x] Tes — `modules/Ppdb/tests/Feature/PpdbSettingsTest.php` (baru): satu periode aktif, jalur bawaan, gelombang tumpang-tindih ditolak, status gelombang menurut hari sekolah (`travelTo` pada zona tenant), tautan memuat kode sekolah, izin, isolasi tenant.

**Selesai bila:** periode, gelombang, dan kuota tersimpan dan tampil di Ringkasan, dan Pengaturan menampilkan kode/tautan. Bukti: `php artisan test --compact modules/Ppdb` → hijau.

### Tahap 2 — Pendaftar untuk panitia
- [x] Migrasi `ppdb_applicants` (termasuk `account_id`); model `Applicant` + factory; baseline.
- [x] `RegisterApplicant` (sumber `staff`), `UpdateApplicant`, `VerifyApplicant`, `CancelApplication`; `DocumentCheck` + `AlwaysCompleteDocumentCheck`, binding di provider — `modules/Ppdb/app/Domain/{Actions,Support}/`, `modules/Ppdb/app/Infrastructure/Stubs/` (baru).
- [x] `ApplicantController`, `VerificationController` + FormRequest; route pendaftar.
- [x] Halaman `Applicants.tsx` nyata, `ApplicantForm.tsx`, `ApplicantShow.tsx` (baru).
- [x] Tes — `modules/Ppdb/tests/Feature/ApplicantTest.php` (baru): nomor berurutan dan unik, NISN ganda dalam satu periode ditolak, jalur periode lain ditolak, tanpa periode aktif ditolak, pencarian + filter + paginasi, verifikasi dan catatan, pembatalan hanya sebelum keputusan, izin (`staf-tu` boleh, `guru` 403), isolasi tenant.

**Selesai bila:** panitia menginput pendaftar, menemukannya di daftar, memverifikasi, dan bisa membatalkannya. Bukti: `php artisan test --compact modules/Ppdb` → hijau.

### Tahap 3 — Akun calon siswa
- [x] Migrasi `ppdb_accounts` (pusat); model `PpdbAccount` + factory; guard `ppdb` — `modules/Ppdb/database/migrations/` (baru), `modules/Ppdb/app/Domain/Models/PpdbAccount.php` (baru), `config/auth.php`.
- [x] `AuthenticatePpdbAccount`; `RegisterAccount`; controller `Account/{Register,Session,Verification,Password}Controller` + FormRequest; Mailable verifikasi dan atur ulang + tampilan surat (`Ppdb::`); route akun.
- [x] Halaman `Account/{Register,Login,VerifyNotice,ForgotPassword,SetPassword}.tsx`, `Account/Home.tsx` (sementara: sapaan + tombol Keluar), `Components/PortalPage.tsx` (baru).
- [x] Tes — `modules/Ppdb/tests/Feature/PpdbAccountTest.php` (baru; `Mail::fake()`): daftar membuat akun dan mengantre surat verifikasi, honeypot terisi → tanpa baris, email ganda ditolak, akun belum terverifikasi dialihkan ke pemberitahuan, tautan bertanda tangan memverifikasi dan tautan rusak 403, masuk dengan galat generik dan terkena limiter, atur ulang kata sandi, keluar. Pemisahan guard: sesi `ppdb` tidak membuka `/ppdb` panitia maupun `/beranda`; pengguna sekolah (`web`) tidak membuka `/calon-siswa`.

**Selesai bila:** calon siswa membuat akun, memverifikasi email, masuk, dan keluar, tanpa menyentuh guard lain. Bukti: `php artisan test --compact modules/Ppdb tests/Architecture` → hijau.

### Tahap 4 — Bergabung, formulir, status
- [x] `JoinSchool`, `LeaveSchool`; `RegisterApplicant` (sumber `online`) dan `UpdateApplicant` oleh pemilik; `PortalState` — `modules/Ppdb/app/Domain/{Actions/Account,Actions,Queries}/` (baru).
- [x] `JoinSchoolController`, `ApplicationController`, `PortalController` + FormRequest; route `join*`, `form*`, `home`.
- [x] Halaman `Account/Home.tsx` (empat keadaan), `Account/Join.tsx`, `Account/Form.tsx` (baru); semua halaman masuk sekolah lewat `TenantContext::run($account->tenant_id, …)`.
- [x] Tes — `modules/Ppdb/tests/Feature/JoinSchoolTest.php` dan `PortalApplicationTest.php` (baru): bergabung dengan kode benar; kode salah, sekolah ditangguhkan, modul mati, tanpa periode aktif → pesan yang sama persis; limiter; keluar sebelum mengirim boleh dan sesudahnya ditolak; formulir hanya untuk akun yang sudah bergabung dan hanya saat gelombang dibuka; nomor pendaftaran muncul di halaman; status "Perlu perbaikan" menampilkan catatan, perbaikan diizinkan hanya saat itu dan mengembalikan status ke `submitted`; **isolasi**: akun sekolah A tidak pernah membaca atau menulis data sekolah B dan pendaftar lain, parameter `school` di formulir diabaikan; pembatalan oleh panitia membebaskan akun.

**Selesai bila:** calon siswa bergabung ke satu sekolah dengan kodenya, mengirim formulir sekali, dan melihat statusnya. Bukti: `php artisan test --compact modules/Ppdb` → hijau.

### Tahap 5 — Seleksi dan pengumuman
- [x] `PathRanking`, `SaveSelection`, `PublishResults`; `ResultAnnouncer` + `SilentResultAnnouncer` — `modules/Ppdb/app/Domain/{Queries,Actions,Support}/`, `Infrastructure/Stubs/` (baru).
- [x] `SelectionController` + FormRequest; route `ppdb.selection.update`, `.publish`.
- [x] Halaman `Selection.tsx` nyata; keadaan "hasil" di `Account/Home.tsx`.
- [x] Tes — `modules/Ppdb/tests/Feature/SelectionTest.php` (baru): urutan peringkat, hanya terverifikasi, melebihi kuota ditolak, pengumuman ditolak selama ada `pending`, setelah pengumuman hanya naik cadangan, `ResultAnnouncer` dipanggil sekali per pendaftar, `staf-tu` hanya-baca (tulis 403), **hasil tidak muncul di props halaman akun sebelum diumumkan**, isolasi tenant.

**Selesai bila:** admin menetapkan keputusan sesuai kuota dan mengumumkan hasil, dan calon siswa melihat hasilnya hanya setelah itu. Bukti: `php artisan test --compact modules/Ppdb` → hijau.

### Tahap 6 — Daftar ulang
- [x] `EnrollApplicant`, `EnrollmentController` + FormRequest; panel daftar ulang di `ApplicantShow.tsx`; `AdmissionFunnel` lengkap di Ringkasan; halaman akun menampilkan "Sudah daftar ulang".
- [x] Tes — `modules/Ppdb/tests/Feature/EnrollmentTest.php` (baru): siswa Core tercipta dengan data pendaftar, `student_id` tersimpan, sebelum pengumuman / bukan `accepted` / sudah daftar ulang ditolak, NIS terpakai → galat pada `nis` dan pendaftar tidak berubah, corong menghitung daftar ulang.

**Selesai bila:** pendaftar diterima menjadi siswa di Master Data tepat satu kali. Bukti: `php artisan test --compact modules/Ppdb modules/Core tests/Architecture` → hijau; `composer deptrac` → 0 pelanggaran.

### Tahap 7 — Laporan, statistik, penutup
- [x] `ApplicantListReport`, `SelectionResultReport`, `AdmissionStatistics` + pendaftaran di provider — `modules/Ppdb/app/Domain/{Reports,Statistics}/` (baru).
- [x] Pindahkan tes Core tentang "Segera hadir" ke kunci `schedule` — `modules/Core/tests/Feature/{InsightRegistryTest,InsightReportsTest}.php`, `modules/Core/tests/Feature/Support/insight.php`, `tests/Browser/InsightTest.php`, `tests/E2E/insight.spec.ts`.
- [x] Seeder contoh — `modules/Ppdb/database/seeders/PpdbDemoSeeder.php` (baru; sekolah bermodul PPDB yang belum punya periode; tanpa angka acak; tanpa `WithoutModelEvents`).
- [x] Tes — `modules/Ppdb/tests/Feature/{PpdbInsightTest,PpdbDemoSeederTest}.php` (baru). Tes browser `tests/Browser/PpdbTest.php` (baru; pola `tests/Browser/AttendanceTest.php`): pengaturan → akun calon siswa dibuat dan diverifikasi → bergabung dengan kode → mengirim formulir → panitia verifikasi → nilai dan terima → umumkan → daftar ulang → siswa terlihat di Master Data; `assertNoJavaScriptErrors()`. Spec `tests/E2E/ppdb-account.spec.ts` (baru; pola `tests/E2E/student-qr.spec.ts`, verifikasi email lewat `tests/E2E/support/database.ts`): calon siswa di sesi sendiri membuka tautan, mendaftar, bergabung, mengirim formulir; admin di sesi lain melihat pendaftarnya.
- [x] Bersihkan: hapus `PpdbController.php`, `PpdbMockData.php`, banner di `PpdbPage.tsx`; perbarui `PpdbPagesTest.php`; `npm run build`.
- [x] Dokumentasi: `modules/Ppdb/CONTRACT.md` (termasuk tabel pusat `ppdb_accounts` dan guard `ppdb`), `modules/Core/CONTRACT.md`, `modules/Identity/CONTRACT.md`, `docs/architecture/modular-monolith.md` (catat bahwa registrasi publik kini ada untuk dua jenis akun pusat: pemohon sekolah dan calon siswa; pengguna sekolah tetap tanpa registrasi publik), salinan `AGENTS.md` (bagian "PPDB (Fase 11)"), memori `project_mockup_first.md`.

**Selesai bila:** laporan dan angka PPDB berasal dari database dan tidak ada sisa mock. Bukti: `php artisan test --compact modules/Ppdb modules/Core tests/Architecture` → hijau; `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/PpdbTest.php` → hijau; `npx playwright test ppdb-account` → hijau; `composer deptrac` → 0 pelanggaran; `npm run build` → sukses.

## Batas tindakan
**Selalu**
- `vendor/bin/pint --dirty --format agent` dan tes modul yang disentuh sebelum menandai tahap ✅.
- Perbarui tabel status, centang task, catat penyimpangan di Log keputusan.
- `Mail::fake()` di tes yang memicu surat; tidak ada surat sungguhan.

**Tanya dulu**
- Mengerjakan salah satu fitur yang ditunda (WA hasil, unggah berkas, daftar sekolah publik) atau memasukkan `ppdb` ke paket langganan.
- Menambah pembatalan pengumuman, pengunduran diri, atau status/keputusan baru; mengizinkan ganti sekolah setelah formulir dikirim.
- Mengubah signature kontrak Core yang sudah ada, `deptrac.php`, kode Platform, atau kode Identity selain `config/roles.php`.
- Memindahkan jam sekolah ke kontrak Platform; menambah dependensi; menambah scheduler/cron.

**Jangan**
- Mengimpor model Core, `User`, `Applicant` milik Platform, atau apa pun dari Attendance; mengimpor Ppdb dari Core.
- FK selain `tenant_id`; `DB::table()` untuk data tenant; `WithoutModelEvents` di seeder.
- Mengandalkan `ResolveTenant` di halaman akun; menerima tenant id dari request kecuali kode saat bergabung.
- Membedakan pesan galat bergabung (jangan bocorkan sekolah mana yang ada); menampilkan keputusan sebelum diumumkan; menyimpan data pendaftar di tabel akun pusat.
- Menghapus entri `ppdb.result` dari `modules/Core/config/notices.php`; memanggil gateway WhatsApp.
- Menghapus tes; mengubah nama route `ppdb.overview` / `ppdb.applicants` / `ppdb.selection`; commit/push tanpa diminta.

## Pengujian
- **Tahap 0:** kontrak penerimaan siswa; akses per peran dan flag modul.
- **Tahap 1:** aturan periode, gelombang, kuota; kode/tautan; izin; isolasi tenant.
- **Tahap 2:** input panitia, penomoran, validasi, pencarian, verifikasi, pembatalan.
- **Tahap 3:** daftar/verifikasi/masuk/atur ulang akun; pemisahan guard; limiter dan honeypot.
- **Tahap 4:** bergabung (jalur sukses dan penolakan seragam), terkunci setelah mengirim, perbaikan, isolasi antar sekolah dan antar akun.
- **Tahap 5:** peringkat, kuota, pengumuman, kunci setelah pengumuman, hasil tersembunyi sebelum diumumkan.
- **Tahap 6:** siswa tercipta sekali, penolakan, corong.
- **Tahap 7:** laporan, statistik, seeder; satu alur browser; satu spec Playwright.

Factory untuk semua data; tanggal diuji dengan `travelTo` pada zona waktu tenant. Setelah Tahap 7, minta user menjalankan suite penuh `php artisan test --compact`.

## Verifikasi end-to-end
1. `php artisan migrate`, `php artisan roles:sync` (atau `migrate:fresh --seed` di dev); `composer run dev` (worker antrean jalan agar surat terkirim).
2. Login admin `sekolah-a` → PPDB › Pengaturan: buat periode, gelombang yang mencakup hari ini, kuota Zonasi 2; salin tautan PPDB.
3. Jendela privat: buka tautan → diarahkan ke daftar akun → buat akun → buka tautan verifikasi dari surat (log mail dev) → masuk → kolom kode sudah terisi → Gabung → isi formulir → nomor `PPDB-..-0001` tampil dan status "Menunggu verifikasi". Coba kode acak dan kode sekolah B (modul PPDB mati): pesan galat sama persis.
4. Coba "Keluar dari sekolah" setelah formulir terkirim → ditolak. Admin → Pendaftar: pendaftar ada (sumber online); tambah dua pendaftar input panitia; "Perlu perbaikan" pada yang online → di jendela privat catatan tampil dan data bisa diperbaiki; verifikasi ketiganya.
5. Seleksi, jalur Zonasi: isi nilai; menerima tiga orang ditolak karena kuota 2; terima dua, satu cadangan. Jendela privat: hasil belum terlihat. "Umumkan hasil" → hasil terlihat.
6. Pendaftar diterima → Daftar ulang dengan NIS → Warga Sekolah › Siswa menampilkan siswa baru tanpa kelas; NIS yang sama untuk pendaftar lain → galat pada NIS. Batalkan pendaftaran satu pendaftar online yang belum diputuskan → akunnya bebas bergabung lagi.
7. Statistik & Laporan: "Daftar Pendaftar PPDB" dan "Hasil Seleksi PPDB" terunduh sebagai CSV; angka PPDB tampil.
8. Login sebagai guru dan siswa → tanpa menu PPDB, `/ppdb` → 403; sesi calon siswa tidak membuka `/ppdb` maupun `/beranda`. Sekolah lain: data terpisah. `composer deptrac` → bersih.

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- 2026-10-02 — Plan dibuat. Dua fitur tambahan ditunda atas keputusan user dan diwakili fungsi tiruan yang mengembalikan `true`.
- 2026-10-03 (Tahap 0) — Penempatan Siswa (`PlacementController`) hanya memuat siswa dari satu kelas, jadi siswa hasil daftar ulang (tanpa kelas) tidak muncul di sana. Mereka diberi kelas lewat form siswa di Warga Sekolah › Siswa (pilihan "Belum ditempatkan" di `StudentForm.tsx`, lewat `SaveStudent` sehingga riwayat kelas benar). Tidak ada kode Core yang diubah; kata "Penempatan Siswa" di Alur langkah 7 dan Asumsi dibaca sebagai form siswa. Menambah filter "belum ditempatkan" di Penempatan Siswa tidak dikerjakan (di luar cakupan).
- 2026-10-03 (Tahap 0) — Tes peran Identity diubah karena perilakunya berubah dengan sengaja: `DefaultRolesTest` dan `RolesPageTest` kini mengharapkan izin `ppdb.*` pada admin-sekolah (empat) dan staf-tu (`ppdb.view`, `ppdb.applicants.manage`). `RolesSyncTest` tidak berubah.
- 2026-10-03 (Tahap 1) — Pengaturan tidak punya aksi hapus gelombang atau hapus jalur: menghapusnya harus ditolak bila sudah ada pendaftar, dan tabel pendaftar baru ada di Tahap 2. `SavePaths` hanya menambah dan mengubah (jalur yang tidak dikirim tidak disentuh; baris jalur baru yang belum disimpan bisa dibuang di halaman). `DeleteWave` dan `DeletePath` dengan pemeriksaan pendaftar ditambahkan di Tahap 2. Aturan "kuota tidak boleh di bawah jumlah yang sudah diterima" ditambahkan di Tahap 5, saat keputusan ada.
- 2026-10-03 (Tahap 1) — Ringkasan memberi `funnel: []` dan tidak punya kolom "Pendaftar" per gelombang: angka itu butuh tabel pendaftar (Tahap 2; corong penuh Tahap 6). Panel corong disembunyikan selama kosong, supaya tidak menampilkan nol palsu.
- 2026-10-03 (Tahap 1) — `PpdbPage` mendapat prop `mock` (bawaan mati). Hanya Pendaftar dan Seleksi yang masih contoh memakainya; Ringkasan dan Pengaturan nyata tidak lagi menampilkan banner. Banner dihapus seluruhnya di Tahap 7.
- 2026-10-03 (Tahap 2) — `DeleteWave` dan `DeletePath` dikerjakan di sini seperti dijanjikan di Tahap 1 (route `ppdb.settings.waves.destroy`, `ppdb.settings.paths.destroy`; konfirmasi lewat `Components/ConfirmAction.tsx`). Tambahan kecil di luar daftar semula: sebuah periode harus tetap punya sedikitnya satu jalur.
- 2026-10-03 (Tahap 2) — `AdmissionFunnel` (corong + jumlah pendaftar per gelombang) dikerjakan di Tahap 2, bukan Tahap 6, karena semua angkanya sudah bisa dihitung dari tabel pendaftar; Diterima dan Daftar ulang bernilai 0 sampai Tahap 5 dan 6. Tahap 6 hanya menampilkan hasilnya di halaman akun.
- 2026-10-03 (Tahap 2) — Daftar pendaftar menampilkan keputusan seleksi pada kolom Status bila sudah ada, selain itu status verifikasi (perilaku tiruan semula dipertahankan). Filter Status hanya memilah status verifikasi. `PpdbController` kini hanya memegang halaman Seleksi tiruan; `PpdbMockData` hanya menyisakan data seleksi.
- 2026-10-03 (Tahap 2) — Aturan `UpdateApplicant`/`VerifyApplicant`/`CancelApplication` yang tidak tertulis di plan semula: pendaftar yang sudah daftar ulang tidak bisa diubah atau diverifikasi ulang; yang sudah punya keputusan hanya boleh berstatus Terverifikasi; "Perlu perbaikan" wajib bercatatan. Pembebasan akun saat pembatalan menunggu Tahap 4 (akunnya belum ada).
- 2026-10-03 (Tahap 3) — Halaman akun memakai kerangka sendiri `Components/PortalPage.tsx` (satu kolom, merek, nama akun + Keluar, pesan flash), bukan `AuthShell` seperti tertulis di Desain: `AuthShell` bergaya kartu dan tidak menampilkan pesan flash ("Email terverifikasi", "Tautan dikirim"), sedangkan halaman akun membutuhkannya. Satu kerangka dipakai untuk halaman masuk maupun halaman akun.
- 2026-10-03 (Tahap 3) — Keluar hanya mengeluarkan guard `ppdb` (`logout()` + `regenerateToken()`), tidak `session()->invalidate()` seperti `SessionController` pemohon Platform: invalidasi akan memutus sesi sekolah di browser yang sama. Teruji (`ends only the applicant session on sign out`).
- 2026-10-03 (Tahap 3) — Kedua tautan (verifikasi dan atur ulang) ditandatangani relatif dan diawali `TenantUrl::root()`; di Platform tautan verifikasi memakai tanda tangan absolut. Alasannya sama dengan di Platform untuk tautan atur ulang: surat dikirim dari worker antrean tanpa host permintaan.
- 2026-10-03 (Tahap 3) — Pesan galat masuk ditulis sendiri ("Email atau kata sandi salah.") dan tidak memakai `__('auth.failed')` karena bahasa aplikasi tidak menyediakan terjemahan Indonesia.
- 2026-10-03 (Tahap 4) — Gelombang pendaftar online tidak dipilih pendaftar: `RegisterApplicant` memasangnya ke gelombang yang sedang dibuka hari ini (satu saja, karena gelombang satu periode tidak tumpang-tindih), sehingga formulir sendiri tidak punya kolom gelombang (`ApplicantFields` menerima `waves` opsional) dan isian `wave_id` dari request diabaikan. Tanpa gelombang yang dibuka, pendaftaran ditolak.
- 2026-10-03 (Tahap 4) — Akun yang sudah bergabung ke sekolah lain tidak otomatis pindah bila membuka tautan sekolah baru: ia harus keluar lebih dulu (`JoinSchool` menolak dengan pesan tersendiri; tautan lalu hanya mengarahkan ke halaman akun). Alasannya: pindah diam-diam di balik tautan mudah salah dan tidak bisa dibatalkan pemilik akun.
- 2026-10-03 (Tahap 4) — Aturan isian dipecah ke `Http/Requests/ApplicantFieldRules` yang dipakai `ApplicantRequest` (panitia, dengan `wave_id`) dan `Account/ApplicationRequest` (pendaftar, tanpa `wave_id`).
- 2026-10-03 (Tahap 4) — Sekolah yang ditangguhkan atau tanpa modul PPDB tetap menampilkan pendaftaran yang sudah terkirim di halaman akun (hanya-baca); keadaan "tidak dibuka" hanya berlaku untuk akun yang belum mengirim formulir.
- 2026-10-03 (Tahap 5) — Aturan seleksi yang tidak tertulis di plan semula, ditetapkan saat membangun: (1) keputusan selain "Belum diputuskan" mewajibkan nilai, karena peringkat berdasarkan nilai; (2) menyimpan seleksi bersifat semua-atau-tidak-sama-sekali; (3) pendaftar yang sudah daftar ulang tidak bisa diubah; (4) pengumuman ditolak bila belum ada pendaftar terverifikasi; (5) seleksi hanya untuk periode yang bukan konsep (aktif atau ditutup), karena panitia boleh menutup periode begitu pendaftaran selesai; (6) pendaftar yang dinaikkan dari cadangan setelah pengumuman langsung diberi tahu lewat `ResultAnnouncer`; (7) kuota 0 berarti belum ada kursi — menerima siapa pun ditolak sampai kuota diatur di Pengaturan.
- 2026-10-03 (Tahap 5) — Pengumuman memberi tahu semua pendaftar yang sudah punya keputusan (termasuk Cadangan dan Tidak diterima), bukan hanya yang diterima. Pendaftar yang masih menunggu verifikasi atau perbaikan tidak diberi tahu (belum ada keputusan).
- 2026-10-03 (Tahap 6) — Tes Ppdb membuat dan membaca siswa lewat kontrak Core (`StudentAdmission::admit()` untuk menyiapkan NIS/NISN yang bentrok, `StudentDirectory::search()` untuk memeriksa hasil), bukan model Core. Itu pilihan, bukan keharusan: tes modul dikecualikan dari aturan impor di `tests/Architecture/ModularMonolithTest.php` (dianggap lapisan perekat, seperti tes Absensi yang memakai factory Core). Dengan kontrak, tes Ppdb tidak ikut rusak bila skema Core berubah, dan kontraknya sendiri ikut teruji. Pemetaan data pendaftar → `NewStudent` dibuktikan dengan `StudentAdmission` tiruan perekam (`RecordingStudentAdmission`); kolom lain siswa (jenis kelamin, tanggal lahir, wali) dibuktikan oleh `StudentAdmissionTest` di Core.
- 2026-10-03 (Tahap 6) — Galat NISN bentrok ditampilkan pada kolom NIS (satu-satunya isian formulir daftar ulang), dengan pesan "NISN ini sudah terdaftar." dari Core.
- 2026-10-03 (Tahap 6) — Halaman detail pendaftar tidak menautkan ke halaman siswa di Core: nama route Core yang ditulis di Ppdb akan menjadi ketergantungan tersembunyi yang tidak ditangkap Deptrac. Pesan sukses mengarahkan ke Warga Sekolah › Siswa dalam teks biasa.
- 2026-10-03 (Tahap 7) — Entri "Segera hadir" untuk laporan PPDB di `modules/Core/config/insight.php` tidak dihapus (sama seperti laporan Kehadiran di fase 10): kunci laporan yang terdaftar tidak lagi diumumkan untuk sekolah mana pun, jadi entrinya tidak berefek. Tes Core yang memakai kunci PPDB sebagai contoh "masih diumumkan" pindah ke `schedule` (`InsightRegistryTest`, `InsightReportsTest`, `Support/insight.php`) dan `tests/Browser/InsightTest.php` kini mencari "Jadwal Pelajaran"; `tests/E2E/insight.spec.ts` tidak menyebut PPDB sehingga tidak berubah. Perubahan perilaku yang disengaja.
- 2026-10-03 (Tahap 7) — Perbaikan di luar daftar semula, ditemukan spec Playwright: `Account/SessionController` tidak lagi memakai `redirect()->intended()` apa adanya (lihat Temuan).
- 2026-10-03 (Tahap 7) — Tipe PHPStan (level 7) dibereskan dengan metode bertipe di FormRequest (`periodData()`, `waveData()`, `accountData()`) dan `array_values()` pada daftar, bukan dengan baseline atau `@phpstan-ignore`.
- 2026-10-03 — Revisi: formulir publik tanpa login diganti akun calon siswa yang bergabung ke satu sekolah dengan kode/tautan, terkunci sejak formulir dikirim (keputusan user). Tahap 3 formulir publik digantikan Tahap 3 (akun) dan Tahap 4 (bergabung + formulir + status); halaman cek hasil publik tidak diperlukan lagi.

### Temuan
- (Tahap 7) **Bug yang hanya terlihat di server sungguhan:** kunci sesi `url.intended` dipakai bersama semua guard. Calon siswa yang sempat membuka halaman sekolah (`/ppdb`, lalu dialihkan ke login sekolah) meninggalkan `url.intended = /ppdb`; masuk ke akun PPDB lewat `redirect()->intended()` lalu membawanya kembali ke `/ppdb`, yang membalas ke login sekolah — tampak seperti gagal masuk. Tes fitur dan tes browser (satu proses PHP) tidak menangkapnya; spec Playwright dengan dua sesi sungguhan menangkapnya. Diperbaiki: `SessionController::destination()` hanya mengikuti alamat di `/calon-siswa` (tautan gabung sekolah tetap terbawa) dan membuang yang lain; teruji di `PpdbAccountTest`. Sisi sebaliknya (pengguna sekolah yang membawa `url.intended` ke halaman akun PPDB setelah masuk) ada di kode Identity yang tidak diubah fase ini; efeknya hanya salah alamat yang langsung dialihkan ke login akun PPDB. Dicatat untuk keputusan user.
- (Tahap 7) Setelah `npm run build`, tes yang dijalankan lewat `vendor/bin/pest -c phpunit.e2e.xml` dan `npx playwright test` memakai `public/hot` bila ada. Berkas itu di mesin ini basi (menunjuk server Vite port 5175 yang sudah mati), jadi dipindah sementara selama keduanya berjalan lalu dikembalikan.
- (Tahap 5) Aturan "kuota tidak boleh di bawah jumlah yang sudah diterima" ditegakkan di dua tempat: `SaveSelection` menolak penerimaan melebihi kuota, dan `SavePaths` menolak menurunkan kuota di bawah jumlah yang sudah diterima (`SelectionTest`: "does not let the quota be set below the number already accepted").
- (Tahap 4) Halaman akun adalah satu-satunya tempat yang menyentuh data sekolah dari luar sekolah. Semua jalurnya masuk lewat `TenantContext::run($akun->tenant_id, …)` dan mencari pendaftaran hanya lewat `account_id` akun itu; tes membuktikan sesi yang mengingat sekolah lain, parameter `school`/`tenant_id`/`account_id` di request, dan akun lain tidak mengubah apa pun (`PortalApplicationTest`). Props halaman akun tidak pernah memuat nilai seleksi; keputusan hanya bila `results_published_at` terisi.
- (Tahap 3) `Modules\Platform\Tests\Feature\ProviderAuthTest` ("renders the provider login page on the console host") gagal di mesin ini: `assertSee('Console Provider')` butuh server SSR Inertia di `127.0.0.1:13714` (`config/inertia.php`, `ssr.enabled`), dan tidak ada yang mendengarkan di sana; hanya satu server Vite hidup, milik worktree lain (`.claude/worktrees/calm-squishing-prism`, port 5173), sedangkan `public/hot` menunjuk 5175 yang sudah mati. Gagal juga pada pohon bersih (`git stash -u`, tanpa perubahan fase ini) dan tetap gagal tanpa `public/hot`, jadi bukan akibat perubahan fase 11. Lulus saat server SSR hidup (suite penuh di awal sesi ini lulus). Dilaporkan ke user; tidak diubah.
- (Tahap 3) `actingAs($akun, 'ppdb')` menjadikan `ppdb` guard bawaan sehingga halaman sekolah tampak terbuka; tes pemisahan guard memakai login sungguhan (`post('/calon-siswa/masuk')`) dan `Auth::guard('web')->logout()` di antaranya. `ResolveTenant` juga menghapus kunci sesi `tenant_id` yang bukan kode sekolah valid: tes yang ingin membuktikan sesi tidak diinvalidasi memakai kunci lain (`penanda`).
- (Tahap 3) Dua controller bernama `VerificationController` ada di modul ini (verifikasi pendaftar oleh panitia, dan verifikasi email akun): di `routes/web.php` yang kedua diberi alias `AccountVerificationController`. Salah memakai yang pertama pada route verifikasi email sempat menghasilkan 500.
- (Tahap 2) Di tes, *arrow function* menangkap variabel per nilai: `fn () => ... ++$sequence` tidak menaikkan penghitung di luarnya, sehingga nomor pendaftar buatan helper bentrok dengan indeks unik. Hitung nomor di luar closure.
- (Tahap 2) `Route::whereNumber()` bukan atribut grup route yang valid; pakai `Route::where(['applicant' => '[0-9]+'])->group(...)`.
- (Tahap 1) Model Eloquent menebak nama tabel dari nama kelas (`admission_periods`); karena tabel berawalan `ppdb_`, tiap model Ppdb perlu `protected $table`. Terlihat dari 500 di semua halaman PPDB saat migrasi sudah ada tetapi `$table` belum.
- (Tahap 1) Tipe halaman modul diperiksa dengan tsconfig sementara yang memuat `modules/Ppdb/resources/js/**` dan `modules/Shared/resources/js/**` (bersih); berkas dihapus lagi sesudahnya. Wayfinder harus dijalankan (`php artisan wayfinder:generate`) setelah controller/route baru; hasilnya di-gitignore.
- (Tahap 0) `PlacementController::index` mengambil siswa lewat `$source->students()`: siswa tanpa kelas tidak pernah tampil di Penempatan Siswa. Cara memberi kelas ke siswa hasil PPDB adalah form siswa (lihat Log keputusan).
- (Tahap 0) Tidak ada paket langganan yang memuat `ppdb` (`BillingMasterDataSeeder`: `core`, `identity`, `attendance`). `PlatformDevSeeder` menulis langganan `sekolah-a` langsung tanpa sinkron modul, jadi `sekolah-a` tetap punya `ppdb` di dev (dibaca dari seeder; `migrate:fresh --seed` tidak dijalankan agar database dev tidak terhapus). Namun mengganti paket `sekolah-a` dari konsol provider menyinkronkan modul dan mematikan PPDB; sekolah sungguhan butuh provider menambah `ppdb` ke modul paketnya atau menyalakannya per sekolah. Keputusan memasukkan PPDB ke paket tetap di tangan user ("Tanya dulu").
- (Perencanaan) Platform sudah memakai nama tabel `applicants` untuk pemohon sekolah; tabel PPDB berawalan `ppdb_`.
- (Perencanaan) Jam sekolah (`SchoolClock`) milik Attendance dan tidak bisa dipakai Ppdb; `SchoolDay` adalah duplikat kecil — calon helper di `PlatformPublic`.
- (Perencanaan) `GuardianNotifier` hanya mengenal `student_id`; WA untuk pendaftar butuh kontrak Core baru saat fitur itu dikerjakan.
- (Perencanaan) Pola akun pusat sudah ada di Platform (`applicants`, guard `applicant`, `/pemohon/*`); akun calon siswa menyalinnya di Ppdb karena internal Platform tidak boleh diimpor. Duplikasi pola (verifikasi email bertanda tangan, honeypot, limiter) dicatat; bila akun pusat ketiga muncul, pertimbangkan membuatnya jadi kontrak/komponen bersama.
- (Perencanaan) `ResolveTenant` mengadopsi sekolah yang diingat sesi untuk tamu, termasuk di halaman pusat; karena itu halaman akun selalu masuk sekolah secara eksplisit.

### Hasil akhir
Selesai 2026-10-03 di branch `feat/ppdb`, belum di-commit.

- **Yang jadi:** calon siswa membuat akun sendiri (pusat, guard `ppdb`, `/calon-siswa/*`), memverifikasi email, bergabung ke satu sekolah dengan kode/tautan sekolah, mengirim formulir, memperbaikinya bila diminta, dan melihat hasil di halaman akunnya setelah diumumkan. Panitia mengatur periode/gelombang/jalur/kuota, menginput pendaftar langsung, memverifikasi, menilai dan menetapkan keputusan sesuai kuota per jalur, mengumumkan, dan mencatat daftar ulang yang membuat siswa di Core lewat `StudentAdmission`. Laporan, figur, panel, seeder contoh, dokumentasi lengkap; tidak ada sisa mock PPDB.
- **Bukti:** `php artisan test --compact` -> 1.247 dari 1.249 lulus (dua gagal, lihat bawah); `vendor/bin/pest -c phpunit.e2e.xml` -> 25 lulus (2 baru); `npx playwright test` -> 9 lulus (1 baru); `vendor/bin/deptrac analyse` -> 0 pelanggaran; PHPStan 29 galat, sama dengan sebelum fase ini, 0 di kode baru; pemeriksaan tipe halaman Ppdb bersih; `npm run build` sukses.
- **Dua tes gagal, bukan akibat fase ini:** `ExampleTest` (login console) dan `ProviderAuthTest` (halaman login console) memeriksa teks halaman yang hanya ada bila server SSR Inertia hidup; di mesin ini tidak ada (gagal juga di pohon bersih).
- **Belum dibuktikan:** surat verifikasi/atur ulang lewat antrean dan SMTP sungguhan (tes memakai `Mail::fake()`; server Playwright memakai mailer `log` dan akun diverifikasi lewat database); pemberitahuan WA hasil seleksi dan unggah berkas (ditunda atas keputusan user).
- **Langkah user sebelum mencoba:** `php artisan migrate`, `php artisan roles:sync`; worker antrean berjalan; PPDB belum ada di paket mana pun, jadi tambahkan `ppdb` ke modul paket di konsol provider atau nyalakan per sekolah. Data contoh: `php artisan db:seed --class="Modules\Ppdb\Database\Seeders\PpdbDemoSeeder"`.
- **Sisa untuk nanti:** WA hasil seleksi (kontrak Core untuk penerima yang belum jadi siswa), unggah berkas, memasukkan PPDB ke paket langganan, filter "belum ditempatkan" di Penempatan Siswa, `url.intended` bersama antar guard di sisi Identity.
