<?php

namespace Modules\Core\App\Domain\Actions;

use Modules\Core\App\Domain\Models\Teacher;

final class SaveTeacher
{
    /**
     * @param  array{name: string, nip?: string|null, nuptk?: string|null, employment: string, duty: string, email?: string|null}  $data
     */
    public function handle(?Teacher $teacher, array $data): Teacher
    {
        $teacher ??= new Teacher;
        $teacher->fill($data)->save();

        return $teacher;
    }
}
