<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\DeleteTeacher;
use Modules\Core\App\Domain\Actions\SaveTeacher;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Http\Concerns\DescribesLinkedAccount;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\TeacherRequest;
use Modules\Core\App\Http\Resources\ClassGroupResource;
use Modules\Core\App\Http\Resources\TeacherResource;
use Modules\Core\App\Http\Resources\TeachingAssignmentResource;

final class TeacherController
{
    use DescribesLinkedAccount, RendersMasterPage;

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $employment = (string) $request->query('employment', '');

        $teachers = Teacher::query()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('nuptk', 'like', "%{$search}%"),
            ))
            ->when($employment !== '', fn ($query) => $query->where('employment', $employment))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return $this->renderMaster('Core/Master/Teachers/Index', [
            'teachers' => TeacherResource::collection($teachers->getCollection())->resolve(),
            'pagination' => $this->paginationMeta($teachers),
            'filters' => ['q' => $search, 'employment' => $employment],
        ]);
    }

    public function show(Teacher $teacher): Response
    {
        $homeroomOf = $teacher->homeroomClasses()
            ->with(['grade', 'major', 'room', 'homeroom', 'academicYear'])
            ->orderBy('name')
            ->get();

        return $this->renderMaster('Core/Master/Teachers/Show', [
            'teacher' => TeacherResource::make($teacher)->resolve(),
            'login' => $this->linkedAccount($teacher->user_id),
            'assignments' => TeachingAssignmentResource::collection(
                $teacher->teachingAssignments()->with(['subject', 'teacher', 'classGroup'])->get()->sortBy('subject.name')->values(),
            )->resolve(),
            'homeroomOf' => ClassGroupResource::collection($homeroomOf)->resolve(),
        ]);
    }

    public function store(TeacherRequest $request, SaveTeacher $save): RedirectResponse
    {
        $teacher = $save->handle(null, $request->teacherData());

        return back()->with('status', "{$teacher->name} ditambahkan.");
    }

    public function update(TeacherRequest $request, Teacher $teacher, SaveTeacher $save): RedirectResponse
    {
        $save->handle($teacher, $request->teacherData());

        return back()->with('status', "Data {$teacher->name} diperbarui.");
    }

    public function destroy(Teacher $teacher, DeleteTeacher $delete): RedirectResponse
    {
        $delete->handle($teacher);

        return to_route('core.master.teachers')->with('status', "{$teacher->name} dihapus.");
    }
}
