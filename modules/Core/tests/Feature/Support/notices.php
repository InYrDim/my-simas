<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Contracts\DTOs\NoticeKind;
use Modules\Core\App\Contracts\NoticeRegistry;

/*
 * A notice kind for the tests, the way a feature module would register
 * one from its service provider.
 */

function absenceNotice(string $key = 'attendance.absent'): NoticeKind
{
    return new NoticeKind(
        key: $key,
        title: 'Siswa tidak hadir',
        description: 'Dikirim saat siswa ditandai sakit, izin, atau alpa.',
        recipient: 'Wali murid',
        template: 'Yth. {nama_wali}, {nama_siswa} tercatat {status} pada {tanggal}. — {nama_sekolah}',
        variables: ['status' => 'sakit', 'tanggal' => '1 Oktober 2026'],
    );
}

function registerAbsenceNotice(string $module = 'core', string $key = 'attendance.absent'): NoticeKind
{
    $kind = absenceNotice($key);

    app(NoticeRegistry::class)->register($module, $kind);

    return $kind;
}
