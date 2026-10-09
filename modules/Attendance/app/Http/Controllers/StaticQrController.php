<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Qr\StaticQrCodes;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\DTOs\StudentRecord;
use Modules\Core\App\Contracts\StudentDirectory;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Cetak QR Statis: a sheet with the printed QR of one active student
 * (`?siswa=`) or of every active student of the active academic year,
 * grouped by class. It is opened from the Siswa list (Core's student
 * actions), behind the ability `attendance.static-qr.print` (settings
 * permission and the school's switch), and laid out for the browser's
 * print dialog.
 */
final class StaticQrController
{
    public function __invoke(Request $request, ClassDirectory $classes, StudentDirectory $students, StaticQrCodes $codes, TenantContext $context): Response
    {
        $groups = $request->filled('siswa')
            ? $this->groupOfOne($students, (int) $request->query('siswa'))
            : $this->groupsOfClasses($classes, $students);

        return Inertia::render('Attendance/StaticQr', [
            'schoolName' => $context->currentOrFail()->name,
            'groups' => array_map(fn (array $group): array => [
                'class' => $group['class'],
                'students' => array_map(fn (StudentRecord $student): array => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'nis' => $student->nis,
                    'code' => $codes->payloadFor($student->id),
                ], $group['students']),
            ], $groups),
        ]);
    }

    /**
     * @return list<array{class: string, students: list<StudentRecord>}>
     */
    private function groupOfOne(StudentDirectory $students, int $id): array
    {
        $student = $students->find($id);

        abort_if($student === null || ! $student->active, 404);

        return [['class' => $student->className ?? 'Tanpa kelas', 'students' => [$student]]];
    }

    /**
     * @return list<array{class: string, students: list<StudentRecord>}>
     */
    private function groupsOfClasses(ClassDirectory $classes, StudentDirectory $students): array
    {
        $groups = [];

        foreach ($classes->ofActiveYear() as $class) {
            $inClass = $students->ofClass($class->id);

            if ($inClass !== []) {
                $groups[] = ['class' => $class->name, 'students' => $inClass];
            }
        }

        return $groups;
    }
}
