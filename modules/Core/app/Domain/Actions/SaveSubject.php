<?php

namespace Modules\Core\App\Domain\Actions;

use Modules\Core\App\Domain\Models\Subject;

final class SaveSubject
{
    /**
     * @param  array{code: string, name: string, group: string, kkm: int}  $data
     */
    public function handle(?Subject $subject, array $data): Subject
    {
        $subject ??= new Subject;
        $subject->fill($data)->save();

        return $subject;
    }
}
