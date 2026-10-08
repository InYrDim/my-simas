# Pengemasan modul PPDB, absensi, dan notifikasi WhatsApp ke wali murid: paket vs add-on (Indonesia)

Tanggal akses semua sumber: 2026-10-08. Semua isi halaman diambil lewat fetch; harga bisa berubah. Label: "terverifikasi (URL)" = terbaca di halaman vendor/Meta; "tidak ditemukan" = tidak ada di halaman yang dibuka.

## 1. Vendor mana yang menjual PPDB / absensi / notifikasi WhatsApp, dan harganya (Rp, satuan)?

### Takeaway
Harga sangat beragam: flat per sekolah per tahun (APPSO, absen.web.id), per siswa per bulan (appabsensisekolah.web.id per snippet pencarian), langganan bulanan siswa tanpa batas (SchoolPay), dan PPDB sebagai langganan per periode (PPDBSekolah.com, PPDB.app). Tidak ada satu satuan dominan. Vendor besar (Gadjian-sejenis, Eduka, dsb.) tidak saya periksa; hasil hanya vendor kecil-menengah.

### Cited Findings
- APPSO, flat per sekolah per tahun, tanpa biaya per siswa: Nyala Growth Rp 2.500.000/thn (QR + absensi manual, app wali Android); Merdeka Growth Rp 5.000.000/thn (tambah website sekolah, integrasi PPDB online, jadwal pelajaran membuka sesi absensi); Merdeka Enterprise Rp 6.000.000/thn (tambah absensi pesantren, multi-cabang, LMS + CBT). Bonus 3 bulan gratis untuk sekolah baru. Terverifikasi — [APPSO](https://appso.id/fitur/absensi-siswa)
- APPSO hardware add-on (hanya Merdeka Enterprise): fingerprint Rp 24.000.000, face recognition Rp 19.000.000, RFID tersedia (harga tidak tertulis). Terverifikasi — [APPSO](https://appso.id/fitur/absensi-siswa)
- SchoolPay, langganan bulanan, siswa tanpa batas: Starter Rp 99.000/bln, Plus Rp 129.000/bln, Premium Rp 169.000/bln. Modul Notifikasi WhatsApp mulai tier Plus; Portal PPDB dan E-Kantin hanya tier Premium. Absensi tidak disebut di tier mana pun. Setup dan maintenance gratis. Terverifikasi — [SchoolPay](https://schoolpay.co.id/)
- PPDBSekolah.com (produk khusus PPDB, musiman per periode): Basic Rp 2.300.000/3 bln, Standard Rp 3.700.000/6 bln, Premium Rp 6.000.000/12 bln. Semua tier fitur identik, termasuk notifikasi WhatsApp dan payment gateway; pembeda hanya durasi. Tidak ada add-on terpisah tertulis. Terverifikasi — [PPDBSekolah.com](https://ppdbsekolah.com/harga/)
- PPDB.app, via SIPLAH, per sekolah per periode: Mandiri Rp 5.000.000 (min 1 sekolah), Kolektif Panitia Rp 4.000.000 (min 10), Kolektif Dinas Rp 3.000.000 (min 20). WhatsApp tidak disebut. Terverifikasi — [PPDB.app](https://ppdb.app/)
- absen.web.id (presensi selfie + GPS + notifikasi WhatsApp ke ortu, semua tier): Standard 1-200 pengguna Rp 1.500.000/thn (diskon Rp 750.000), Medium 1-500 Rp 2.500.000 (diskon Rp 1.250.000), Premium 1-1000 Rp 5.500.000 (diskon Rp 2.500.000); tambahan pengguna Rp 5.000/pengguna. Pembeda tier: kuota pengguna dan storage, bukan modul. QR dan fingerprint tidak disebut. Terverifikasi — [absen.web.id](https://absen.web.id/)
- SchoolMantic: ada paket "Free Forever" untuk sampai 100 siswa, "6 pilihan paket", bayar 10 bulan untuk 12 bulan; Bimbel Rp 300.000/bln tanpa batas siswa. Notifikasi WhatsApp/Telegram/push disebut; rincian harga per paket tidak tertulis di landing page, PPDB tidak disebut. Terverifikasi — [SchoolMantic](https://www.schoolmantic.com/). (Angka Rp 3.000/siswa/bln WhatsApp dan Rp 5.000 SMS+WA hanya muncul di ringkasan pencarian, tidak terkonfirmasi di halaman: tidak diverifikasi.)
- appabsensisekolah.web.id: ringkasan pencarian menyebut Basic Rp 3.000, Standard Rp 4.000, Premium Rp 5.000 per siswa/bln dengan WhatsApp hanya di Premium. Fetch halaman gagal (socket closed): TIDAK terverifikasi — [URL](http://www.appabsensisekolah.web.id/)
- Jasa website + PPDB (sekali bayar): Velocity Developer Rp 500.000 / Rp 1.200.000, Seven Media Tech Rp 1.499.000-3.999.000+, Arrazy Inovasi Rp 4,5 jt / 8,5 jt / 15 jt. Hanya dari ringkasan pencarian, halaman tidak di-fetch: tidak diverifikasi — [Arrazy](https://arrazyinovasi.com/jasa-website-sekolah)
- Benchmark industri (blog vendor Seqolah, bukan harga produknya sendiri): per siswa Rp 1.500-3.000/bln; flat Rp 1,5-5 jt/bln untuk 500+ siswa; lisensi sekali bayar Rp 15-50 jt + maintenance 15-20%/thn. Sumber sekunder/pemasaran — [Seqolah blog](https://www.seqolah.com/blog/panduan-biaya-aplikasi-pembayaran-sekolah-2026)

### Inferences
- Absensi dan notifikasi WhatsApp hampir selalu satu paket fitur "absensi"; PPDB adalah modul terpisah yang dijual sebagai produk sendiri atau dinaikkan ke tier atas.
- Harga PPDB musiman (per 3/6/12 bulan atau per periode) berbeda dari absensi yang tahunan.

### Gaps
- Tidak memeriksa vendor besar yang harganya mungkin tidak dipublikasikan (hanya "hubungi sales"); harga tidak dipublikasikan untuk SchoolMantic per paket.
- Harga fingerprint/RFID/gerbang selain APPSO tidak ditemukan.

## 2. Notifikasi WhatsApp: per pesan, kuota, atau gratis? Resmi atau tidak? Risiko?

### Takeaway
Dari vendor yang diperiksa, WhatsApp ke wali murid biasanya disertakan flat (absen.web.id, PPDBSekolah.com, SchoolPay tier Plus+) atau dijual sebagai add-on tahunan flat (APPSO). Hanya APPSO yang terang-terangan membedakan jalur tidak resmi dan resmi Meta, dengan selisih harga 4,7x.

### Cited Findings
- APPSO: add-on WhatsApp terpisah, "Unofficial" Rp 2.000.000/thn vs "Official Meta API" Rp 9.400.000/thn per sekolah; kirim 3-5 detik setelah absen. Tidak ada kuota atau per pesan tertulis, tidak ada pernyataan risiko pemblokiran di halaman. Terverifikasi — [APPSO](https://appso.id/fitur/absensi-siswa)
- SchoolPay: modul WhatsApp termasuk tier Plus dan Premium; kuota/biaya per pesan dan jenis API tidak diungkap. Terverifikasi — [SchoolPay](https://schoolpay.co.id/)
- absen.web.id: WhatsApp ke ortu di semua tier; jenis API (resmi/tidak) tidak disebut. Terverifikasi — [absen.web.id](https://absen.web.id/)
- PPDBSekolah.com: WhatsApp termasuk di semua tier. Terverifikasi — [PPDBSekolah.com](https://ppdbsekolah.com/harga/)
- Kantorkita (blog): tidak ada harga, tidak ada pernyataan resmi/tidak resmi atau risiko. Terverifikasi (tidak ada info) — [Kantorkita](https://www.kantorkita.co.id/blog/aplikasi-absensi-siswa-notifikasi-whatsapp-orang-tua-otomatis-murah/)
- Meta: penagihan per pesan template terkirim sejak 1 Juli 2025; kategori Marketing, Utility, Authentication; tarif ditentukan kategori template dan kode negara penerima (Indonesia = +62, kelompok "Rest of Asia Pacific"). Utility di dalam jendela layanan pelanggan 24 jam tidak ditagih. Rate card IDR tersedia (CSV dan PDF), berlaku per 1 Oktober 2026 menurut halaman resmi. Terverifikasi — [Meta pricing](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing)
- Meta: mulai 1 Oktober 2026 pesan service dan utility non-template (dalam jendela 24 jam) mulai ditagih per pesan, tarif sama dengan utility/authentication per pasar, tanpa diskon volume. Terverifikasi — [Meta non-template](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing/non-template-messages)
- Angka IDR Indonesia (sumber sekunder, penyedia BSP Cekat, berlaku 1 Juli 2026): Marketing Rp 586,33; Utility Rp 356,65; Authentication domestik Rp 356,65; authentication internasional Rp 1.940,13 per pesan, belum termasuk PPN 11% (PPN dari ringkasan pencarian). Tidak terverifikasi ke PDF Meta — [Cekat](https://cekat.ai/en/blog/panduan-harga-biaya-whatsapp-api-dan-omnichannel-di-indonesia). Halaman Meta tidak menampilkan angka Indonesia di teks; harus dibuka dari PDF/CSV IDR.
- Benchmark biaya WA gateway per notifikasi Rp 25-150 (blog Seqolah; sekunder, kemungkinan jalur non-resmi/BSP) — [Seqolah blog](https://www.seqolah.com/blog/panduan-biaya-aplikasi-pembayaran-sekolah-2026)

### Inferences
- Tarif utility ~Rp 357/pesan menjelaskan mengapa APPSO memasang add-on resmi Rp 9,4 jt/thn flat: kira-kira setara ~26.000 pesan/thn. Ini hitungan saya, bukan klaim vendor; vendor tidak menyatakan kuota.
- Notifikasi absensi (utility) pada skala sekolah mudah menjadi biaya variabel nyata bila resmi; paket flat tanpa kuota tersirat memakai jalur tidak resmi atau menyerap biaya.

### Gaps
- Angka IDR dari PDF/CSV Meta tidak terbaca (hanya label tautan). Harus diunduh manual.
- Tidak ada vendor yang menyatakan risiko pemblokiran nomor di halaman resmi yang saya baca; risiko jalur tidak resmi hanya dugaan dari ringkasan pencarian, tidak bersumber primer.
- Tidak ada vendor yang menagih per pesan ke sekolah di halaman yang diperiksa (tidak ditemukan).

## 3. PPDB: produk terpisah musiman atau fitur dalam paket?

### Takeaway
Keduanya ada. PPDBSekolah.com dan PPDB.app menjualnya sebagai produk khusus per periode; APPSO dan SchoolPay menaruhnya sebagai fitur yang hanya terbuka di tier menengah-atas.

### Cited Findings
- Produk terpisah per periode: PPDBSekolah.com (Rp 2,3 jt/3 bln s.d. Rp 6 jt/12 bln) — [link](https://ppdbsekolah.com/harga/); PPDB.app (Rp 3-5 jt per sekolah per periode, via SIPLAH) — [link](https://ppdb.app/)
- Fitur dalam paket tier atas: APPSO Merdeka Growth (Rp 5 jt/thn) — [link](https://appso.id/fitur/absensi-siswa); SchoolPay Premium (Rp 169.000/bln) — [link](https://schoolpay.co.id/)
- SchoolMantic: PPDB tidak disebut di landing page — [link](https://www.schoolmantic.com/)

### Inferences
- Pola: PPDB dipakai musiman, jadi dijual per periode atau sebagai pembuka tier atas, bukan di paket dasar.

### Gaps
- Tidak ditemukan vendor yang menjual PPDB sebagai add-on berharga terpisah di dalam paket absensi.

## 4. Contoh paket yang membuka modul hanya di tier tertinggi

### Takeaway
Ya, jelas.

### Cited Findings
- SchoolPay: PPDB dan E-Kantin hanya di Premium Rp 169.000/bln; WhatsApp mulai Plus Rp 129.000/bln — [SchoolPay](https://schoolpay.co.id/)
- APPSO: PPDB mulai Merdeka Growth; absensi pesantren, LMS/CBT, multi-cabang hanya Enterprise; hardware fingerprint/face hanya Enterprise — [APPSO](https://appso.id/fitur/absensi-siswa)
- Kebalikan: absen.web.id dan PPDBSekolah.com membedakan tier berdasarkan kuota/durasi, bukan modul — [absen.web.id](https://absen.web.id/), [PPDBSekolah.com](https://ppdbsekolah.com/harga/)

### Inferences
- Dua pola umum: gating modul per tier (SchoolPay, APPSO) vs gating kuota/durasi (absen.web.id, PPDBSekolah.com).

### Gaps
- Sampel kecil (5 vendor ter-fetch); bukan survei pasar lengkap.
