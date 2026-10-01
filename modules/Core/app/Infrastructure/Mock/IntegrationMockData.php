<?php

namespace Modules\Core\App\Infrastructure\Mock;

/**
 * Static sample data for the Integrasi mockup pages. Nothing here is
 * persisted and no message is ever sent.
 */
final class IntegrationMockData
{
    /**
     * @return array{connected: bool, number: string, deviceName: string, lastSyncLabel: string, sentThisMonth: int, quota: int}
     */
    public function whatsappConnection(): array
    {
        return [
            'connected' => true,
            'number' => '+62 812-3456-7890',
            'deviceName' => 'HP Tata Usaha',
            'lastSyncLabel' => '5 menit lalu',
            'sentThisMonth' => 318,
            'quota' => 1000,
        ];
    }

    /**
     * @return list<array{key: string, title: string, description: string, recipient: string, enabled: bool}>
     */
    public function whatsappNotifications(): array
    {
        return [
            ['key' => 'absent', 'title' => 'Siswa tidak hadir', 'description' => 'Dikirim saat siswa ditandai sakit, izin, atau alpa.', 'recipient' => 'Wali murid', 'enabled' => true],
            ['key' => 'ppdb-result', 'title' => 'Hasil seleksi PPDB', 'description' => 'Dikirim saat hasil diumumkan.', 'recipient' => 'Pendaftar', 'enabled' => true],
            ['key' => 'invoice', 'title' => 'Pengingat tagihan langganan', 'description' => 'Dikirim 3 hari sebelum jatuh tempo.', 'recipient' => 'Admin Sekolah', 'enabled' => false],
            ['key' => 'announcement', 'title' => 'Pengumuman sekolah', 'description' => 'Dikirim saat pengumuman baru dipublikasikan.', 'recipient' => 'Wali murid', 'enabled' => false],
        ];
    }

    /**
     * @return array{body: string, variables: list<string>, sample: array<string, string>}
     */
    public function whatsappTemplate(): array
    {
        return [
            'body' => 'Yth. Bapak/Ibu wali dari {nama_siswa}, pada {tanggal} ananda tercatat {status}. Hubungi wali kelas jika ada pertanyaan. — {nama_sekolah}',
            'variables' => ['nama_siswa', 'tanggal', 'status', 'nama_sekolah'],
            'sample' => [
                'nama_siswa' => 'Aditya Pratama',
                'tanggal' => '1 Oktober 2026',
                'status' => 'sakit',
                'nama_sekolah' => 'SMA Contoh',
            ],
        ];
    }

    /**
     * @return list<array{id: int, sentAt: string, to: string, kind: string, status: string}>
     */
    public function whatsappHistory(): array
    {
        return [
            ['id' => 1, 'sentAt' => '1 Okt 2026, 07.42', 'to' => '+62 812-••••-1180', 'kind' => 'Siswa tidak hadir', 'status' => 'read'],
            ['id' => 2, 'sentAt' => '1 Okt 2026, 07.42', 'to' => '+62 857-••••-9024', 'kind' => 'Siswa tidak hadir', 'status' => 'delivered'],
            ['id' => 3, 'sentAt' => '30 Sep 2026, 15.10', 'to' => '+62 813-••••-5531', 'kind' => 'Hasil seleksi PPDB', 'status' => 'read'],
            ['id' => 4, 'sentAt' => '30 Sep 2026, 15.10', 'to' => '+62 878-••••-4402', 'kind' => 'Hasil seleksi PPDB', 'status' => 'failed'],
            ['id' => 5, 'sentAt' => '29 Sep 2026, 07.40', 'to' => '+62 821-••••-7765', 'kind' => 'Siswa tidak hadir', 'status' => 'delivered'],
        ];
    }
}
