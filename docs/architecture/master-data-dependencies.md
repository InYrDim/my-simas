# Dependensi halaman Master Data dan Warga Sekolah

Dibaca dari route, FormRequest, action, dan model di `modules/Core` (belum dicoba di aplikasi yang berjalan). Tidak ada foreign key di database; semua syarat dan penjaga hapus ditegakkan di request dan action.

**Cara membaca diagram:** panah menunjuk dari data yang **harus ada lebih dulu** ke halaman yang membutuhkannya. Garis solid = wajib, garis putus-putus = opsional.

- **Hijau**: berdiri sendiri, bisa diisi tanpa data lain.
- **Ungu**: turunan otomatis, tidak diisi manual.
- **Oranye**: bergantung pada data lain.
- **Abu-abu**: halaman Akademik (di luar Master Data), hanya untuk konteks.

## 1. Prasyarat membuat data baru

```mermaid
flowchart LR
    classDef ind fill:#d6efe9,stroke:#23806f,color:#14181f
    classDef auto fill:#e4e1f5,stroke:#6258b0,color:#14181f
    classDef dep fill:#fbe3cb,stroke:#b9610f,color:#14181f
    classDef akd fill:#eceff3,stroke:#7a8594,color:#14181f

    subgraph MD["Master Data"]
        Profil["Profil Sekolah<br/>/master/sekolah"]:::ind
        Tahun["Tahun Ajaran<br/>/master/tahun-ajaran"]:::ind
        Jurusan["Jurusan<br/>/master/tingkat-jurusan"]:::ind
        Ruangan["Ruangan<br/>/master/ruangan"]:::ind
        Mapel["Mata Pelajaran<br/>/master/mata-pelajaran"]:::ind
        Tingkat["Tingkat<br/>diisi dari jenjang"]:::auto
        Semester["Semester<br/>dibuat dari tahun ajaran"]:::auto
        Kelas["Kelas<br/>/master/kelas"]:::dep
        Ekskul["Ekstrakurikuler<br/>/master/ekstrakurikuler"]:::dep
    end

    subgraph WS["Warga Sekolah"]
        Guru["Guru dan Tendik<br/>/master/guru"]:::ind
        Siswa["Siswa<br/>/master/siswa"]:::dep
    end

    subgraph AK["Akademik (konteks)"]
        Wali["Wali Kelas"]:::akd
        Pengampu["Pengampu Mapel"]:::akd
        Penempatan["Penempatan Siswa"]:::akd
    end

    Profil -->|"seed otomatis"| Tingkat
    Tahun -->|"dibuat otomatis"| Semester

    Tahun -->|"wajib"| Kelas
    Tingkat -->|"wajib"| Kelas
    Jurusan -->|"wajib bila jurusan sudah ada"| Kelas
    Ruangan -.->|"opsional"| Kelas

    Kelas -.->|"opsional (Belum ditempatkan)"| Siswa
    Guru -.->|"pembina, opsional"| Ekskul
    Siswa -.->|"anggota, tambah via NIS"| Ekskul

    Guru --> Wali
    Kelas --> Wali
    Mapel --> Pengampu
    Guru --> Pengampu
    Kelas --> Pengampu
    Siswa --> Penempatan
    Kelas --> Penempatan
```

## 2. Prasyarat per halaman

| Halaman | Grup | Wajib ada dulu | Opsional | Hanya dibaca untuk tampilan |
| --- | --- | --- | --- | --- |
| Profil Sekolah | Master Data | tidak ada | tidak ada | tidak ada |
| Tahun Ajaran | Master Data | tidak ada | tidak ada | tidak ada |
| Semester | Master Data | Tahun Ajaran (barisnya lahir dari tahun ajaran; hanya bisa diubah) | tidak ada | tidak ada |
| Tingkat | Master Data | Profil Sekolah (jenjang menentukan isinya; tidak diedit manual) | tidak ada | Tahun Ajaran aktif |
| Jurusan | Master Data | tidak ada | tidak ada | tidak ada |
| **Kelas** | Master Data | Tahun Ajaran, Tingkat; Jurusan bila sudah ada jurusan | Ruangan | tidak ada |
| **Mata Pelajaran** | Master Data | **tidak ada** | tidak ada | nama Tingkat |
| Ruangan | Master Data | tidak ada | tidak ada | tidak ada |
| Ekstrakurikuler | Master Data | tidak ada | Guru (pembina); Siswa untuk anggota | tidak ada |
| **Siswa** | Warga Sekolah | tidak ada | Kelas tahun ajaran aktif, tanggal lahir, NISN, data wali | tidak ada |
| **Guru dan Tendik** | Warga Sekolah | tidak ada | NIP, NUPTK, email | tidak ada |

Field wajib sendiri: Siswa butuh nama, NIS (unik per sekolah), dan jenis kelamin; Guru butuh nama, status kepegawaian, dan tugas. NISN dan NIP bila diisi juga harus unik per sekolah.

### Jawaban atas contoh Mata Pelajaran

Membuat mata pelajaran baru **tidak membutuhkan data Siswa maupun Guru**. `SubjectRequest` hanya memvalidasi kode, nama, kelompok, dan KKM. Siswa dan Guru baru terlibat di Akademik › Pengampu Mapel, yang butuh Mata Pelajaran, Guru, dan Kelas. Itu langkah terpisah dan tidak menghalangi pembuatan mapel.

## 3. Penjaga hapus: siapa mengunci siapa

Panah menunjuk dari data yang masih memakai ke data yang **tidak bisa dihapus** selama dipakai.

```mermaid
flowchart LR
    classDef ind fill:#d6efe9,stroke:#23806f,color:#14181f
    classDef dep fill:#fbe3cb,stroke:#b9610f,color:#14181f
    classDef akd fill:#eceff3,stroke:#7a8594,color:#14181f

    Siswa["Siswa"]:::dep
    Kelas["Kelas"]:::dep
    Ekskul["Ekstrakurikuler"]:::dep
    Pengampu["Pengampu Mapel"]:::akd

    Jurusan["Jurusan"]:::ind
    Ruangan["Ruangan"]:::ind
    Mapel["Mata Pelajaran"]:::ind
    Guru["Guru dan Tendik"]:::ind
    Jenjang["Jenjang di Profil Sekolah"]:::ind
    Riwayat["Riwayat kelas dan<br/>keanggotaan ekskul"]:::akd

    Siswa -->|"masih ada siswa"| Kelas
    Kelas -->|"dipakai kelas"| Jurusan
    Kelas -->|"dipakai kelas"| Ruangan
    Kelas -->|"sudah ada kelas: jenjang terkunci"| Jenjang
    Pengampu -->|"masih diampu"| Mapel
    Pengampu -->|"masih mengampu"| Guru
    Kelas -->|"masih wali kelas"| Guru
    Ekskul -->|"masih pembina"| Guru
    Siswa -.->|"hapus siswa ikut menghapus"| Riwayat
```

Dua aturan di luar diagram: Tahun Ajaran hanya bisa dihapus saat berstatus draf, dan hapus Kelas ikut membersihkan baris pengampunya.

## 4. Siapa memakai Siswa dan Guru

Siswa dan Guru adalah data paling banyak dipakai. Modul lain tidak mengimpor model Core; semuanya lewat kontrak (DTO), sesuai aturan modular monolith.

```mermaid
flowchart LR
    classDef core fill:#fbe3cb,stroke:#b9610f,color:#14181f
    classDef mod fill:#eceff3,stroke:#7a8594,color:#14181f

    Siswa["Siswa"]:::core
    Guru["Guru dan Tendik"]:::core

    Absensi["Absensi"]:::mod
    Ppdb["PPDB"]:::mod
    Identity["Identity: akun login"]:::mod
    WA["WhatsApp: notifikasi wali"]:::mod
    Lap["Laporan dan Statistik"]:::mod

    Siswa -->|"StudentDirectory"| Absensi
    Siswa -->|"GuardianNotifier"| WA
    Ppdb -->|"StudentAdmission: daftar ulang"| Siswa
    Siswa -->|"AccountProvisioner: NIS + tgl lahir"| Identity
    Guru -->|"AccountProvisioner: NIP"| Identity
    Guru -->|"ClassDirectory: kelas yang diampu"| Absensi
    Siswa --> Lap
    Guru --> Lap
```

Akibatnya, mengubah NIS atau NIP ikut mengubah nama pengguna akun login, dan siswa yang lulus, pindah, atau keluar otomatis dinonaktifkan akunnya.
