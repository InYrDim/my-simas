<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\DeleteExtracurricular;
use Modules\Core\App\Domain\Actions\SaveExtracurricular;
use Modules\Core\App\Domain\Models\Extracurricular;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\ExtracurricularRequest;
use Modules\Core\App\Http\Resources\ExtracurricularResource;
use Modules\Core\App\Http\Resources\StudentResource;
use Modules\Core\App\Http\Resources\TeacherResource;

final class ExtracurricularController
{
    use RendersMasterPage;

    public function index(): Response
    {
        $activities = Extracurricular::query()
            ->with('coach')
            ->withCount('memberships')
            ->orderBy('name')
            ->get();

        return $this->renderMaster('Core/Master/Extracurriculars/Index', [
            'extracurriculars' => ExtracurricularResource::collection($activities)->resolve(),
            'teachers' => TeacherResource::collection(Teacher::query()->orderBy('name')->get())->resolve(),
        ]);
    }

    public function show(Extracurricular $extracurricular): Response
    {
        $extracurricular->load('coach')->loadCount('memberships');

        $members = $extracurricular->members()->with('classGroup')->orderBy('name')->get();

        return $this->renderMaster('Core/Master/Extracurriculars/Show', [
            'extracurricular' => [
                ...ExtracurricularResource::make($extracurricular)->resolve(),
                'memberList' => StudentResource::collection($members)->resolve(),
            ],
            'teachers' => TeacherResource::collection(Teacher::query()->orderBy('name')->get())->resolve(),
        ]);
    }

    public function store(ExtracurricularRequest $request, SaveExtracurricular $save): RedirectResponse
    {
        $activity = $save->handle(null, $request->activityData());

        return back()->with('status', "{$activity->name} ditambahkan.");
    }

    public function update(ExtracurricularRequest $request, Extracurricular $extracurricular, SaveExtracurricular $save): RedirectResponse
    {
        $save->handle($extracurricular, $request->activityData());

        return back()->with('status', "{$extracurricular->name} diperbarui.");
    }

    public function destroy(Extracurricular $extracurricular, DeleteExtracurricular $delete): RedirectResponse
    {
        $delete->handle($extracurricular);

        return to_route('core.master.extracurriculars')->with('status', "{$extracurricular->name} dihapus.");
    }
}
