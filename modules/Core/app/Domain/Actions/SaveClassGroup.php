<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Major;

final class SaveClassGroup
{
    /**
     * A school that has defined majors must put every class in one.
     *
     * @param  array{academic_year_id: int, grade_id: int, major_id?: int|null, room_id?: int|null, name: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(?ClassGroup $class, array $data): ClassGroup
    {
        if (($data['major_id'] ?? null) === null && Major::query()->exists()) {
            throw ValidationException::withMessages(['major_id' => 'Jurusan wajib dipilih.']);
        }

        $class ??= new ClassGroup;
        $class->fill($data)->save();

        return $class;
    }
}
