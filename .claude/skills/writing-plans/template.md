# <Judul: hasil yang dituju, bukan nama teknis>

> **Status dokumen:** Draf | Disetujui | Berjalan | Selesai
> **Dibuat:** YYYY-MM-DD · **Diperbarui:** YYYY-MM-DD · **Branch:** `<nama-branch>`

## Context
<Masalah atau kebutuhan, apa pemicunya, dan kondisi sekarang di kode (dengan path). Dua sampai empat paragraf pendek. Pembaca yang tidak ikut obrolan harus paham mengapa pekerjaan ini ada.>

## Goal
<Satu sampai tiga kalimat hasil yang bisa diamati: "Setelah ini, <siapa> bisa <melakukan apa> dan <melihat apa>.">

## Batasan masalah

**Dalam cakupan**
- <hal yang dikerjakan>

**Di luar cakupan**
- <hal yang sengaja tidak dikerjakan> — <alasan singkat / kapan dikerjakan>

**Asumsi**
- <hal yang dianggap benar dan akan mengubah rencana bila salah>

## Batasan teknis & keputusan

**Aturan yang mengikat**
- <aturan arsitektur yang relevan> (sumber: `<path dokumen aturan>`)

**Keputusan**
| Tanggal | Keputusan | Diputuskan oleh |
| --- | --- | --- |
| YYYY-MM-DD | <keputusan> | user / hasil baca kode |

## Desain
<Per modul. Bedakan permukaan publik (`Contracts/`) dari internal. Sebut tabel, kontrak, route, dan halaman yang berubah. Kode yang dipakai ulang disebut dengan path-nya.>

### <Modul A>
- <keputusan desain>

### Alur
1. <langkah alur dari sudut pengguna>

## Tahapan & status
Legenda: ⬜ belum · 🟡 berjalan · ✅ selesai. Berhenti di antara tahap dan tunggu persetujuan.

| Tahap | Cakupan | Status | Catatan |
| --- | --- | --- | --- |
| 1 | <cakupan satu tahap vertikal> | ⬜ | |
| 2 | <…> | ⬜ | |

## Tasks

### Tahap 1 — <nama>
- [ ] <task> — `<path/file yang sudah ada>`
- [ ] <task> — `<path/file>` (baru)
- [ ] Tes: <kasus yang dicakup> — `<path/tes>`

**Selesai bila:** <hasil yang bisa diamati>. Bukti: `<perintah persis>` → <hasil yang diharapkan>.

### Tahap 2 — <nama>
- [ ] <task> — `<path/file>`

**Selesai bila:** <…>. Bukti: `<perintah>` → <hasil>.

## Batas tindakan
**Selalu**
- <tindakan aman yang wajib, mis. jalankan Pint dan tes modul sebelum menandai tahap selesai>

**Tanya dulu**
- <tindakan berdampak, mis. mengubah signature kontrak publik, mengubah keputusan terkunci>

**Jangan**
- <larangan, mis. menghapus tes, menambah FK selain `tenant_id`, push tanpa diminta>

## Pengujian
- **Tahap 1:** <kasus: jalur utama, validasi, izin, isolasi antar tenant>
- **Tahap 2:** <…>

## Verifikasi end-to-end
1. `<perintah persis>` → <hasil yang diharapkan>
2. <langkah manual di aplikasi> → <yang terlihat>

## Catatan pelaksanaan
Diisi selama eksekusi; dokumen ini hidup.

### Log keputusan
- YYYY-MM-DD — <perubahan dari rencana> — <alasan>

### Temuan
- <hal tak terduga: aturan yang menjegal, asumsi yang salah, jebakan yang perlu diingat>

### Hasil akhir
<Diisi saat semua tahap selesai: apa yang jadi, apa yang tersisa, tindak lanjut.>
