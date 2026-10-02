# Penyusun formulir PPDB (seperti Google Form): kolom kustom, urutan, pratinjau, unggah berkas

> **Status:** Selesai · **Dibuat:** 2026-10-03 · **Branch:** `feat/ppdb` (lanjutan fase 11; belum di-push)
> **Lokasi dokumen saat dieksekusi:** `docs/ai/plan/fase-11/ppdb-form-builder-plan.md` (baru) — menyalin isi berkas ini adalah task pertama Tahap 0.

## Context
Commit `c5bba66` memberi sekolah tiga keadaan (Wajib/Opsional/Tidak dipakai) untuk tujuh kolom bawaan, disimpan sebagai JSON di `ppdb_periods.form_fields` dan diatur dari satu panel di Pengaturan PPDB. Sekolah belum bisa menambah pertanyaan sendiri, mengubah urutan, atau meminta berkas. User ingin formulir pendaftaran berfungsi seperti Google Form, dengan submenu sendiri.

## Goal
Setelah ini admin sekolah membuka **PPDB › Formulir** dan, untuk satu periode: menambah, mengubah, mengarsipkan, dan menghapus (bila belum ada jawaban) kolom kustom; memilih tipe, label, teks bantuan, Wajib/Opsional, opsi jawaban, dan validasi dasar; mengatur urutan semua kolom (bawaan dan kustom) dengan seret-lepas atau tombol naik/turun; dan melihat pratinjau langsung. Formulir calon siswa dan formulir panitia menampilkan persis susunan itu, jawabannya tersimpan, tampil di detail pendaftar, dan ikut di laporan Daftar Pendaftar.

## Keputusan
| Tanggal | Keputusan | Oleh |
| --- | --- | --- |
| 2026-10-03 | Tipe kolom: teks singkat, paragraf, angka, tanggal, pilihan tunggal (dropdown), pilihan ganda (kotak centang), **judul bagian**, **unggah berkas** | user |
| 2026-10-03 | Urutan: seret-lepas + tombol naik/turun; dependensi baru `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities` disetujui | user |
| 2026-10-03 | Jawaban kolom kustom ikut menjadi kolom di laporan Daftar Pendaftar PPDB | user |
| 2026-10-03 | Submenu khusus "Formulir" di bawah PPDB | user |
| 2026-10-03 | Tetap per periode; periode baru menyalin formulir periode terakhir (keputusan sebelumnya, tetap berlaku) | user |
| 2026-10-03 | Definisi kolom pindah dari JSON ke tabel `ppdb_form_fields`; `ppdb_periods.form_fields`, `FormFields`, `FieldRequirement`, `SaveFormFields`, `FormFieldsRequest`, panel dan route di Pengaturan dihapus (belum pernah dirilis) | desain |
| 2026-10-03 | Nilai kolom bawaan tetap di kolom `ppdb_applicants` (dipakai pencarian, laporan, `StudentAdmission`); hanya jawaban kustom masuk tabel jawaban | desain |
| 2026-10-03 | Satu tombol "Simpan formulir" untuk seluruh susunan (pola `SavePaths`); hapus kolom adalah aksi terpisah dengan konfirmasi | desain |

## Cakupan
**Dalam:** tabel definisi kolom dan jawaban; halaman penyusun + pratinjau; perender formulir bersama untuk calon siswa, panitia, detail; validasi dinamis; unggah, unduh, dan hapus berkas; kolom laporan; salinan ke periode baru; tes; dokumentasi.

**Di luar:** logika bersyarat/lompat bagian, halaman berganda, banyak berkas per kolom, pilihan "Lainnya" berisi teks, skala/kisi, duplikasi formulir antar sekolah, mengubah label kolom bawaan, mengubah `DocumentCheck` (verifikasi berkas tetap keputusan panitia), kolom berbeda per jalur.

**Aturan perilaku**
- Kolom bawaan (10): `path_id`, `name`, `gender` **terkunci** (selalu wajib, tidak bisa diarsipkan, bisa diurutkan dan diberi teks bantuan); `birth_place`, `birth_date`, `nisn`, `origin_school`, `address`, `guardian_name`, `guardian_phone` bisa Wajib/Opsional/diarsipkan. Tidak ada kolom bawaan yang bisa dihapus. Gelombang (hanya di formulir panitia) tetap di atas, di luar susunan.
- Arsip = tidak ditanyakan lagi, jawaban lama tetap dan tetap tampil di detail pendaftar; bisa dipulihkan. Hapus hanya untuk kolom kustom tanpa jawaban.
- Tipe kolom terkunci setelah kolom itu punya jawaban; label, bantuan, wajib, opsi, dan validasi tetap bisa diubah (jawaban lama disimpan apa adanya).
- Formulir bisa diubah selama periode berstatus konsep atau berjalan; periode ditutup = hanya-baca.
- Batas: 60 kolom per periode, 30 opsi per kolom, label 150 karakter, bantuan 300.
- Validasi dasar: teks singkat — panjang maksimal, format (bebas / angka saja / email / nomor telepon); paragraf — panjang maksimal; angka — minimal, maksimal; tanggal — boleh/tidak di masa depan; pilihan — nilai harus salah satu opsi; berkas — jenis (PDF, gambar JPG/PNG) dan ukuran maksimal (bawaan 2 MB, paling besar 5 MB), satu berkas per kolom.

## Desain

### Tabel (migrasi baru `modules/Ppdb/database/migrations/0009_01_01_000004_create_ppdb_form_tables.php`; migrasi lama tidak diubah)
- `ppdb_form_fields`: `tenantId()`, `period_id`, `key` (nullable; nama kolom bawaan, null = kustom), `type` (`builtin|text|paragraph|number|date|select|checkboxes|file|section`), `label`, `help` (nullable), `required` (bool), `options` (json nullable), `rules` (json nullable), `sort_order`, `archived_at` (nullable), timestamps. Unique `(tenant_id, period_id, key)`; indeks `(tenant_id, period_id, sort_order)`.
- `ppdb_applicant_answers`: `tenantId()`, `applicant_id`, `field_id`, `value` (text nullable; JSON untuk kotak centang dan berkas: `{path, name, size, mime}`), timestamps. Unique `(tenant_id, applicant_id, field_id)`; indeks `(tenant_id, field_id)`. Tanpa FK selain `tenant_id`.
- Isi awal: untuk tiap periode yang ada, buat 10 baris bawaan dari `ppdb_periods.form_fields` (Off → `archived_at`), lalu `dropColumn('form_fields')`. Ini satu-satunya tempat `DB::table()` dipakai (migrasi, `tenant_id` ditulis eksplisit); kode aplikasi tetap Eloquent.

### Backend (`modules/Ppdb/app`)
- Model `Domain/Models/{FormField,ApplicantAnswer}.php` (baru; `BelongsToTenant`, factory, masuk `deptrac.baseline.yaml`), enum `Domain/Enums/FieldType.php` (baru). `AdmissionPeriod::fields()` (HasMany, urut `sort_order`); kolom dan helper `form_fields`/`formFields()` dihapus.
- `Domain/Support/BuiltinFields.php` (baru; menggantikan `FormFields`): kunci, label, bawaan wajib, aturan nilai, mana yang terkunci.
- `Domain/Actions/SeedFormFields.php` (baru): 10 baris bawaan untuk satu periode; dipanggil dari hook `created` di `AdmissionPeriod::booted()` supaya periode buatan factory dan seeder juga punya formulir. `SavePeriod` (ada): periode baru lalu menyalin susunan periode terakhir (`CopyFormFields`, baru: keadaan kolom bawaan + kolom kustom yang tidak diarsipkan).
- `Domain/Actions/SaveForm.php` (baru; pola `SavePaths`): daftar baris berurutan — baris ber-`id` mengubah, tanpa `id` menambah, urutan daftar = `sort_order`; menolak: periode ditutup, id bukan milik periode, mengubah tipe kolom yang sudah punya jawaban, mengarsipkan/mengubah wajib kolom terkunci, pilihan tanpa opsi, melebihi batas. Semua-atau-tidak dalam satu transaksi. `DeleteFormField.php` (baru): hanya kustom tanpa jawaban.
- `Domain/Support/FormRules.php` (baru): membangun aturan validasi dari kolom aktif — kolom bawaan dengan nama kolomnya, kustom di `answers.{id}`; berkas wajib dianggap terpenuhi bila pendaftar sudah punya berkasnya. `ApplicantFieldRules` memakainya; cara `ApplicantRequest` dan `Account/ApplicationRequest` menemukan periode **tetap** (yang kedua tetap lewat `TenantContext::run($account->tenant_id, …)`).
- `Domain/Actions/SaveAnswers.php` (baru): menyimpan jawaban kustom dan berkas; dipanggil di dalam transaksi `RegisterApplicant`, `UpdateApplicant`, `UpdateOwnApplication`. Berkas lewat `TenantStorage` (`modules/Platform/app/Contracts/TenantStorage.php`, belum pernah dipakai; modul `ppdb`, path `applicants/{applicant}/{field}-{acak}.{ext}`); berkas lama dihapus saat diganti; bila transaksi gagal, berkas yang baru ditulis dihapus. `CancelApplication` (ada) ikut menghapus jawaban dan berkasnya.
- Unduh berkas: panitia `GET /ppdb/pendaftar/{applicant}/berkas/{field}` (`ppdb.view`); calon siswa `GET /calon-siswa/formulir/berkas/{field}` (hanya pendaftaran milik akunnya). Dikirim sebagai lampiran dengan `X-Content-Type-Options: nosniff`; path tidak pernah datang dari request.
- `Http/Controllers/FormController.php` (baru) + `Requests/FormRequest…` (`SaveFormRequest`, baru). Route di grup `can:ppdb.settings.manage` (`modules/Ppdb/routes/web.php`): `GET formulir` (`ppdb.form`, `?periode=`), `PUT formulir/{period}` (`ppdb.form.update`), `DELETE formulir/kolom/{field}` (`ppdb.form.fields.destroy`). Route `settings.form.update` dihapus. Menu: anak "Formulir" (izin `ppdb.settings.manage`) di `PpdbServiceProvider::registerNavigation`, sebelum "Pengaturan".
- Controller yang ada mengirim `fields` (definisi aktif berurutan) dan `answers` menggantikan `formFields`: `ApplicantController::create/show`, `Account/ApplicationController::form`. `ApplicantController::index` tetap mengirim `showOrigin`.
- `Domain/Reports/ApplicantListReport.php`: kolom tambahan = label kolom kustom (bukan bagian) dari periode-periode yang pendaftarnya masuk rentang, digabung menurut label; kotak centang digabung dengan koma, berkas ditulis "Ada".

### Frontend (`modules/Ppdb/resources/js`, aturan `.ai/rules/js.md`, URL lewat Wayfinder)
- `Components/FormRenderer.tsx` (baru; menggantikan `ApplicantFields.tsx`): menggambar formulir dari daftar definisi — kolom bawaan dengan widget yang sekarang, kustom menurut tipe, bagian sebagai judul. Dipakai `Pages/Ppdb/Account/Form.tsx`, `ApplicantForm.tsx`, `ApplicantShow.tsx`, dan pratinjau. Formulir berisi berkas dikirim sebagai multipart; pembaruan memakai POST dengan `_method=put`.
- `Pages/Ppdb/FormBuilder.tsx` (baru): kiri daftar kartu kolom yang bisa diseret (`@dnd-kit/sortable`, pegangan seret + tombol naik/turun untuk keyboard dan HP), tiap kartu dibuka untuk mengedit (tipe, label, bantuan, wajib, opsi, validasi), tombol arsip/pulihkan/hapus, "Tambah kolom" dan "Tambah bagian"; kanan pratinjau langsung dari keadaan lokal (di layar sempit: tab Susun | Pratinjau, `@shared/components/ui/tabs`). Satu tombol "Simpan formulir"; peringatan bila pergi dengan perubahan belum disimpan; pemilih periode seperti di Pengaturan (`ChosenPeriod`).
- UI dasar dari Shared; yang belum ada ditambah ke Shared dengan `npx shadcn@latest add switch` (untuk Wajib). Pilihan tunggal memakai `OptionSelect`, kotak centang memakai `checkbox` yang ada.
- `Pages/Ppdb/Settings.tsx`: panel "Formulir pendaftaran" diganti tautan ke submenu Formulir. `ApplicantShow` menampilkan jawaban kustom (tautan unduh untuk berkas), termasuk jawaban kolom yang sudah diarsipkan.

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Dikerjakan berurutan tanpa berhenti (keputusan user di fase ini); tiap tahap ✅ hanya setelah buktinya lulus.

| Tahap | Cakupan | Status |
| --- | --- | --- |
| 0 | Fondasi: tabel, model, isi awal, kolom bawaan pindah dari JSON ke tabel; perilaku lama tetap | ✅ |
| 1 | Penyusun: submenu, simpan susunan, kolom kustom non-berkas, bagian, urutan, pratinjau | ✅ |
| 2 | Formulir dan jawaban: perender bersama, validasi, simpan jawaban, detail pendaftar | ✅ |
| 3 | Unggah berkas | ✅ |
| 4 | Laporan, salinan periode, tes browser, dokumentasi | ✅ |

### Tahap 0 — Fondasi
- [x] Salin dokumen ini ke `docs/ai/plan/fase-11/ppdb-form-builder-plan.md`, status "Berjalan".
- [x] Migrasi, `FormField`, `ApplicantAnswer`, `FieldType`, `BuiltinFields`, `SeedFormFields` + hook, factory, baseline Deptrac.
- [x] `FormRules` untuk kolom bawaan; hapus `FormFields`, `FieldRequirement`, `SaveFormFields`, `FormFieldsRequest`, route dan panel Pengaturan.
- [x] Tes: `modules/Ppdb/tests/Feature/PpdbFormFieldsTest.php` ditulis ulang ke model baru (perilaku yang sama: bawaan, wajib/opsional/arsip, nilai kolom arsip dibuang, data lama tetap, perbaikan mengikuti periode pendaftar) — **perubahan tes yang disengaja**, tes pengaturan lewat panel lama pindah ke Tahap 1.

**Selesai bila:** `php artisan test --compact modules/Ppdb` hijau; `composer deptrac` 0 pelanggaran.

### Tahap 1 — Penyusun
- [x] `SaveForm`, `DeleteFormField`, `SaveFormRequest`, `FormController`, route, menu.
- [x] `npm install @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities`; `npx shadcn@latest add switch`; `FormBuilder.tsx` dengan pratinjau; `php artisan wayfinder:generate`.
- [x] Tes `PpdbFormBuilderTest.php` (baru): tambah/ubah/urut/arsip/pulihkan, hapus hanya tanpa jawaban, kolom terkunci, tipe terkunci setelah ada jawaban, pilihan tanpa opsi ditolak, batas jumlah, periode ditutup hanya-baca, id periode lain ditolak, `staf-tu`/guru 403, sekolah lain 404.

**Selesai bila:** admin menyusun dan menyimpan formulir; `php artisan test --compact modules/Ppdb` hijau; `npm run types:check` bersih.

### Tahap 2 — Formulir dan jawaban
- [x] `FormRules` untuk kustom, `SaveAnswers`, perubahan tiga action pendaftar, `CancelApplication`; props `fields`/`answers`.
- [x] `FormRenderer.tsx` di empat tempat; `ApplicantFields.tsx` dihapus.
- [x] Tes `PpdbCustomAnswersTest.php` (baru): tiap tipe diterima dan ditolak sesuai validasi, wajib/opsional, jawaban untuk kolom arsip atau kolom periode lain dibuang, urutan di props mengikuti susunan, calon siswa dan panitia, perbaikan, jawaban arsip tetap tampil di detail, isolasi tenant dan antar akun.

**Selesai bila:** jawaban kustom tersimpan dan tampil; `php artisan test --compact modules/Ppdb` hijau.

### Tahap 3 — Unggah berkas
- [x] Tipe `file` di `FormRules`/`SaveAnswers`, penyimpanan `TenantStorage`, dua route unduh, pembersihan saat ganti/batal.
- [x] Tes `PpdbFileAnswerTest.php` (baru; `Storage::fake('local')`, `UploadedFile::fake()`): jenis dan ukuran ditolak, tersimpan di folder sekolahnya, ganti menghapus yang lama, wajib terpenuhi oleh berkas yang sudah ada, unduh hanya oleh panitia sekolah itu dan pemilik akun (akun lain dan sekolah lain 404/403), pembatalan menghapus berkas.

**Selesai bila:** berkas terunggah, terunduh, dan terhapus dengan benar; `php artisan test --compact modules/Ppdb` hijau.

### Tahap 4 — Laporan, salinan, penutup
- [x] `ApplicantListReport` + `CopyFormFields` di `SavePeriod`; perbarui `PpdbInsightTest`, `PpdbSettingsTest`, `PpdbDemoSeederTest` bila perlu.
- [x] Browser `tests/Browser/PpdbTest.php`: skenario ketiga diganti — admin menambah kolom pilihan dan kolom teks, menaikkan urutannya, mengarsipkan NISN, pratinjau berubah, menyimpan; calon siswa mengisi formulir itu; jawaban terlihat di detail pendaftar; `assertNoJavaScriptErrors()`.
- [x] Dokumentasi: `modules/Ppdb/CONTRACT.md` (tabel baru, penyusun, berkas kini ada; `DocumentCheck` tetap tiruan), `docs/architecture/modular-monolith.md`, salinan `AGENTS.md`, memori `project_mockup_first.md`; Log keputusan, Temuan, Hasil akhir di dokumen plan.

**Selesai bila:** semua lulus —
- `php artisan test --compact modules/Ppdb modules/Core tests/Architecture`
- `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/PpdbTest.php` (pindahkan `public/hot` yang basi sementara)
- `composer deptrac` 0 pelanggaran; `vendor/bin/phpstan analyse` tanpa error baru (baseline 29); `vendor/bin/pint --dirty --format agent`; `npm run types:check`; `npm run build`.

## Batas tindakan
**Selalu:** Eloquent + `BelongsToTenant` untuk data tenant; tanpa FK selain `tenant_id`; berkas hanya lewat `TenantStorage`; Pint dan tes modul sebelum menandai tahap ✅; perbarui tabel status dan Log keputusan.
**Tanya dulu:** dependensi selain tiga paket `@dnd-kit` dan komponen shadcn `switch`; mengubah kontrak Core/Platform; menambah tipe kolom atau logika bersyarat; menaikkan batas berkas di atas 5 MB.
**Jangan:** mengandalkan `ResolveTenant` di halaman akun; menerima path berkas, `account_id`, atau sekolah dari request; menyajikan berkas dari disk publik; menghapus jawaban saat kolom diarsipkan; mengubah migrasi yang sudah di-commit; commit/push tanpa diminta.

## Risiko
- Batas unggah server hosting (`upload_max_filesize`, `post_max_size`) bisa di bawah 5 MB — dicek saat rilis; pesan galat validasi tetap jelas.
- Berkas memakai kuota disk shared hosting; tidak ada pembersihan otomatis selain saat pendaftaran dibatalkan.
- `@dnd-kit` dengan React 19 + React Compiler: bila bermasalah, penyusun tetap berfungsi dengan tombol naik/turun dan temuan dicatat.
- Setelah rilis: `php artisan migrate` (tanpa `roles:sync`).

### Log keputusan (pelaksanaan)
- 2026-10-03 — Rencana disetujui; `public/hot` yang basi dipindah sementara saat tes browser seperti sebelumnya.
- 2026-10-03 — `SaveForm` menolak daftar yang melewatkan kolom (halaman usang) alih-alih menaruhnya di urutan acak.
- 2026-10-03 — Aturan wajib berkas memakai berkas yang sudah ada: `FormRules::forPeriod($period, $applicant)`.
- 2026-10-03 — Helper tes `customField()`/`storedAnswer()` dipindah ke `Support/helpers.php` agar tidak mendaftarkan tes dua kali.

### Temuan
- Unduhan akun pusat memakai id kolom biasa (bukan model terikat): binding model bertenant tidak bisa dilakukan sebelum `TenantContext::run`.
- Test `forceFill` pada model basi tidak menulis apa pun bila nilainya sama dengan nilai awal model; pakai `update` lewat query.
- shadcn `add switch` memasang paket `cn` yang nyasar dan `pnpm-lock.yaml`; keduanya dihapus sesuai aturan `.ai/rules/js.md`.

### Hasil akhir
Semua tahap selesai. Bukti: `php artisan test --compact modules/Ppdb modules/Core tests/Architecture` (716 lulus), `tests/Browser/PpdbTest.php` (3 lulus), `composer deptrac` 0 pelanggaran, PHPStan 29 (baseline), Pint, `npm run types:check`, `npm run build`. Catatan: server tes browser tidak mem-parsing multipart, jadi form hanya dikirim multipart bila ada berkas (Inertia otomatis); unggah berkas dicakup tes feature, bukan browser.
