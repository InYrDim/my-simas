<?php

namespace Modules\Attendance\App\Domain\Students;

use Illuminate\Support\Facades\Gate;
use Modules\Core\App\Contracts\DTOs\StudentAction;
use Modules\Core\App\Contracts\StudentActionProvider;

/**
 * The printing of the static QR on the Siswa list: one student or all of
 * them. Offered only to someone who may print it while the school has the
 * static QR on.
 */
final class StaticQrStudentActions implements StudentActionProvider
{
    public function actions(): array
    {
        if (! Gate::allows('attendance.static-qr.print')) {
            return [];
        }

        return [new StudentAction(
            key: 'static-qr',
            label: 'QR statis',
            allUrl: route('attendance.static-qr'),
            studentUrl: route('attendance.static-qr').'?siswa=',
        )];
    }
}
