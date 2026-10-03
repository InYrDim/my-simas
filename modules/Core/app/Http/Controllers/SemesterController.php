<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\UpdateSemester;
use Modules\Core\App\Domain\Models\Semester;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\SemesterRequest;
use Modules\Core\App\Http\Resources\SemesterResource;

final class SemesterController
{
    use RendersMasterPage;

    public function index(): Response
    {
        $semesters = Semester::query()
            ->with('academicYear')
            ->orderByDesc('start_date')
            ->get();

        return $this->renderMaster('Core/Master/Semesters/Index', [
            'semesters' => SemesterResource::collection($semesters)->resolve(),
        ]);
    }

    public function update(SemesterRequest $request, Semester $semester, UpdateSemester $update): RedirectResponse
    {
        $update->handle($semester, $request->semesterData());

        return back()->with('status', "Semester {$semester->name} diperbarui.");
    }
}
