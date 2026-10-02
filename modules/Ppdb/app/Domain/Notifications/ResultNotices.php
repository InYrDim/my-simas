<?php

namespace Modules\Ppdb\App\Domain\Notifications;

use Modules\Core\App\Contracts\DTOs\ContactNotice;
use Modules\Core\App\Contracts\DTOs\NoticeKind;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * The WhatsApp notice PPDB can send to an applicant's guardian: what was
 * decided, when the school announces its results. Whether it really goes
 * out, and how it reads, is the school's choice on Integrasi › WhatsApp —
 * nothing here looks at that switch.
 */
final class ResultNotices
{
    public const RESULT = 'ppdb.result';

    /**
     * @return list<NoticeKind>
     */
    public static function kinds(): array
    {
        return [
            new NoticeKind(
                key: self::RESULT,
                title: 'Hasil seleksi PPDB',
                description: 'Dikirim saat hasil seleksi diumumkan, dan saat pendaftar cadangan naik menjadi diterima.',
                recipient: 'Wali pendaftar',
                template: 'Yth. {nama_wali}, hasil seleksi PPDB {nama_sekolah} untuk {nama_siswa} (no. {nomor}, jalur {jalur}): {hasil}. {keterangan}',
                variables: [
                    'nomor' => 'PPDB-27-0001',
                    'jalur' => 'Zonasi',
                    'hasil' => 'Diterima',
                    'keterangan' => 'Silakan melakukan daftar ulang sesuai jadwal sekolah.',
                ],
            ),
        ];
    }

    /**
     * The notice about an applicant who has a decision, to the guardian they
     * gave.
     */
    public function resultOf(Applicant $applicant, string $pathName): ContactNotice
    {
        return new ContactNotice(
            kind: self::RESULT,
            recipientName: $applicant->guardian_name ?? "Orang tua/wali {$applicant->name}",
            phone: $applicant->guardian_phone,
            subjectName: $applicant->name,
            variables: [
                'nomor' => $applicant->number,
                'jalur' => $pathName,
                'hasil' => mb_strtoupper($applicant->decision->label()),
                'keterangan' => $this->guidance($applicant->decision),
            ],
        );
    }

    private function guidance(Decision $decision): string
    {
        return match ($decision) {
            Decision::Accepted => 'Silakan melakukan daftar ulang sesuai jadwal sekolah.',
            Decision::Waitlist => 'Anda masuk daftar cadangan; sekolah akan menghubungi bila ada kursi.',
            Decision::Rejected => 'Terima kasih telah mendaftar.',
            Decision::Pending => '',
        };
    }
}
