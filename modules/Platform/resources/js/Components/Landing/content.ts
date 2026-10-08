import { login as schoolLogin } from '@/routes';
import { login as applicantLogin, register } from '@/routes/applicant';
import { register as ppdbRegister } from '@/routes/ppdb/account';

/**
 * What the landing page says, in one place. Only product truth lives
 * here: the modules that exist, the real registration flow, the default
 * roles. No customer, figure or price may be added — there is none yet.
 */
export const links = {
    register: register.url(),
    applicantLogin: applicantLogin.url(),
    schoolLogin: schoolLogin.url(),
    ppdbRegister: ppdbRegister.url(),
};

export type LandingModule = {
    key: string;
    name: string;
    summary: string;
    detail: string[];
};

export const modules: LandingModule[] = [
    {
        key: 'absensi',
        name: 'Absensi',
        summary:
            'Kehadiran di gerbang dan per jam pelajaran, dicatat dari HP guru.',
        detail: [
            'Siswa menunjukkan QR sekali pakai, atau guru mengisi manual',
            'Riwayat dan input manual menjadi jalur koreksi',
            'Tidak ada yang ditandai alpa secara otomatis',
        ],
    },
    {
        key: 'ppdb',
        name: 'PPDB',
        summary:
            'Penerimaan siswa baru dengan gelombang, jalur, kuota, dan formulir buatan sekolah.',
        detail: [
            'Calon siswa mendaftar dengan kode atau tautan sekolah',
            'Panitia memverifikasi, meminta perbaikan, dan memutuskan per jalur',
            'Daftar ulang langsung menjadi data siswa',
        ],
    },
    {
        key: 'induk',
        name: 'Data induk',
        summary:
            'Siswa, guru, kelas, dan mata pelajaran, dengan impor dari berkas.',
        detail: [
            'Akun siswa dan guru dibuat dari NIS atau NIP',
            'Kata sandi pertama wajib diganti saat masuk',
            'Siswa yang keluar dinonaktifkan, tidak dihapus',
        ],
    },
    {
        key: 'akademik',
        name: 'Akademik',
        summary:
            'Jam pelajaran, jadwal kelas, dan jadwal mengajar setiap guru.',
        detail: [
            'Guru membuka "Jadwal Saya" dan "Kelas Mengajar"',
            'Siswa melihat hari dan bulannya di "Kelas Saya"',
            'Waktu mengikuti zona waktu sekolah sendiri',
        ],
    },
    {
        key: 'whatsapp',
        name: 'WhatsApp sekolah',
        summary:
            'Pemberitahuan ke wali dari nomor WhatsApp milik sekolah sendiri.',
        detail: [
            'Sekolah menautkan nomornya sendiri dengan QR',
            'Setiap jenis pesan mati sampai sekolah menyalakannya',
            'Sekolah menulis sendiri isi pesannya',
        ],
    },
    {
        key: 'laporan',
        name: 'Statistik & laporan',
        summary: 'Beranda per peran, statistik, dan laporan dari setiap modul.',
        detail: [
            'Kantor melihat kehadiran hari ini dan pendaftar yang menunggu',
            'Guru melihat pelajaran hari ini',
            'Laporan dapat dibuka per modul',
        ],
    },
];

export type LandingStep = { title: string; body: string };

/** The real onboarding ritual, in order (Platform's ApplicantShell + approval). */
export const steps: LandingStep[] = [
    {
        title: 'Buat akun pemohon',
        body: 'Nama, email, dan kata sandi orang yang mengajukan sekolah.',
    },
    {
        title: 'Verifikasi email',
        body: 'Buka tautan yang kami kirim ke email Anda.',
    },
    {
        title: 'Isi data sekolah dan pilih paket',
        body: 'Sekolah mulai dengan masa trial, tanpa pembayaran.',
    },
    {
        title: 'Tim kami meninjau',
        body: 'Data sekolah dapat dikoreksi saat pengajuan disetujui.',
    },
    {
        title: 'Admin sekolah masuk',
        body: 'Email aktivasi terkirim; admin mengatur kata sandi lalu masuk.',
    },
];

export type LandingPrinciple = { title: string; body: string };

export const principles: LandingPrinciple[] = [
    {
        title: 'Satu sekolah, satu ruang kerja',
        body: 'Data, akun, peran, dan pengaturan setiap sekolah terpisah dari sekolah lain.',
    },
    {
        title: 'Akun dibuat oleh sekolah',
        body: 'Guru, staf, dan siswa tidak mendaftar sendiri. Admin sekolah yang membuat atau mengundang.',
    },
    {
        title: 'Tidak ada pesan yang tidak dinyalakan',
        body: 'SIMAS tidak mengirim WhatsApp untuk jenis pesan yang belum dinyalakan sekolah.',
    },
    {
        title: 'Dirancang untuk HP dan sinyal lemah',
        body: 'Guru mencatat dari HP di sela jam mengajar; kantor mengelola dari komputer.',
    },
];

export type RoleKey = 'guru' | 'staf' | 'admin' | 'siswa';

export const roles: { key: RoleKey; label: string }[] = [
    { key: 'guru', label: 'Guru' },
    { key: 'staf', label: 'Staf/TU' },
    { key: 'admin', label: 'Admin Sekolah' },
    { key: 'siswa', label: 'Siswa' },
];

/** Default-role summary. Only cells the shipped roles really grant. */
export const roleTasks: { task: string; roles: RoleKey[] }[] = [
    { task: 'Mencatat kehadiran per jam pelajaran', roles: ['guru', 'admin'] },
    { task: 'Mencatat kehadiran harian di gerbang', roles: ['staf', 'admin'] },
    { task: 'Memverifikasi pendaftar PPDB', roles: ['staf', 'admin'] },
    { task: 'Mengelola akun dan peran', roles: ['admin'] },
    { task: 'Menunjukkan QR kehadiran', roles: ['siswa'] },
    { task: 'Melihat kehadiran sendiri', roles: ['siswa'] },
];
