<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\DeleteClassGroup;
use Modules\Core\App\Domain\Actions\SaveClassGroup;
use Modules\Core\App\Domain\Actions\SyncDefaultGrades;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Major;
use Modules\Core\App\Domain\Models\Room;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\ClassGroupRequest;
use Modules\Core\App\Http\Resources\AcademicYearResource;
use Modules\Core\App\Http\Resources\ClassGroupResource;
use Modules\Core\App\Http\Resources\GradeResource;
use Modules\Core\App\Http\Resources\MajorResource;
use Modules\Core\App\Http\Resources\RoomResource;
use Modules\Core\App\Http\Resources\StudentResource;
use Modules\Core\App\Http\Resources\TeachingAssignmentResource;

final class ClassGroupController
{
    use RendersMasterPage;

    public function index(SyncDefaultGrades $syncGrades): Response
    {
        $syncGrades->handle(SchoolProfile::current());

        $classes = ClassGroup::query()
            ->with(['grade', 'major', 'room', 'homeroom', 'academicYear'])
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return $this->renderMaster('Core/Master/Classes/Index', [
            'classes' => ClassGroupResource::collection($classes)->resolve(),
            'grades' => GradeResource::collection(Grade::query()->orderBy('sort_order')->get())->resolve(),
            'majors' => MajorResource::collection(Major::query()->orderBy('code')->get())->resolve(),
            'rooms' => RoomResource::collection(Room::query()->orderBy('code')->get())->resolve(),
            'years' => AcademicYearResource::collection(
                AcademicYear::query()->with('semesters')->orderByDesc('start_date')->get(),
            )->resolve(),
        ]);
    }

    public function show(ClassGroup $classGroup): Response
    {
        $classGroup->load(['grade', 'major', 'room', 'homeroom', 'academicYear'])->loadCount('students');

        $students = $classGroup->students()->with('classGroup')->orderBy('name')->get();

        return $this->renderMaster('Core/Master/Classes/Show', [
            'class' => ClassGroupResource::make($classGroup)->resolve(),
            'students' => StudentResource::collection($students)->resolve(),
            'canCreateAccounts' => Gate::allows('core.master.manage') && Gate::allows('identity.users.create'),
            'assignments' => TeachingAssignmentResource::collection(
                $classGroup->teachingAssignments()->with(['subject', 'teacher', 'classGroup'])->get()->sortBy('subject.name')->values(),
            )->resolve(),
        ]);
    }

    public function store(ClassGroupRequest $request, SaveClassGroup $save): RedirectResponse
    {
        $class = $save->handle(null, $request->validated());

        return back()->with('status', "Kelas {$class->name} ditambahkan.");
    }

    public function update(ClassGroupRequest $request, ClassGroup $classGroup, SaveClassGroup $save): RedirectResponse
    {
        $save->handle($classGroup, $request->validated());

        return back()->with('status', "Kelas {$classGroup->name} diperbarui.");
    }

    public function destroy(ClassGroup $classGroup, DeleteClassGroup $delete): RedirectResponse
    {
        $delete->handle($classGroup);

        return back()->with('status', "Kelas {$classGroup->name} dihapus.");
    }
}
