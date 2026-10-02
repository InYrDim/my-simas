# Tindak lanjut PPDB: pengaman periode kosong, "Belum ditempatkan" di Penempatan Siswa, WA hasil seleksi, pembaruan PRODUCT.md

> **Status:** Selesai · **Dibuat:** 2026-10-03 · **Branch:** `feat/ppdb` (setelah `f9f7d6a`)
> **Lokasi dokumen saat dieksekusi:** `docs/ai/plan/fase-11/ppdb-followup-plan.md` (baru) — menyalin isi berkas ini adalah task pertama.

## Context
Pemeriksaan fase lain (3, 5–10) terhadap PPDB menemukan tiga hal: (1) periode yang disisipkan tanpa event model tidak punya baris formulir sehingga formulir calon siswa kosong tanpa pesan (spec Playwright sempat gagal karena ini; helper E2E sudah diperbaiki, **belum di-commit**: `tests/E2E/support/database.ts`); (2) siswa hasil daftar ulang tidak punya kelas dan **tidak muncul di Akademik › Penempatan Siswa** karena halaman itu hanya bekerja dari rombel asal; (3) `PRODUCT.md` masih menulis PPDB "belum dibangun" dan "tanpa registrasi publik"; dan (4) atas permintaan user ("sekalian dengan notif WA-nya") pemberitahuan WhatsApp hasil seleksi, yang sebelumnya ditunda dan hanya berupa tiruan `ResultAnnouncer`, ikut dibangun.

## Keputusan (user, 2026-10-03)
- Keempatnya dikerjakan semua. Pemberitahuan WA hasil seleksi kini dibangun (menggantikan keputusan 2026-10-02 yang menundanya; kontrak Core baru disetujui lewat rencana ini).
- PRODUCT.md dikerjakan lewat skill `/impeccable` (alur `init`: perbarui, jangan menimpa; jangan menyentuh DESIGN.md atau gaya visual).
- Calon siswa dicatat di **Users sebagai pemangku kepentingan keempat** (prioritas terendah).
- "Planned next" ditulis ulang hanya berisi yang benar-benar belum ada.

## Batasan
**Di luar cakupan:** halaman hasil publik, unggah berkas pengganti `DocumentCheck`, status terkirim/dibaca (butuh webhook), pengingat berulang, balasan pendaftar; mengubah alur Naikkan/Pindahkan/Luluskan yang ada; menempatkan siswa ke rombel tahun ajaran yang sudah selesai; tombol "tempatkan" langsung dari halaman PPDB; DESIGN.md; kontrak lintas modul baru (semua di Core dan Ppdb sendiri-sendiri).
**Aturan yang mengikat:** Ppdb dan Core hanya bicara lewat `app/Contracts` (tidak ada impor baru); tanpa FK baru; Eloquent + `BelongsToTenant`; tes tidak dihapus (sumber: `AGENTS.md`).

## 1. Pengaman periode kosong (Ppdb)
Masalah: `FormFieldList::for()` dan `FormController::show()` membaca `ppdb_form_fields` apa adanya; periode tanpa baris menampilkan formulir kosong.
- `modules/Ppdb/app/Domain/Actions/SeedFormFields.php` — tambah `ensure(AdmissionPeriod $period): void`: bila periode belum punya satu pun baris, panggil `handle()`; tangkap `UniqueConstraintViolationException` (dua permintaan bersamaan, indeks unik `(tenant_id, period_id, key)` sudah menjaga).
- Dipanggil di titik baca periode: `modules/Ppdb/app/Domain/Queries/FormFieldList.php::for()`, `modules/Ppdb/app/Http/Controllers/FormController.php::show()`, dan `modules/Ppdb/app/Domain/Actions/SaveForm.php::handle()` (sebelum membaca `fields()`). `FormRules` tetap tidak menulis apa pun (sudah jatuh ke bawaan bila baris kosong).
- Tes — `modules/Ppdb/tests/Feature/PpdbFormFieldsTest.php`: periode dibuat dengan `AdmissionPeriod::withoutEvents(...)` (nol baris) → halaman Tambah pendaftar, builder, dan formulir calon siswa menampilkan 10 isian bawaan dan baris tersimpan; panggilan kedua tidak menggandakan; sekolah lain tidak terpengaruh.
- Rapikan `tests/E2E/support/database.ts`: hapus penyisipan 10 baris manual (kini dijamin pengaman) dan jalankan ulang `npx playwright test ppdb-account` sebagai bukti.

## 2. "Belum ditempatkan" di Penempatan Siswa (Core)
Perilaku: sumber khusus "Belum ditempatkan" berisi siswa **aktif tanpa kelas**; satu-satunya tindakan adalah **Tempatkan ke rombel** (kelas tahun ajaran aktif atau yang akan datang). Naikkan/Pindahkan/Luluskan tidak berlaku untuk mereka. Riwayat kelas ditulis `SaveStudent` seperti biasa.
- `modules/Core/app/Domain/Enums/PlacementAction.php` — tambah `Assign = 'assign'`.
- `modules/Core/app/Domain/Actions/PlaceStudents.php` — tambah `assign(ClassGroup $target, array $studentIds): int`: semua siswa harus ditemukan, aktif, dan `class_id` kosong; rombel tujuan harus milik tahun ajaran yang belum selesai (aktif atau mulai setelah/pada tahun aktif; pakai `AcademicYear::isActive()` dan `start_date`); semua dicek sebelum satu pun diubah; satu transaksi lewat `SaveStudent::handle($student, ['class_id' => ...])`. `handle()` yang ada tidak diubah.
- `modules/Core/app/Http/Requests/PlacementRequest.php` — `source_class_id` menjadi `required_unless:action,assign` (nullable); `target_class_id` wajib untuk `assign`; pesan Indonesia.
- `modules/Core/app/Http/Controllers/PlacementController.php` — `index`: `?kelas=belum` memilih sumber "belum ditempatkan" (props `sourceClassId: null`, `unplaced: true`, `students` = aktif tanpa kelas); selalu kirim `unplacedCount`. Pilihan bawaan tanpa `?kelas` **tidak berubah** (kelas pertama tahun aktif). `store`: cabang `Assign` memanggil `assign()` dan menjawab "{n} siswa ditempatkan ke {rombel}.".
- `modules/Core/resources/js/Pages/Core/Academic/Placement/Index.tsx` — (a) bila `unplacedCount > 0` tampilkan banner "N siswa belum ditempatkan" dengan tautan ke `?kelas=belum`; (b) pemilih "Rombel asal" mendapat opsi pertama "Belum ditempatkan (N)"; (c) di mode ini sembunyikan "Dari tahun ajaran" dan "Tindakan", tampilkan hanya "Rombel tujuan" (kelas tahun aktif/mendatang) dan tombol "Tempatkan"; pratinjau "N siswa belum ditempatkan akan ditempatkan ke X"; keadaan kosong "Semua siswa aktif sudah punya kelas." Gunakan komponen Shared yang ada (`OptionSelect`, `Panel`, `DataTable`, `Alert`; aturan `.ai/rules/js.md`), URL lewat Wayfinder. Mode ini tetap bisa dibuka walau sekolah belum punya rombel (tujuan kosong → pesan "Buat kelas di Master Data lebih dulu").
- Tes — `modules/Core/tests/Feature/AcademicPlacementTest.php` (tambah; yang ada tidak diubah): daftar belum ditempatkan hanya siswa aktif tanpa kelas; menempatkan menulis `class_id` dan riwayat tahun itu; siswa yang sudah berkelas / lulus / tahun ajaran selesai ditolak dan tidak ada yang berubah; siswa sekolah lain ditolak; guru bisa melihat tetapi 403 menulis; `unplacedCount` benar. Tes browser `tests/Browser/` hanya bila sudah ada skenario Penempatan (cek dulu; bila tidak ada, cukup feature + Playwright PPDB: daftar ulang → muncul di "Belum ditempatkan").
- Dokumen: `modules/Core/CONTRACT.md` (satu kalimat), dan koreksi `docs/ai/plan/fase-11/ppdb-plan.md` langkah 7 + pesan di `modules/Ppdb/resources/js/Pages/Ppdb/ApplicantShow.tsx` (panel Daftar ulang) agar menunjuk "Akademik › Penempatan Siswa › Belum ditempatkan".

## 3. Pemberitahuan WhatsApp hasil seleksi (Core + Ppdb)
Masalah: `GuardianNotifier` hanya mengenal `student_id`, sedangkan pendaftar belum menjadi siswa. Solusi: kontrak Core kedua untuk penerima yang bukan siswa; Ppdb mendaftarkan jenis pemberitahuannya dan mengganti tiruan `ResultAnnouncer`.
- **Core — kontrak baru** (`modules/Core/app/Contracts`): `ContactNotifier::notify(ContactNotice $notice): void` dan DTO `DTOs/ContactNotice` (`kind`, `recipientName`, `?phone`, `subjectName`, `variables`). Aturan sama dengan `GuardianNotifier`: kind tak terdaftar/modul nonaktif → `UnknownNoticeKindException`; kind dimatikan sekolah → tidak ada yang terjadi dan tidak dicatat; bila nyala, pesan dicatat dan diantre walau tanpa nomor (log berstatus "tanpa penerima"). `subjectName` mengisi `{nama_siswa}`, `recipientName` mengisi `{nama_wali}`.
- **Core — implementasi** `Infrastructure/Whatsapp/DefaultContactNotifier.php` (baru; meniru `DefaultGuardianNotifier` tanpa mencari siswa; memakai `DefaultNoticeRegistry`, `WhatsappNoticeSetting`, `NoticeTemplate::fill`, `QueueWhatsappMessage::handle(..., studentId: null)`); bind di `CoreServiceProvider`.
- **Ppdb — jenis pemberitahuan** `Domain/Notifications/ResultNotices.php` (pola `modules/Attendance/app/Domain/Notifications/AttendanceNotices.php`): `ppdb.result`, penerima "Wali pendaftar", templat bawaan "Yth. {nama_wali}, hasil seleksi PPDB {nama_sekolah} untuk {nama_siswa} (no. {nomor}, jalur {jalur}): {hasil}. {keterangan}", variabel `nomor`, `jalur`, `hasil`, `keterangan`. Didaftarkan lewat `NoticeRegistry` di `PpdbServiceProvider` (matikan-nyalakan oleh sekolah di Integrasi › WhatsApp; mulai dalam keadaan mati). Keterangan menurut keputusan: Diterima → "Silakan melakukan daftar ulang sesuai jadwal sekolah."; Cadangan → "Anda masuk daftar cadangan; sekolah akan menghubungi bila ada kursi."; Tidak diterima → "Terima kasih telah mendaftar."
- **Ppdb — pengganti tiruan** `Infrastructure/Notifications/WhatsappResultAnnouncer.php` (baru; implementasi `ResultAnnouncer`): kirim `ContactNotice` ke `guardian_name ?? "Orang tua/wali {nama}"` dan `guardian_phone`; tangkap `UnknownNoticeKindException` → `false`; kembalikan `true` bila ada nomor. Bind di `PpdbServiceProvider` menggantikan `SilentResultAnnouncer` (hapus kelas itu). Pemanggil tidak berubah: `PublishResults` (setelah transaksi, per pendaftar berkeputusan) dan `SaveSelection` (naik dari cadangan setelah pengumuman). Pendaftar yang kolom telepon walinya diarsipkan/kosong tercatat "tanpa penerima".
- **Core — "Segera hadir":** kosongkan `ppdb.result` dari `modules/Core/config/notices.php` (`upcoming` menjadi `[]`); tes Core yang memakai `ppdb.result` sebagai contoh "Segera hadir" (`GuardianNotifierTest`, baris ~208 dan ~218) memakai `config(['notices.upcoming' => [...]])` sendiri — perubahan perilaku disengaja, dicatat di Log keputusan.
- **Tes:** Core — `ContactNotifierTest` (baru): mati → tidak ada log; nyala → tercatat dan diantre dengan variabel terisi; tanpa nomor → "tanpa penerima"; kind modul nonaktif → exception; isolasi tenant; templat milik sekolah dipakai. Ppdb — `ResultNoticeTest` (baru; `Queue::fake()`, fake `ContactNotifier` perekam): pengumuman mengirim satu pemberitahuan per pendaftar berkeputusan dengan keterangan yang tepat, yang `pending` tidak, naik dari cadangan memberi tahu sekali, tidak mengumumkan dua kali, kind mati → tak ada pesan; sekolah lain tidak terpengaruh. `SelectionTest` tetap memakai `RecordingResultAnnouncer`. Tes browser/E2E tidak mengirim pesan sungguhan (gateway selalu di-fake).
- **Dokumen:** `modules/Core/CONTRACT.md` (kontrak `ContactNotifier`), `modules/Ppdb/CONTRACT.md` (tiruan `ResultAnnouncer` diganti, `DocumentCheck` tetap tiruan), `docs/architecture/modular-monolith.md` dan `AGENTS.md` (bagian PPDB: WA hasil kini ada), memori `project_mockup_first.md`.

## 4. Pembaruan PRODUCT.md (skill `/impeccable`, alur `init`)
- Jalankan sekali `<skill-base-dir>/scripts/impeccable context` (basis: `.claude/skills/impeccable`; di Windows tanpa `sh` pakai `impeccable.cmd`); ikuti arahannya, jangan diulang. Bila peluncur gagal, kirim pesan "Context loading did not run; I'll read the existing project context directly." sebelum alat berikutnya, lalu baca PRODUCT.md langsung.
- Perbarui `PRODUCT.md` **di tempat** (jangan menimpa; salin komentar `<!-- impeccable:product-schema 1 -->` apa adanya; jangan menulis DESIGN.md, gaya visual, atau klaim fiktif; Bahasa tetap Inggris seperti berkas):
  - **Users:** tambah pengguna keempat — calon siswa/orang tua pendaftar PPDB (HP, jaringan lambat, akun pusat sendiri, bergabung ke satu sekolah dengan kode, mengisi formulir lalu memantau hasil); revisi kalimat "An applicant is not a user" agar hanya berlaku untuk pemohon sekolah.
  - **Product Purpose / out of scope:** hapus "attendance and PPDB as modules, public self-registration" dari daftar di luar cakupan; yang tetap di luar: pembayaran sungguhan, mesin pusat notifikasi.
  - **Operating Context:** "no public registration and no self-signup" menjadi: pengguna sekolah tetap tanpa registrasi publik; ada dua registrasi publik — pemohon sekolah dan calon siswa PPDB (honeypot, throttle, jawaban generik); tambah ritual PPDB singkat (akun → kode sekolah → formulir yang disusun sekolah → verifikasi → seleksi → pengumuman → daftar ulang); catat WhatsApp per sekolah (termasuk hasil seleksi PPDB), akun siswa/guru, impor, statistik & laporan sebagai bagian yang ada; tulis ulang "Planned next" hanya berisi yang belum ada: job kedaluwarsa modul, verifikasi berkas PPDB yang sungguhan (kini tiruan), `ppdb` masuk paket langganan, penentuan paket yang memuat Absensi.
  - **Capabilities and Constraints:** "Public surface: exactly one unauthenticated page" menjadi dua kelompok halaman publik (formulir pemohon sekolah di host pusat; halaman akun calon siswa `/calon-siswa/*`); tambahkan modul yang ada (Core master data, akademik, Absensi, PPDB) dan UI peran/izin serta Beranda.
  - **Evidence on Hand:** tambah `docs/ai/plan/fase-3` sampai `fase-11` dan `CONTRACT.md` Attendance/Ppdb sebagai rujukan; tetap nyatakan tidak ada bukti pelanggan.
  - Biarkan Brand, Principles, Accessibility tidak berubah kecuali satu penyesuaian prinsip bila ada yang bertentangan (mis. "A human approves…" tetap benar untuk sekolah, bukan untuk calon siswa — tambah satu klausa).
- Gerbang selesai: berkas ada, skema komentar utuh, tidak ada bagian visual; ringkas apa yang diperbarui dan apa yang sengaja dibiarkan terbuka. Langkah 5 `init` (`buildPath`/live) dilewati — bukan permintaan ini.

## Tahapan (berurutan, tanpa berhenti)
| Tahap | Cakupan | Status |
| --- | --- | --- |
| 0 | Salin dokumen ini ke `docs/ai/plan/fase-11/ppdb-followup-plan.md` | ✅ |
| 1 | Pengaman periode kosong + tes + rapikan helper E2E | ✅ |
| 2 | Belum ditempatkan di Penempatan Siswa + tes + dokumen | ✅ |
| 3 | WA hasil seleksi: `ContactNotifier` (Core), jenis `ppdb.result` + pengumuman nyata (Ppdb) | ✅ |
| 4 | PRODUCT.md lewat `/impeccable` (setelah tahap 1–3 agar isinya benar) | ✅ |

**Selesai bila:**
- WA hasil: pengumuman menaruh satu pesan antrean per pendaftar berkeputusan di log WhatsApp sekolah yang menyalakan `ppdb.result`, tanpa memanggil gateway sungguhan;
- `php artisan test --compact modules/Ppdb modules/Core tests/Architecture` hijau;
- `npx playwright test ppdb-account` hijau (jalankan dengan `public/hot` yang basi dipindah sementara, lalu kembalikan);
- `composer deptrac` 0 pelanggaran; `vendor/bin/phpstan analyse` tetap 29 (baseline); `vendor/bin/pint --dirty --format agent`; `npm run types:check`; `npm run build`;
- `PRODUCT.md` memuat skema yang sama dan tidak menyebut PPDB sebagai "belum dibangun".

## Batas tindakan
**Selalu:** Pint dan tes modul sebelum menandai tahap; perbarui tabel status; catat penyimpangan.
**Tanya dulu:** menambah dependensi; mengubah kontrak Core/Platform yang sudah ada (menambah `ContactNotifier` sudah disetujui); menyentuh DESIGN.md.
**Jangan:** memanggil gateway WhatsApp sungguhan di tes (`Http::fake` + `Http::preventStrayRequests`); mengirim pesan bila sekolah belum menyalakan kind-nya; menyimpan kunci sesi WA di DTO/log; commit/push tanpa diminta; menghapus atau melemahkan tes; mengubah alur Naikkan/Pindahkan/Luluskan; memakai `DB::table()` untuk data tenant di kode aplikasi.

## Catatan pelaksanaan

### Log keputusan
- 2026-10-03 — Tes Core yang memakai `ppdb.result` sebagai entri "Segera hadir" (`GuardianNotifierTest`, dua tes) kini memakai kunci buatan `uji.soon` lewat `config(["notices.upcoming" => ...])`, karena Ppdb mendaftarkan `ppdb.result` dan `config/notices.php` kini kosong. Perubahan perilaku disengaja.
- 2026-10-03 — Helper tes `selectionSchool()`/`selectionBody()` dipindah ke `modules/Ppdb/tests/Feature/Support/helpers.php` agar tidak mendaftarkan tes dua kali lewat `require_once` berkas tes.
- 2026-10-03 — Tes browser tidak ditambah untuk Penempatan Siswa (belum ada skenario Penempatan); cakupan: tes feature (`AcademicUnplacedPlacementTest`, 9 tes) dan Playwright PPDB.

### Temuan
- `Model::withoutEvents()` ikut mematikan hook `tenant_id`; periode "tanpa baris formulir" disimulasikan dengan menghapus barisnya.
- `SilentResultAnnouncer` dihapus; `DocumentCheck` tetap satu-satunya tiruan di Ppdb.
- `git stash` terketik tanpa sengaja saat pemeriksaan dan langsung dikembalikan dengan `git stash pop`; tidak ada perubahan hilang (stash lama di `main` tidak disentuh).

### Hasil akhir
Semua tahap selesai. Bukti: `php artisan test --compact modules/Ppdb modules/Core tests/Architecture` (744 lulus), `npx playwright test ppdb-account` (lulus, helper E2E tidak lagi menyisipkan baris formulir), `composer deptrac` 0 pelanggaran, PHPStan 29 (baseline), Pint, `npm run types:check`, `npm run build`. `PRODUCT.md` diperbarui lewat alur `init` Impeccable (skema komentar utuh, DESIGN.md tidak disentuh).
