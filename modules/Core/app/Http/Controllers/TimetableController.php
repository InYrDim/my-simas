<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\SaveTimetableEntry;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Queries\ClassTimetable;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\TimetableEntryRequest;

/**
 * Jadwal Pelajaran: the weekly timetable of one class of the active
 * academic year. Reading needs `core.academic.view`, changing a lesson
 * `core.academic.manage`.
 */
final class TimetableController
{
    use RendersMasterPage;

    public function index(Request $request, ClassTimetable $timetable): Response
    {
        $classes = ClassGroup::query()
            ->whereHas('academicYear', fn ($query) => $query->where('status', AcademicYearStatus::Active->value))
            ->orderBy('name')
            ->get();

        $selected = $classes->firstWhere('id', $request->integer('kelas')) ?? $classes->first();

        $subjects = $selected === null
            ? collect()
            : TeachingAssignment::query()->with(['subject', 'teacher'])->where('class_id', $selected->id)->get()
                ->sortBy(fn (TeachingAssignment $assignment): string => $assignment->subject->name)
                ->values();

        return $this->renderMaster('Core/Academic/Timetable/Index', [
            'classes' => $classes->map(fn (ClassGroup $class): array => ['id' => $class->id, 'name' => $class->name])->values()->all(),
            'classId' => $selected?->id,
            'subjects' => $subjects->map(fn (TeachingAssignment $assignment): array => [
                'value' => (string) $assignment->subject_id,
                'label' => "{$assignment->subject->name} · {$assignment->teacher->name}",
            ])->all(),
            'days' => $selected === null ? [] : $timetable->forClass($selected->id),
        ]);
    }

    public function update(TimetableEntryRequest $request, SaveTimetableEntry $save): RedirectResponse
    {
        $class = ClassGroup::query()->findOrFail($request->classId());

        $save->handle($request->periodSlotId(), $class, $request->subjectId());

        return back()->with('status', 'Jadwal kelas '.$class->name.' disimpan.');
    }
}
