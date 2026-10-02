# Kolom formulir PPDB yang bisa diatur sekolah

> **Status:** Selesai · **Dibuat:** 2026-10-03 · **Branch:** `feat/ppdb` (lanjutan fase 11; belum di-push)
> **Lokasi dokumen saat dieksekusi:** `docs/ai/plan/fase-11/ppdb-form-fields-plan.md` (baru) — menyalin isi berkas ini adalah task pertama.

## Context
Formulir pendaftaran PPDB sekarang sama untuk semua sekolah dan semua jenjang (`modules/Ppdb/app/Http/Requests/ApplicantFieldRules.php`): jalur, nama, jenis kelamin, tempat lahir (opsional), tanggal lahir, NISN (opsional), asal sekolah, alamat (opsional), nama wali, telepon wali. Sekolah dengan kebutuhan berbeda (mis. SD tidak punya NISN/asal sekolah SMP) tidak bisa menyesuaikan. User memilih: **admin mengaktifkan/mematikan kolom tertentu**.

## Goal
Setelah ini admin sekolah, di PPDB › Pengaturan, menentukan untuk setiap kolom pilihan formulir: **Wajib**, **Opsional**, atau **Tidak dipakai**, per periode PPDB. Formulir calon siswa, formulir panitia, validasi server, dan halaman detail mengikuti pengaturan itu. Periode baru menyalin pengaturan periode sebelumnya.

## Keputusan (user, 2026-10-03)
- Berlaku **per periode** (periode baru menyalin dari periode terakhir; pendaftar lama tidak terpengaruh tahun berikutnya).
- Tiga keadaan per kolom: Wajib / Opsional / Tidak dipakai.
- Hanya mengatur kolom yang ada; **tidak** menambah kolom kustom (di luar cakupan).

## Cakupan
**Selalu ada (tidak bisa diatur):** `path_id`, `name`, `gender` — dipakai seleksi dan `StudentAdmission` (NIS, nama, jenis kelamin wajib di Core).
**Bisa diatur** (bawaan = perilaku sekarang, sehingga periode lama tidak berubah):
| Kolom | Bawaan |
| --- | --- |
| `birth_place` | Opsional |
| `birth_date` | Wajib |
| `nisn` | Opsional |
| `origin_school` | Wajib |
| `address` | Opsional |
| `guardian_name` | Wajib |
| `guardian_phone` | Wajib |

**Di luar cakupan:** kolom kustom/jenis isian baru, pengaturan jenjang, pilihan jurusan, unggah berkas, kolom berbeda per jalur/gelombang.

## Desain
**Penyimpanan.** Migrasi baru `modules/Ppdb/database/migrations/0009_01_01_000003_add_form_fields_to_ppdb_periods.php` (baru; migrasi lama sudah di-commit, jangan diubah):
- `ppdb_periods.form_fields` JSON nullable (null = bawaan).
- `ppdb_applicants`: `birth_date`, `origin_school`, `guardian_name`, `guardian_phone` menjadi nullable (`->change()`), karena kolom yang dimatikan tidak diisi. `down()` mengembalikannya.

**Aturan di satu tempat** — `modules/Ppdb/app/Domain/Support/FormFields.php` (baru) dan enum `Domain/Enums/FieldRequirement.php` (baru: `Required|Optional|Off`, label Indonesia):
- daftar kolom yang bisa diatur + label + bawaan;
- `forPeriod(AdmissionPeriod): array<string, FieldRequirement>` — nilai tersimpan ditimpa di atas bawaan, kunci asing diabaikan;
- `rules(array $fields): array` — Wajib → `required`, Opsional → `nullable`, Tidak dipakai → tidak ada aturan (kolom dibuang dari `validated()`), plus aturan tipe/panjang yang sekarang;
- `AdmissionPeriod`: cast `form_fields` array + helper `formFields()`.
`ApplicantFieldRules` memanggil `FormFields` alih-alih daftar tetap (nama atribut dan pesan tetap).

**Action** `Domain/Actions/SaveFormFields.php` (baru): memvalidasi nilai, menyimpan ke periode. Kolom yang dimatikan **tidak menghapus** data pendaftar yang sudah ada (data tetap, hanya disembunyikan dari formulir). `SavePeriod` (ada): periode baru menyalin `form_fields` periode terakhir.

**Request.** Aturan bergantung pada periode:
- `ApplicantRequest` (panitia): periode = `{applicant}` di route bila ada, selain itu `AdmissionPeriod::active()`.
- `Account/ApplicationRequest` (calon siswa): `rules()` jalan di halaman pusat tanpa tenant, jadi periode dicari di dalam `TenantContext::run($account->tenant_id, …)` — periode aktif untuk mengirim, periode pendaftar sendiri untuk perbaikan. Jangan memakai `ResolveTenant`.
- Baru: `Requests/FormFieldsRequest.php` untuk pengaturan.

**Controller/route.** `PUT pengaturan/periode/{period}/formulir`, nama `ppdb.settings.form.update`, di grup `can:ppdb.settings.manage` (`modules/Ppdb/routes/web.php` baris ~107). `SettingsController` mengirim `formFields` (kunci, label, keadaan, `locked`) ke `Settings`. `ApplicantController::create/show` dan `Account/ApplicationController::form` mengirim `formFields` keadaan periode terkait. Kolom pada daftar pendaftar `origin` hanya muncul bila `origin_school` tidak dimatikan.

**Frontend** (`modules/Ppdb/resources/js`, aturan `.ai/rules/js.md`; URL lewat Wayfinder):
- `Components/ApplicantFields.tsx`: prop `fields`; kolom Tidak dipakai tidak digambar, Opsional diberi "(opsional)", Wajib tanpa tanda. Tipe `ApplicantData` tetap.
- `Pages/Ppdb/Settings.tsx`: panel "Formulir pendaftaran" — baris per kolom dengan `OptionSelect` (Wajib/Opsional/Tidak dipakai) + tombol Simpan; tiga kolom tetap tampil sebagai "Selalu dipakai".
- `Pages/Ppdb/ApplicantShow.tsx`: sembunyikan kolom yang dimatikan **kecuali** bila pendaftar itu sudah punya isinya (data lama).
- `Pages/Ppdb/Account/Form.tsx` dan `ApplicantForm.tsx` meneruskan `formFields`.

**Dipakai ulang:** `OptionSelect`, `Panel` (`@shared/components/page-parts`), pola `SavePaths`/`PathsRequest` untuk action + request pengaturan, `ChosenPeriod`, `helpers.php` tes Ppdb.

**Tidak berubah:** `EnrollApplicant` (`NewStudent` sudah menerima birthDate/wali/NISN `null`), laporan `ApplicantListReport` (sel kosong untuk kolom null), `ApplicantChecks` (NISN unik hanya bila terisi).

## Tahapan
Satu tahap, dikerjakan berurutan tanpa berhenti (mengikuti keputusan user sebelumnya).

- [x] Salin dokumen ini ke `docs/ai/plan/fase-11/ppdb-form-fields-plan.md`.
- [x] Migrasi + `FieldRequirement` + `FormFields` + cast/helper `AdmissionPeriod`.
- [x] `SaveFormFields`, salinan di `SavePeriod`, `FormFieldsRequest`, route, `SettingsController`.
- [x] Aturan dinamis di `ApplicantFieldRules`, `ApplicantRequest`, `Account/ApplicationRequest`; kirim `formFields` dari tiga controller.
- [x] React: `ApplicantFields`, `Settings`, `ApplicantShow`, `ApplicantForm`, `Account/Form`, kolom `Applicants`; `php artisan wayfinder:generate`.
- [x] Tes feature (`modules/Ppdb/tests/Feature/PpdbFormFieldsTest.php`, baru): bawaan = perilaku lama; Wajib menolak kosong; Opsional menerima kosong; Tidak dipakai membuang nilai kiriman dan tidak menimpa data lama; periode baru menyalin; panitia dan calon siswa sama-sama mengikuti periodenya; perbaikan (status revisi) mengikuti periode pendaftar, bukan periode aktif; `staf-tu`/guru 403 pada pengaturan; sekolah lain tidak terpengaruh. Sesuaikan `PpdbPagesTest`/`PpdbSettingsTest` bila props berubah.
- [x] Browser: tambah ke `tests/Browser/PpdbTest.php` — admin mematikan NISN dan asal sekolah, kolom hilang dari formulir calon siswa dan pendaftaran terkirim.
- [x] Dokumentasi: `modules/Ppdb/CONTRACT.md`, bagian PPDB di `docs/architecture/modular-monolith.md` dan salinan `AGENTS.md`; catat di plan (Log keputusan, Temuan).

**Selesai bila:** admin mengubah keadaan kolom, formulir dan validasi mengikutinya, dan semua bukti lulus:
- `php artisan test --compact modules/Ppdb modules/Core tests/Architecture`
- `vendor/bin/pest -c phpunit.e2e.xml tests/Browser/PpdbTest.php` (pindahkan `public/hot` yang basi sementara, seperti sebelumnya)
- `composer deptrac` → 0 pelanggaran; `vendor/bin/phpstan` tanpa error baru; `vendor/bin/pint --dirty --format agent`; `npm run build`.

## Batas tindakan
**Selalu:** Eloquent saja untuk data tenant; tanpa FK baru; jalankan Pint dan tes modul sebelum selesai; tidak menghapus/melemahkan tes.
**Tanya dulu:** menambah kolom kustom, mengubah kontrak Core (`NewStudent`), mengubah migrasi yang sudah di-commit.
**Jangan:** menghapus data pendaftar saat kolom dimatikan; mengandalkan `ResolveTenant` di halaman akun; commit/push tanpa diminta.

## Risiko
- `->change()` pada kolom SQLite (tes) membangun ulang tabel; verifikasi migrasi di SQLite dan MySQL (hosting).
- Setelah rilis: `php artisan migrate` (tanpa `roles:sync`; tidak ada izin baru).

## Catatan pelaksanaan

### Log keputusan
- 2026-10-03 — Plan disetujui; cakupan per periode dan tiga keadaan atas keputusan user.
- 2026-10-03 — Helper tes `applicantForm()` dipindah dari `ApplicantTest.php` ke `Support/helpers.php` agar dipakai bersama (tes tidak dihapus).
- 2026-10-03 — `ApplicantController::index` mengirim `showOrigin` agar kolom Asal sekolah di daftar mengikuti formulir periode.

### Temuan
- Heredoc panjang di Bash gagal lagi; berkas diubah dengan Edit.
- `npm run check` melaporkan masalah format di 231 berkas yang sudah ada sebelumnya (bukan dari perubahan ini); `npm run types:check` bersih.

### Hasil akhir
Semua bukti lulus: `php artisan test --compact modules/Ppdb` (213), `modules/Core tests/Architecture` (445), `PpdbTest` browser (3), `composer deptrac` 0 pelanggaran, PHPStan 29 (baseline, 0 di kode baru), Pint, `npm run build`, `types:check`. Spec Playwright tidak diubah.
