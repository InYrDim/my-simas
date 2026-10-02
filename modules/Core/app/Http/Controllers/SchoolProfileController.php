<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\SaveSchoolProfile;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\SchoolProfileRequest;

final class SchoolProfileController
{
    use RendersMasterPage;

    public function show(SaveSchoolProfile $save): Response
    {
        return $this->renderMaster('Core/Master/School/Show', [
            'levelLocked' => $save->isLevelLocked(),
        ]);
    }

    public function update(SchoolProfileRequest $request, SaveSchoolProfile $save): RedirectResponse
    {
        $save->handle($request->validated());

        return back()->with('status', 'Profil sekolah disimpan.');
    }
}
