<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\PlaceStudents;
use Modules\Core\App\Domain\Enums\PlacementAction;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\PlacementRequest;
use Modules\Core\App\Http\Resources\AcademicYearResource;
use Modules\Core\App\Http\Resources\ClassGroupResource;
use Modules\Core\App\Http\Resources\StudentResource;

final class PlacementController
{
    use RendersMasterPage;

    /** `?kelas=belum`: the students who have no class yet, instead of one class. */
    private const UNPLACED = 'belum';

    public function index(Request $request): Response
    {
        $classes = ClassGroup::query()
            ->with(['grade', 'major', 'room', 'homeroom', 'academicYear'])
            ->withCount('students')
            ->orderBy('name')
            ->get();

        $unplacedQuery = fn () => Student::query()->where('status', 'active')->whereNull('class_id');
        $unplaced = $request->query('kelas') === self::UNPLACED;

        $source = $unplaced ? null : ($classes->firstWhere('id', $request->integer('kelas'))
            ?? $classes->first(fn (ClassGroup $class): bool => $class->academicYear->isActive())
            ?? $classes->first());

        $students = match (true) {
            $unplaced => $unplacedQuery()->with('classGroup')->orderBy('name')->orderBy('id')->get(),
            $source === null => collect(),
            default => $source->students()->where('status', 'active')->with('classGroup')->orderBy('name')->orderBy('id')->get(),
        };

        return $this->renderMaster('Core/Academic/Placement/Index', [
            'years' => AcademicYearResource::collection(
                AcademicYear::query()->with('semesters')->orderByDesc('start_date')->get(),
            )->resolve(),
            'classes' => ClassGroupResource::collection($classes)->resolve(),
            'sourceClassId' => $source?->id,
            'unplaced' => $unplaced,
            'unplacedCount' => $unplacedQuery()->count(),
            'students' => StudentResource::collection($students)->resolve(),
        ]);
    }

    public function store(PlacementRequest $request, PlaceStudents $place): RedirectResponse
    {
        $action = $request->placementAction();
        $target = $request->filled('target_class_id') ? ClassGroup::query()->findOrFail($request->integer('target_class_id')) : null;

        if ($action === PlacementAction::Assign) {
            $count = $place->assign($target ?? throw ValidationException::withMessages(['target_class_id' => 'Rombel tujuan wajib dipilih.']), $request->studentIds());

            return back()->with('status', "{$count} siswa ditempatkan ke {$target->name}.");
        }

        $source = ClassGroup::query()->findOrFail($request->integer('source_class_id'));

        $count = $place->handle($action, $source, $target, $request->studentIds());

        return back()->with('status', match ($action) {
            PlacementAction::Promote => "{$count} siswa dinaikkan ke {$target?->name}.",
            PlacementAction::Move => "{$count} siswa dipindahkan ke {$target?->name}.",
            default => "{$count} siswa diluluskan.",
        });
    }
}
