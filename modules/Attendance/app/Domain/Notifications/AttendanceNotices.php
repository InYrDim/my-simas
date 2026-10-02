<?php

namespace Modules\Attendance\App\Domain\Notifications;

use Carbon\CarbonInterface;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\DTOs\GuardianNotice;
use Modules\Core\App\Contracts\DTOs\NoticeKind;
use Modules\Core\App\Contracts\GuardianNotifier;

/**
 * The WhatsApp notices attendance can send to guardians, and the moments
 * that send them. Whether a notice really goes out, and how it reads, is
 * the school's choice on Integrasi › WhatsApp — nothing here looks at
 * that switch.
 *
 * Only what happens today is announced: filling in an earlier day tells
 * nobody.
 */
final class AttendanceNotices
{
    public const GATE_IN = 'attendance.gate-in';

    public const GATE_OUT = 'attendance.gate-out';

    public const ABSENT = 'attendance.absent';

    public const LESSON_ABSENT = 'attendance.lesson-absent';

    public function __construct(
        private readonly GuardianNotifier $notifier,
        private readonly SchoolClock $clock,
    ) {}

    /**
     * @return list<NoticeKind>
     */
    public static function kinds(): array
    {
        return [
            new NoticeKind(
                key: self::GATE_IN,
                title: 'Siswa masuk sekolah',
                description: 'Dikirim saat siswa tercatat masuk di gerbang sekolah.',
                recipient: 'Wali murid',
                template: 'Yth. {nama_wali}, {nama_siswa} tercatat masuk sekolah pada {tanggal} pukul {jam} ({status}). - {nama_sekolah}',
                variables: ['tanggal' => 'Senin, 5 Oktober 2026', 'jam' => '06.52', 'status' => 'Hadir'],
            ),
            new NoticeKind(
                key: self::GATE_OUT,
                title: 'Siswa pulang sekolah',
                description: 'Dikirim saat siswa tercatat pulang di gerbang sekolah.',
                recipient: 'Wali murid',
                template: 'Yth. {nama_wali}, {nama_siswa} tercatat pulang dari sekolah pada {tanggal} pukul {jam}. - {nama_sekolah}',
                variables: ['tanggal' => 'Senin, 5 Oktober 2026', 'jam' => '14.05'],
            ),
            new NoticeKind(
                key: self::ABSENT,
                title: 'Siswa tidak hadir',
                description: 'Dikirim saat siswa ditandai sakit, izin, atau alpa pada hari itu.',
                recipient: 'Wali murid',
                template: 'Yth. {nama_wali}, {nama_siswa} tercatat {status} pada {tanggal}. Keterangan: {keterangan}. - {nama_sekolah}',
                variables: ['tanggal' => 'Senin, 5 Oktober 2026', 'status' => 'Alpa', 'keterangan' => '-'],
            ),
            new NoticeKind(
                key: self::LESSON_ABSENT,
                title: 'Siswa alpa di jam pelajaran',
                description: 'Dikirim saat siswa ditandai alpa pada satu jam pelajaran, padahal tidak tercatat sakit, izin, atau alpa hari itu.',
                recipient: 'Wali murid',
                template: 'Yth. {nama_wali}, {nama_siswa} tidak mengikuti pelajaran {mapel} pada {tanggal}, {jam_pelajaran}, tanpa keterangan. - {nama_sekolah}',
                variables: ['tanggal' => 'Senin, 5 Oktober 2026', 'jam_pelajaran' => 'pukul 07.15-08.00', 'mapel' => 'Matematika'],
            ),
        ];
    }

    public function gateIn(int $studentId, CarbonInterface $at, AttendanceStatus $status): void
    {
        $local = $this->clock->local($at);

        $this->notifier->notify(new GuardianNotice($studentId, self::GATE_IN, [
            'tanggal' => $this->clock->dateLabel($local->toDateString()),
            'jam' => $local->format('H.i'),
            'status' => $status->label(),
        ]));
    }

    public function gateOut(int $studentId, CarbonInterface $at): void
    {
        $local = $this->clock->local($at);

        $this->notifier->notify(new GuardianNotice($studentId, self::GATE_OUT, [
            'tanggal' => $this->clock->dateLabel($local->toDateString()),
            'jam' => $local->format('H.i'),
        ]));
    }

    /**
     * A student marked sick, excused or absent for the day.
     */
    public function dailyAbsence(int $studentId, string $date, AttendanceStatus $status, ?string $note): void
    {
        if ($date !== $this->clock->today()) {
            return;
        }

        $this->notifier->notify(new GuardianNotice($studentId, self::ABSENT, [
            'tanggal' => $this->clock->dateLabel($date),
            'status' => $status->label(),
            'keterangan' => $note === null || trim($note) === '' ? '-' : trim($note),
        ]));
    }

    /**
     * A student marked absent in one lesson.
     *
     * @param  string  $startsAt  H:i
     * @param  string  $endsAt  H:i
     */
    public function lessonAbsence(int $studentId, string $date, string $startsAt, string $endsAt, ?string $subject): void
    {
        if ($date !== $this->clock->today()) {
            return;
        }

        $this->notifier->notify(new GuardianNotice($studentId, self::LESSON_ABSENT, [
            'tanggal' => $this->clock->dateLabel($date),
            'jam_pelajaran' => 'pukul '.str_replace(':', '.', $startsAt).'-'.str_replace(':', '.', $endsAt),
            'mapel' => $subject ?? '(mapel tidak dicatat)',
        ]));
    }
}
