<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\StudentClassHistory;
use Modules\Identity\App\Contracts\AccountProvisioner;
use Modules\Identity\App\Contracts\Exceptions\AccountActionRefusedException;
use Modules\Identity\App\Contracts\Exceptions\UsernameTakenException;

/**
 * Creates or updates a student and keeps the class history in step: only
 * an active student has a class, placing one writes the history row of the
 * class's academic year, and leaving (graduated, transferred, left) marks
 * the latest row.
 *
 * A student's account follows the record: it takes the new name and NIS,
 * is deactivated when the student leaves and reactivated on return.
 */
final class SaveStudent
{
    private const LEAVING_NOTES = [
        'graduated' => 'Lulus',
        'transferred' => 'Pindah sekolah',
        'left' => 'Keluar',
    ];

    public function __construct(private readonly AccountProvisioner $accounts) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the account cannot follow the change
     */
    public function handle(?Student $student, array $data): Student
    {
        $student ??= new Student(['status' => 'active']);

        return DB::transaction(function () use ($student, $data): Student {
            $previousStatus = $student->exists ? $student->status : null;

            $student->fill($data);

            if (! $student->isActive()) {
                $student->class_id = null;
            }

            $identityChanged = $student->exists && $student->isDirty(['name', 'nis']);

            $student->save();

            $this->recordHistory($student, $previousStatus);
            $this->syncAccount($student, $previousStatus, $identityChanged);

            return $student;
        });
    }

    private function recordHistory(Student $student, ?string $previousStatus): void
    {
        if ($student->class_id !== null) {
            $class = ClassGroup::query()->findOrFail($student->class_id);

            $row = StudentClassHistory::query()->firstOrNew([
                'student_id' => $student->id,
                'academic_year_id' => $class->academic_year_id,
            ]);

            $note = $row->exists && $row->class_id !== $class->id
                ? 'Pindah kelas'
                : ($row->note ?: 'Kelas aktif');

            $row->fill([
                'class_id' => $class->id,
                'class_name' => $class->name,
                'note' => $note,
            ])->save();

            return;
        }

        if ($previousStatus === 'active' && isset(self::LEAVING_NOTES[$student->status])) {
            StudentClassHistory::query()
                ->where('student_id', $student->id)
                ->orderByDesc('academic_year_id')
                ->first()
                ?->update(['note' => self::LEAVING_NOTES[$student->status]]);
        }
    }

    private function syncAccount(Student $student, ?string $previousStatus, bool $identityChanged): void
    {
        if ($student->user_id === null) {
            return;
        }

        try {
            if ($identityChanged) {
                $this->accounts->updateIdentity($student->user_id, $student->name, $student->nis);
            }

            if ($previousStatus === 'active' && ! $student->isActive()) {
                $this->accounts->deactivate($student->user_id);
            } elseif ($previousStatus !== null && $previousStatus !== 'active' && $student->isActive()) {
                $this->accounts->reactivate($student->user_id);
            }
        } catch (UsernameTakenException) {
            throw ValidationException::withMessages([
                'nis' => 'NIS ini sudah dipakai sebagai nama pengguna akun lain.',
            ]);
        } catch (AccountActionRefusedException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }
    }
}
