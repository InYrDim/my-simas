<?php

namespace Modules\Core\App\Domain\Actions;

use Modules\Core\App\Domain\Models\Extracurricular;

final class SaveExtracurricular
{
    /**
     * @param  array{name: string, coach_teacher_id?: int|null, schedule?: string|null, kind: string}  $data
     */
    public function handle(?Extracurricular $extracurricular, array $data): Extracurricular
    {
        $extracurricular ??= new Extracurricular;
        $extracurricular->fill($data)->save();

        return $extracurricular;
    }
}
