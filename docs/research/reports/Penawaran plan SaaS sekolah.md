# Pasar SaaS sekolah memadukan kuota siswa dan modul

Vendor SaaS sekolah di Indonesia paling sering menyusun plan dengan dua sumbu: kuota siswa (pada model flat per sekolah) dan modul yang dibuka (pada model per siswa). Satuan harganya tidak seragam. Ada tarif per siswa **Rp 2.000-4.500 per bulan** untuk paket dasar sampai menengah, dan ada tarif flat per sekolah **Rp 0,5-6 juta per tahun** atau **Rp 150 ribu-2,5 juta per bulan**. Batas jumlah akun guru dan penyimpanan jarang dipakai sebagai pembeda. PPDB hampir selalu dijual musiman, sebagai produk sendiri atau sebagai pembuka tier atas. Absensi dan notifikasi WhatsApp sering menyatu dalam satu paket, tetapi WhatsApp resmi lewat Meta memunculkan biaya variabel yang nyata. Trial lazim berdurasi 14-30 hari, dan diskon tahunan lazim 10-37%. Dari sisi penagihan, juknis BOS terbaru tidak menyebut langganan aplikasi manajemen sekolah secara eksplisit, dan Pasal 66 melarang sewa aplikasi daring penerimaan murid baru dari dana BOS, sehingga posisi PPDB dan penagihan ke sekolah negeri perlu dicek ke dinas setempat.

**Catatan kehati-hatian.** Semua angka harga diambil lewat alat fetch yang merangkum halaman, bukan menyalin teks mentah. Cek ulang di halaman vendor sebelum dikutip sebagai harga final. Konversi Rp dari USD tidak diverifikasi dan memakai asumsi kasar Rp16.000-17.000 per USD. Seluruh sumber diakses 2026-10-08.

Legenda label: **[V]** terverifikasi di halaman primer (lewat ringkasan fetch); **[TD]** tidak ditemukan; **[I]** inferensi penulis atau sumber sekunder yang belum dikonfirmasi.

## Sumbu pembeda: kuota siswa untuk sekolah kecil, modul untuk model per siswa

Pada model flat, pembeda utamanya kuota. DiksaID menjual Basic (100 siswa, 10 guru, 5 GB) Rp 500.000/tahun, Standard (300 siswa, 28 guru, 10 GB) Rp 1,2 juta/tahun, dan Premium (1.000 siswa, 40 guru, 20 GB) Rp 2,5 juta/tahun, ditambah paket gratis 50 siswa yang hanya berupa website profil ([DiksaID](https://diksa.id/)). EduSantri bertingkat 0-100, 101-500, 501-1.000, dan 1.001-2.000 siswa, dengan harga Rp 1 juta sampai Rp 2,5 juta per bulan ([EduSantri](https://edusantri.com/pricing)). absen.web.id membedakan tier lewat kuota pengguna (200/500/1.000) dan penyimpanan, bukan modul ([absen.web.id](https://absen.web.id/)). Pada model per siswa dan model flat bertingkat modul, pembedanya adalah modul yang terbuka. APPSO membuka absensi, jadwal, dan rapor di tier Growth, lalu LMS dan CBT di Enterprise ([APPSO](https://www.appso.id/harga)). SikaCloud menambah rapor digital, PPDB, dan CBT di Smart, lalu dompet digital, kantin, dan dukungan 24/7 di Pro ([SikaCloud](https://sikacloud.com/harga)). SISAP dan Scholarik tidak membedakan modul sama sekali; pembedanya hanya durasi bayar ([SISAP](https://sisap.id/), [Scholarik](https://scholarik.com/)).

Batas akun staf jarang dipakai. Hanya DiksaID yang menuliskan batas guru; SISAP dan AdminSekolah.net menyatakan pengguna tanpa batas ([AdminSekolah.net](https://adminsekolah.net/harga/)). Penyimpanan hanya muncul di DiksaID dan PENAILMU (Premium 500 GB), sedangkan vendor global tidak menetapkan batas GB publik. Dukungan prioritas, domain kustom, dan white label dipakai sebagai pembeda tier atas ([Kamadeva SISKO](https://www.kamadeva.com/index-menu-price)). Inferensi **[I]**: untuk SIMAS, kuota siswa dan modul adalah sumbu yang sesuai pasar; batas akun staf dan GB bisa diabaikan atau dipakai sebagai batas lunak.

| Pola | Contoh | Batas yang dipakai | Status |
|---|---|---|---|
| Kuota siswa + guru + GB | DiksaID, PENAILMU | siswa, guru, penyimpanan | [V] |
| Kuota pengguna saja | absen.web.id | pengguna (+Rp 5.000/pengguna tambahan) | [V] |
| Bertingkat modul | APPSO, SikaCloud, SchoolPay | modul | [V] |
| Satu paket, beda durasi | SISAP, Scholarik | tidak ada | [V] |
| Tier siswa (global) | Classe365 (1-100 s.d. 1000+) | siswa; harga di atas 100 siswa tidak tampil | [V] |
| Flat per institusi, user tak terbatas | Fedena $999-1.699/thn | modul; pembeda antar plan tidak jelas | [V] |

## Dasar harga: flat per sekolah menang untuk sekolah kecil, per siswa untuk skala besar

Tidak ada satuan dominan. Berikut tabel perbandingan vendor Indonesia yang harganya terbaca di halaman resmi.

| Produk | Dasar harga | Harga | Catatan |
|---|---|---|---|
| SikaCloud | per siswa/bulan | Basic Rp 2.000; Smart Rp 3.600; Pro Rp 5.200; promo tahunan Rp 25.000/siswa/thn | tahunan diskon 20%; ada beli putus ([SikaCloud](https://sikacloud.com/harga)) |
| Scholarik | per pengguna aktif/bulan | Rp 2.500 (tahunan) sampai Rp 4.000 (bulanan) | diskon volume 4-10%; akun ortu gratis ([Scholarik](https://scholarik.com/)) |
| SISAP | per siswa/bulan | Rp 9.000 (bulanan) sampai Rp 6.250 setara (tahunan Rp 75.000) | semua modul 24+ ([SISAP](https://sisap.id/)) |
| SISKO | per siswa/bulan | Rp 10.000; white label Rp 25.000 | fokus pembayaran/SPP ([Kamadeva](https://www.kamadeva.com/index-menu-price)) |
| APPSO | flat/sekolah/tahun | Nyala Rp 1,5-4 juta; Merdeka Rp 4-6 juta | tanpa biaya setup ([APPSO](https://www.appso.id/harga)) |
| DiksaID | flat/tahun + kuota | Rp 0,5 / 1,2 / 2,5 juta | ([DiksaID](https://diksa.id/)) |
| AdminSekolah.net | flat/bulan, siswa tak terbatas | Rp 150 / 300 / 500 ribu | add-on terpisah ([AdminSekolah.net](https://adminsekolah.net/harga/)) |
| EduSantri | campuran | Basic gratis; Rp 2.000/siswa; premium Rp 1-2,5 juta/bln | ([EduSantri](https://edusantri.com/pricing)) |
| e-School (Rifil) | flat sewa | Rp 2 juta/bln (<1.000 siswa) | jual putus Rp 150-200 juta, nego ([Rifil](https://rifil.co.id/simdik-sistem-informasi-manajemen-pendidikan-dasar-menengah/)) |
| Classe365 (global) | tier siswa | $100/bln untuk 1-100 siswa | setara sekitar Rp 1,6-1,7 juta/bln **[I]**, kurs tidak diverifikasi ([Classe365](https://www.classe365.com/pricing/)) |
| Fedena (global) | flat/institusi/tahun | $999 / $1.399 / $1.699 | setara sekitar Rp 16-17 juta/thn untuk Standard **[I]** ([Fedena](https://fedena.com/pricing-and-plans)) |

Harga yang berbeda lebih dari 10 kali lipat bisa hidup berdampingan. Contohnya DiksaID Premium, yang pada kuota penuh 1.000 siswa setara sekitar Rp 208 per siswa per bulan, dan SISAP Rp 6.250-9.000 per siswa per bulan. Ini **[I]** sinyal bahwa sekolah kecil swasta sangat sensitif harga dan sebagian vendor bersaing dengan harga rendah. Benchmark industri dari blog vendor Seqolah (Rp 1.500-3.000 per siswa per bulan; lisensi sekali bayar Rp 15-50 juta plus maintenance 15-20% per tahun) bersifat pemasaran dan sekunder ([Seqolah](https://www.seqolah.com/blog/panduan-biaya-aplikasi-pembayaran-sekolah-2026)). Vendor besar (Ruangguru, Kelas Pintar, Edlink/Sevima) **[TD]** tidak mempublikasikan harga SIS untuk sekolah; Eduka, iSchool, dan Sekolahku tidak ditemukan hasil relevan ([Kelas Pintar](https://www.kelaspintar.id/)).

## Plan khusus dan kesepakatan harga: ada, tapi sedikit yang menulis angkanya

Pola "hubungi kami" ada di dua bentuk. Pertama, tier tertinggi tanpa harga: Classe365 Enterprise Pro dan Fedena Enterprise (tambahan akses source code) **[V]**. Kedua, vendor yang sepenuhnya memakai quote: Alma, Teachmint, dan Gradelink tidak menerbitkan harga di halaman primer **[V]**, sedangkan angka Capterra ($99/bulan flat untuk Alma, mulai $5 per user per tahun untuk Teachmint) tidak diverifikasi **[I]**. Di Indonesia, "Enterprise" sering hanya tier teratas dengan harga tertulis (APPSO Rp 4-6 juta). Yang benar-benar kustom adalah SISKO Premium white label (via konsultasi WhatsApp), PENAILMU (harga via WhatsApp), Skoolacloud (via demo), serta jual putus: e-School Rp 150-200 juta (negosiasi) dan Sais Cloud lisensi lifetime Rp 350 juta, yang terakhir hanya dari snippet pencarian **[I]**. Diskon khusus yayasan multi-sekolah dengan angka pasti **[TD]**; Skoolacloud hanya menyebut dukungan multi-sekolah tanpa harga. Diskon volume eksplisit hanya ditemukan di Scholarik (4% untuk 501-750 pengguna, 7% untuk 751-1.000, 10% di atas 1.000).

## Add-on: PPDB dijual terpisah, WhatsApp cenderung dipaketkan, tetapi biaya resmi bersifat variabel

PPDB hampir selalu terpisah atau di tier atas. PPDBSekolah.com menjualnya sebagai produk musiman: Rp 2,3 juta/3 bulan, Rp 3,7 juta/6 bulan, Rp 6 juta/12 bulan, dengan fitur identik di semua tier termasuk WhatsApp ([PPDBSekolah.com](https://ppdbsekolah.com/harga/)). PPDB.app menjual Rp 3-5 juta per sekolah per periode lewat SIPLah ([PPDB.app](https://ppdb.app/)). AdminSekolah.net memakai tarif SPMB yang sama persis dengan PPDBSekolah.com sebagai add-on. SchoolPay menaruh PPDB hanya di Premium Rp 169.000/bulan ([SchoolPay](https://schoolpay.co.id/)), dan APPSO mulai dari Merdeka. Tidak ada vendor yang menjual PPDB sebagai add-on berharga terpisah di dalam paket absensi **[TD]**.

Absensi umumnya masuk paket inti (DiksaID menjualnya Rp 300.000/tahun sebagai add-on di tier bawah dan menyertakannya di Premium). Hardware bersifat terpisah: fingerprint Rp 24 juta dan face recognition Rp 19 juta di APPSO, mesin absensi Rp 2 juta di EduSantri.

WhatsApp punya tiga pola **[V]**: termasuk di semua tier (absen.web.id, PPDBSekolah.com), dibuka mulai tier menengah (SchoolPay Plus Rp 129.000/bulan), atau add-on flat (AdminSekolah.net Rp 50.000/bulan; APPSO Rp 2 juta/tahun tidak resmi versus Rp 9,4 juta/tahun resmi Meta) ([APPSO](https://appso.id/fitur/absensi-siswa)). Tidak ada vendor yang menagih per pesan ke sekolah di halaman yang diperiksa **[TD]**, dan tidak ada yang menyatakan risiko pemblokiran nomor **[TD]**. Meta menagih per pesan template terkirim sejak 1 Juli 2025, dan mulai 1 Oktober 2026 pesan service dan utility non-template juga ditagih ([Meta pricing](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing), [non-template](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing/non-template-messages)). Angka IDR di PDF Meta tidak terbaca; sumber sekunder BSP menyebut utility sekitar Rp 357 per pesan di luar PPN ([Cekat](https://cekat.ai/en/blog/panduan-harga-biaya-whatsapp-api-dan-omnichannel-di-indonesia)), **[I]** belum dicek ke dokumen Meta. Dengan tarif itu, add-on resmi APPSO Rp 9,4 juta/tahun setara sekitar 26.000 pesan **[I]** (hitungan penulis, vendor tidak menyatakan kuota). Implikasi **[I]**: jika SIMAS memakai jalur resmi, paket WhatsApp flat tanpa kuota berisiko menggerus margin; sebaiknya diberi kuota pesan atau dijual sebagai add-on.

## Trial dan diskon tahunan: trial 14-30 hari, diskon 10-37%

| Vendor | Trial | Fitur | Diskon tahunan |
|---|---|---|---|
| SISKO | 30 hari, tanpa kartu | tampak terbatas pada modul pembayaran **[I]** | tanpa komitmen jangka panjang ([Kamadeva](https://www.kamadeva.com/index-menu-price)) |
| DiksaID | "3 bulan" tertulis di paket berbayar | tidak dirinci | harga sudah tahunan; paket gratis permanen ([DiksaID](https://diksa.id/)) |
| SISAP | demo maks 10 siswa | terbatas kuota | Rp 9.000 turun ke setara Rp 6.250 (tahunan) |
| APPSO | trial modul inti; garansi 14 hari refund | modul inti | bonus 3 bulan sekolah baru; 10% early adopter |
| SikaCloud | ada, durasi tidak disebut | tidak diketahui | 20% (promo tahunan hemat 54% vs Smart reguler) |
| Scholarik | tidak disebut | | tahunan 37% lebih murah dari bulanan |
| Classe365 | 15 hari, tanpa kartu | semua modul | 10% ([Classe365](https://www.classe365.com/pricing/)) |
| Fedena | 14 hari | tidak dirinci | harga sudah tahunan |
| OpenEduCat | 15 hari, tanpa kartu | tidak dirinci | bayar 2 tahun, tahun ke-3 gratis ([OpenEduCat](https://openeducat.org/pricing)) |
| Teachworks | 21 hari, tanpa kontrak | | tidak ada ([Teachworks](https://www.teachworks.com/pricing)) |

Satu-satunya trial yang jelas berfitur penuh adalah Classe365. Untuk vendor Indonesia, apakah trial SikaCloud, APPSO, dan PENAILMU berfitur penuh atau terbatas **[TD]**. Pola umum **[I]**: prabayar tahunan diberi insentif 20-37% atau bonus bulan, dan paket gratis permanen terbatas (DiksaID 50 siswa, EduSantri Basic, SchoolMantic 100 siswa) dipakai sebagai pintu masuk ([SchoolMantic](https://www.schoolmantic.com/)).

## Anggaran BOS dan PPN: aplikasi manajemen tidak disebut, PPDB berisiko, penagihan mengikuti pencairan

**Dana BOS.** Juknis BOSP terbaru adalah Permendikdasmen 8/2026 (ditetapkan 5 Februari 2026) ([peraturan.go.id](https://peraturan.go.id/files/Permendikdasmen-no-8-tahun-2026.pdf)). **[V]** Teksnya tidak menyebut langganan aplikasi manajemen sekolah atau SIS secara eksplisit. Yang eksplisit boleh: LMS dan perangkat lunak yang dipakai dalam pembelajaran, "langganan konten" (BOS Kinerja), biaya layanan penerimaan murid daring, dan "langganan daya dan jasa lain yang relevan". Yang eksplisit dilarang (Pasal 66 ayat 1): membeli perangkat lunak pelaporan keuangan BOSP atau yang sejenis, dan menyewa aplikasi pendataan atau aplikasi daring penerimaan murid baru. Teks ini tampak bertabrakan dengan "biaya layanan penerimaan Murid daring" di lampiran, dan tidak ada penjelasan resmi **[TD]**. Pelaporan wajib lewat ARKAS milik Kementerian, bukan vendor. Dana cair dua tahap; laporan tahap I paling lambat 31 Juli, dan laporan keseluruhan paling lambat 31 Januari tahun berikutnya. Pembayaran tahunan di muka tidak diatur **[TD]**, dan daftar pasti bukti pertanggungjawaban (kuitansi, faktur, NPWP vendor) **[TD]**. Juknis BOS madrasah (Kepdirjen Pendis 944/2026 jo. 2100/2026) hanya terbaca dari blog sekunder; teks primernya **[TD]**, sehingga tidak ada klaim untuk madrasah yang aman. Inferensi **[I]**, bukan nasihat hukum: memposisikan SIMAS sebagai layanan pembelajaran atau layanan daring memberi jalur komponen yang lebih jelas; PPDB dan pelaporan keuangan berisiko bersinggungan dengan Pasal 66. Siklus tagihan semesteran yang selaras pencairan BOS lebih mudah dicocokkan dengan RKAS/ARKAS daripada bulanan.

**PPN.** **[V]** Berdasarkan PMK 131/2024, tarif PPN 12% dikenakan atas DPP nilai lain 11/12, sehingga beban efektif jasa kena pajak non-mewah sekitar 11% ([JDIH Kemenkeu](https://jdih.kemenkeu.go.id/api/download/ad276b82-94bd-4197-b409-af33e2842cd6/2024pmkeuangan131.pdf)). Contoh: jasa Rp 1.000.000 menghasilkan PPN Rp 110.000. Tidak ditemukan perubahan tarif sampai 2026-10-08, tetapi pencarian terbatas. Pengusaha kecil beromzet sampai Rp 4,8 miliar per tahun tidak wajib dikukuhkan PKP (PMK 197/PMK.03/2013) ([DJP](https://www.pajak.go.id/en/node/8899)). **[I]** (sumber sekunder) Non-PKP tidak boleh menerbitkan faktur pajak ([online-pajak](https://www.online-pajak.com/tentang-ppn-efaktur/non-pkp-menerbitkan-faktur-pajak/)). Maka vendor non-PKP menagih tanpa baris PPN, sedangkan vendor PKP memungut PPN dengan faktur pajak. Status PPN pada sebagian besar vendor tidak jelas di halaman mereka; SikaCloud hanya disebut belum termasuk PPN 11% lewat snippet pencarian. Klasifikasi SaaS lokal sebagai JKP secara eksplisit, kewajiban pungut/potong pajak oleh bendahara sekolah negeri, dan syarat vendor di SIPLah (NPWP, PKP) **[TD]**. Perpres 46/2025 (batas pengadaan langsung Rp 200 juta, kuitansi hingga Rp 50 juta) hanya dari ringkasan pencarian **[I]**. Metode bayar yang lazim di sekolah (transfer, virtual account, QRIS) **[TD]** dari sumber primer.

## Kesimpulan

Pasar publik di Indonesia terdiri dari vendor niche dengan harga terbuka, sedangkan nama besar menutup harga di balik sales. Ini membuat rentang Rp 150 ribu-2,5 juta per bulan (flat) atau Rp 2-5 ribu per siswa per bulan (per siswa) menjadi acuan realistis untuk SIMAS, dengan paket gratis atau trial 14-30 hari sebagai pintu masuk. Temuan yang paling berpengaruh pada desain adalah dua risiko biaya dan kepatuhan. Pertama, WhatsApp resmi punya biaya variabel per pesan yang tidak tercermin di harga flat vendor lokal. Kedua, PPDB dan pelaporan keuangan berada di zona abu-abu Pasal 66 untuk sekolah negeri yang membayar dari BOS.

Sebelum plan dikunci, empat hal perlu dicek: harga halaman vendor (angka fetch berasal dari ringkasan), tarif IDR di PDF Meta, status PKP SIMAS (menentukan ada tidaknya PPN pada invoice), dan konfirmasi dinas atau tim BOS tentang langganan SaaS. Sampel vendor kecil dan tidak mewakili seluruh pasar; kurs Rp tidak diverifikasi.
