<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\SaveClassAssignments;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\TeachingAssignmentRequest;
use Modules\Core\App\Http\Resources\ClassGroupResource;
use Modules\Core\App\Http\Resources\SubjectResource;
use Modules\Core\App\Http\Resources\TeacherResource;
use Modules\Core\App\Http\Resources\TeachingAssignmentResource;

final class TeachingAssignmentController
{
    use RendersMasterPage;

    public function index(Request $request): Response
    {
        $classes = ClassGroup::query()
            ->whereHas('academicYear', fn ($query) => $query->where('status', AcademicYearStatus::Active->value))
            ->with(['grade', 'major', 'room', 'homeroom', 'academicYear'])
            ->orderBy('name')
            ->get();

        $selected = $classes->firstWhere('id', $request->integer('kelas')) ?? $classes->first();

        $assignments = $selected === null
            ? collect()
            : $selected->teachingAssignments()->with(['subject', 'teacher', 'classGroup'])->get();

        return $this->renderMaster('Core/Academic/Assignments/Index', [
            'classes' => ClassGroupResource::collection($classes)->resolve(),
            'classId' => $selected?->id,
            'subjects' => SubjectResource::rows(Subject::query()->orderBy('name')->get(), $this->gradeRange()),
            'teachers' => TeacherResource::collection(Teacher::query()->orderBy('name')->orderBy('id')->get())->resolve(),
            'assignments' => TeachingAssignmentResource::collection($assignments)->resolve(),
        ]);
    }

    public function update(TeachingAssignmentRequest $request, ClassGroup $classGroup, SaveClassAssignments $save): RedirectResponse
    {
        $save->handle($classGroup, $request->rows());

        return back()->with('status', "Pengampu kelas {$classGroup->name} disimpan.");
    }

    private function gradeRange(): string
    {
        $names = Grade::query()->orderBy('sort_order')->pluck('name');

        return match (true) {
            $names->isEmpty() => '—',
            $names->count() === 1 => "Kelas {$names->first()}",
            default => "Kelas {$names->first()}–{$names->last()}",
        };
    }
}
