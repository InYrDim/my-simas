<?php

namespace Modules\Core\App\Domain\Actions;

use Modules\Core\App\Domain\Models\Major;

final class SaveMajor
{
    /**
     * @param  array{code: string, name: string, kind: string, concentrations?: list<string>}  $data
     */
    public function handle(?Major $major, array $data): Major
    {
        $major ??= new Major;
        $major->fill($data)->save();

        return $major;
    }
}
