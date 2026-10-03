<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Core\App\Domain\Actions\DeleteMajor;
use Modules\Core\App\Domain\Actions\SaveMajor;
use Modules\Core\App\Domain\Models\Major;
use Modules\Core\App\Http\Requests\MajorRequest;

final class MajorController
{
    public function store(MajorRequest $request, SaveMajor $save): RedirectResponse
    {
        $major = $save->handle(null, $request->majorData());

        return back()->with('status', "Jurusan {$major->code} ditambahkan.");
    }

    public function update(MajorRequest $request, Major $major, SaveMajor $save): RedirectResponse
    {
        $save->handle($major, $request->majorData());

        return back()->with('status', "Jurusan {$major->code} diperbarui.");
    }

    public function destroy(Major $major, DeleteMajor $delete): RedirectResponse
    {
        $delete->handle($major);

        return back()->with('status', "Jurusan {$major->code} dihapus.");
    }
}
