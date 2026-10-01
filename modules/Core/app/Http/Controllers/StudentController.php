<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\DeleteStudent;
use Modules\Core\App\Domain\Actions\SaveStudent;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\StudentRequest;
use Modules\Core\App\Http\Resources\StudentResource;

final class StudentController
{
    use RendersMasterPage;

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $classId = (string) $request->query('class', '');
        $status = (string) $request->query('status', '');

        $students = Student::query()
            ->with('classGroup')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%"),
            ))
            ->when($classId !== '', fn ($query) => $query->where('class_id', (int) $classId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return $this->renderMaster('Core/Master/Students/Index', [
            'students' => StudentResource::collection($students->getCollection())->resolve(),
            'pagination' => $this->paginationMeta($students),
            'filters' => ['q' => $search, 'class' => $classId, 'status' => $status],
            'classes' => $this->activeYearClasses(),
        ]);
    }

    public function show(Student $student): Response
    {
        $student->load(['classGroup', 'classHistory.academicYear']);

        return $this->renderMaster('Core/Master/Students/Show', [
            'student' => StudentResource::make($student)->resolve(),
            'history' => $student->classHistory->map(fn ($row): array => [
                'year' => $row->academicYear->name,
                'class' => $row->class_name,
                'note' => $row->note,
            ])->values()->all(),
            'classes' => $this->activeYearClasses(),
        ]);
    }

    public function store(StudentRequest $request, SaveStudent $save): RedirectResponse
    {
        $student = $save->handle(null, $request->validated());

        return back()->with('status', "{$student->name} ditambahkan.");
    }

    public function update(StudentRequest $request, Student $student, SaveStudent $save): RedirectResponse
    {
        $save->handle($student, $request->validated());

        return back()->with('status', "Data {$student->name} diperbarui.");
    }

    public function destroy(Student $student, DeleteStudent $delete): RedirectResponse
    {
        $delete->handle($student);

        return to_route('core.master.students')->with('status', "{$student->name} dihapus.");
    }

    /**
     * The classes a student can be placed in: those of the active academic year.
     *
     * @return list<array{id: int, name: string}>
     */
    private function activeYearClasses(): array
    {
        return ClassGroup::query()
            ->whereHas('academicYear', fn ($query) => $query->where('status', AcademicYearStatus::Active->value))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ClassGroup $class): array => ['id' => $class->id, 'name' => $class->name])
            ->all();
    }
}
