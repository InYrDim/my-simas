<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\AssignHomerooms;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\HomeroomRequest;
use Modules\Core\App\Http\Resources\ClassGroupResource;
use Modules\Core\App\Http\Resources\TeacherResource;

final class HomeroomController
{
    use RendersMasterPage;

    public function index(): Response
    {
        $classes = ClassGroup::query()
            ->whereHas('academicYear', fn ($query) => $query->where('status', AcademicYearStatus::Active->value))
            ->with(['grade', 'major', 'room', 'homeroom', 'academicYear'])
            ->orderBy('name')
            ->get();

        return $this->renderMaster('Core/Academic/Homerooms/Index', [
            'classes' => ClassGroupResource::collection($classes)->resolve(),
            'teachers' => TeacherResource::collection(Teacher::query()->orderBy('name')->orderBy('id')->get())->resolve(),
        ]);
    }

    public function update(HomeroomRequest $request, AssignHomerooms $assign): RedirectResponse
    {
        $assign->handle($request->homerooms());

        return back()->with('status', 'Penetapan wali kelas disimpan.');
    }
}
