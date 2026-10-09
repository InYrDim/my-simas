# Plan: Template kartu siswa untuk QR statis

## Context
QR statis sudah bisa dicetak dari Warga Sekolah › Siswa (commit `bb2bded`), tapi hasilnya
kartu QR polos. Sekolah yang sudah punya desain kartu siswa (gambar) ingin QR-nya
ditempel langsung di kartu itu. Tujuan: admin mengunggah gambar kartu, menandai di mana
QR diletakkan (placeholder), lalu cetak per siswa / semua memakai kartu itu.

## Keputusan desain (rekomendasi)
1. **Komposit di browser, bukan di server.** Halaman cetak (`StaticQr.tsx`) sudah merender
   QR sebagai SVG (`qrcode.react`). Kartu = `<img>` gambar template + `QRCodeSVG`
   diposisikan absolut dengan persentase. Tidak butuh dependensi baru (AGENTS.md melarang
   tanpa persetujuan), QR tetap vektor tajam, tidak ada GD/Imagick.
2. **Gambar pakai `<img>`, bukan CSS background**, supaya ikut tercetak tanpa pengguna
   mencentang "Background graphics" di dialog print.
3. **Placeholder disimpan dalam persen** (`x`, `y`, `size` = % dari lebar gambar), jadi
   tidak bergantung resolusi gambar. Kotak selalu persegi (QR harus persegi).
4. **Satu template per sekolah**, hanya sisi depan, hanya QR (tanpa teks nama/NIS di v1).
5. **Ukuran fisik kartu** disimpan (`card_width_mm`, default 85,6 mm = CR80); tinggi
   mengikuti rasio gambar. Cetak ditata grid di A4, `break-inside-avoid`.
6. **Editor = halaman sendiri** (kanvas visual drag, bukan 2–4 field), sesuai aturan UX
   di `.ai/rules/js.md`; dibuka dari Pengaturan Absensi (sebelah opsi QR statis) dan dari
   header halaman cetak. Disembunyikan bagi yang tak punya izin.

## Cetak dalam modal (perubahan tambahan)
Cetak dari Siswa tidak lagi membuka tab baru; ini perluasan halaman Siswa, jadi sesuai
`.ai/rules/js.md` ("modal untuk perpanjangan halaman") dipakai modal.
- `Core/.../Students/Index.tsx`: satu `Dialog` Shared di tingkat halaman (tidak bersarang).
  Tombol "Cetak semua QR statis" dan "Cetak QR statis" per baris kini membuka modal,
  bukan `<a target="_blank">`. Judul: "QR statis — {nama}" / "QR statis — semua siswa".
- Isi modal = `<iframe>` ke URL aksi (`allUrl` / `studentUrl{id}`) ditambah `?embed=1`,
  dengan skeleton selama memuat. Core tetap tidak mengimpor komponen Attendance:
  kontrak `StudentAction` tidak berubah, Core hanya tahu sebuah URL. Alasan iframe:
  logika kartu/template ada di Attendance; menduplikasinya di Core melanggar batas modul.
- Tombol "Cetak" ada di footer modal dan memanggil `iframe.contentWindow.print()`
  (same-origin), sehingga yang tercetak hanya kartu, bukan halaman Siswa.
- `StaticQr.tsx`: bila `embed=1` (prop dari controller), sembunyikan header/tombol sendiri
  dan padding; tanpa `embed` halaman tetap berfungsi mandiri (mis. dibuka langsung).
- Cetak semua = banyak kartu: modal besar (`max-w-4xl`, tinggi ~80vh, scroll di dalam iframe).

## Data & penyimpanan
- Tabel baru `static_qr_card_templates` (migrasi `0007_01_01_000008_...` di Attendance,
  tenant-scoped via `BelongsToTenant`, satu baris per sekolah, tanpa FK selain `tenant_id`):
  `image_path`, `image_name`, `image_mime`, `image_width`, `image_height`,
  `qr_x`, `qr_y`, `qr_size` (decimal persen), `card_width_mm`.
  Model `StaticQrCardTemplate` + factory.
- Berkas disimpan lewat `Platform\Contracts\TenantStorage` (module `attendance`, path
  `static-qr/card-{random}.{ext}`), nama dibuat server — meniru
  `Ppdb/app/Domain/Support/AnswerFiles.php`. Otomatis terhitung `StorageMeter`.
- Template tetap tersimpan saat QR statis dimatikan; hanya aksesnya yang tertutup.

## Backend (modul Attendance)
Rute baru di `routes/web.php`, semua di balik `can:attendance.static-qr.print`:
- `GET  absensi/qr-statis/kartu` → `StaticQrCardController@show` (editor)
- `POST absensi/qr-statis/kartu/gambar` → unggah/ganti gambar (reset placeholder ke tengah)
- `PUT  absensi/qr-statis/kartu` → simpan placeholder + `card_width_mm`
- `DELETE absensi/qr-statis/kartu` → hapus template + berkas
- `GET  absensi/qr-statis/kartu/gambar` → stream gambar (`X-Content-Type-Options: nosniff`);
  path dibaca hanya dari baris DB, tidak pernah dari request.

Kelas baru: `Domain/Qr/CardTemplateFiles` (put/delete/stream), action `SaveCardTemplate`,
`ReplaceCardImage`, `DeleteCardTemplate`, FormRequest (`AttendanceFormRequest` sebagai induk).
Validasi unggah: `image`, mimes `png,jpg,jpeg` saja (**SVG ditolak**, risiko XSS),
maks ~5 MB, dimensi min ±400 px sisi terpendek, cek `getimagesize` (tanpa GD).
Validasi placeholder: `0 ≤ x`, `size ≥ 5`, `x+size ≤ 100`, `y+size·(lebar/tinggi) ≤ 100`,
`card_width_mm` 40–300.
`StaticQrController` diubah: menambahkan prop `template` (url gambar, x/y/size, lebar mm,
rasio) bila ada. Query `?format=polos` memaksa lembar QR polos.

## Frontend
- `Pages/Attendance/StaticQrCard.tsx` (editor): unggah (komponen Shared `Field`/`Input`),
  pratinjau gambar dengan kotak QR yang bisa digeser (pointer events + panah keyboard),
  slider ukuran, input lebar kartu (mm), tombol Simpan / Ganti gambar / Hapus
  (`ConfirmAction`-gaya `AlertDialog` dari Shared). Pratinjau memakai QR contoh,
  **bukan kode siswa sungguhan**. Komponen `ui` dari `modules/Shared` saja.
- `StaticQr.tsx`: jika `template` ada, render kartu (img + QR) sebagai satuan cetak;
  sakelar "Kartu / QR saja" (client-side) dan tautan "Atur template kartu" (print:hidden).
- `Settings.tsx`: tombol "Atur template kartu" di bawah opsi QR statis saat aktif.
- Logika persen→piksel dibuat satu util kecil dipakai editor dan halaman cetak agar posisi
  identik.

## Berkas yang disentuh (pola)
`modules/Attendance/{routes/web.php, app/Http/Controllers/StaticQr*Controller.php,
app/Domain/{Models,Actions,Qr}/*, app/Http/Requests/*, database/{migrations,factories},
resources/js/Pages/Attendance/{StaticQr,StaticQrCard,Settings}.tsx, CONTRACT.md,
tests/Feature/StaticQrCardTest.php}`. Core tidak berubah (cetak tetap lewat
`StudentActionRegistry` yang sudah ada).

## Tes (Pest, `StaticQrCardTest.php`)
- Unggah valid tersimpan di `tenants/{id}/attendance/...`; non-gambar, SVG, terlalu besar,
  terlalu kecil → ditolak, tidak ada berkas tersisa.
- Placeholder di luar gambar / ukuran tak wajar ditolak; yang valid tersimpan.
- Ganti gambar menghapus berkas lama dan mereset posisi; hapus menghapus berkas + baris.
- Akses: terlarang bila QR statis mati atau bukan admin; sekolah lain tidak bisa membaca
  gambar sekolah ini (isolasi tenant); header `nosniff`.
- Halaman cetak mengirim prop `template` hanya bila ada; `?format=polos` menyembunyikannya.
- `?embed=1` mengirim prop `embed: true`; tanpa itu `false`. Tes Siswa tetap lulus
  (kontrak `studentActions` tidak berubah).
Aturan proyek: jalankan `npm run build` (manifest Vite untuk halaman baru), `pint --dirty`,
`tsc --noEmit`, Deptrac; tes `modules/Attendance` penuh.

## Verifikasi manual
Unggah kartu contoh → geser/ubah ukuran kotak → Simpan → Siswa › "Cetak QR statis" untuk
satu siswa (modal terbuka, tab tidak berpindah) → tombol Cetak di modal → Print preview
browser hanya berisi kartu: posisi QR sama dengan editor, gambar tercetak tanpa
"Background graphics", ukuran fisik ≈ lebar mm yang diatur. Pindai hasil cetak/layar
dengan Pindai QR (QR statis aktif) dan pastikan terbaca.

## Risiko / catatan
- Kualitas cetak bergantung resolusi gambar yang diunggah (saran ≥ 600 px lebar).
- QR kecil pada gambar ramai sulit dipindai: editor memberi peringatan bila `size` terlalu
  kecil relatif lebar fisik (< ~15 mm).
- Mengganti `APP_KEY` tetap membatalkan semua kode cetak (tidak berubah).

## Pertanyaan terbuka (default dalam kurung)
1. Satu template saja, atau perlu beberapa (mis. per jenjang/kelas)? (satu)
2. Perlu teks nama/NIS/kelas ikut ditempel di kartu juga? (tidak, v1 hanya QR)
3. Ukuran fisik default 85,6 mm CR80 sudah sesuai kartu sekolah? (ya, bisa diubah)
4. Setelah disetujui, ingin plan ini juga dicatat di `docs/ai/plan/fase-18/`? (ya)

## Status
Selesai: template kartu, editor letak QR, cetak dalam modal di daftar Siswa. Tes: StaticQrCardTest.
