<?php

namespace Modules\Core\App\Http\Controllers;

use Inertia\Response;
use Modules\Core\App\Domain\Actions\SyncDefaultGrades;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Major;
use Modules\Core\App\Domain\Models\SchoolProfile;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Resources\GradeResource;
use Modules\Core\App\Http\Resources\MajorResource;

/**
 * Tingkat & Jurusan: the grade levels follow the school's jenjang
 * (read-only here); majors are edited through MajorController.
 */
final class GradeController
{
    use RendersMasterPage;

    public function index(SyncDefaultGrades $syncGrades): Response
    {
        $syncGrades->handle(SchoolProfile::current());

        $activeYearIds = AcademicYear::query()
            ->where('status', AcademicYearStatus::Active->value)
            ->pluck('id');

        $grades = Grade::query()
            ->withCount(['classes' => fn ($query) => $query->whereIn('academic_year_id', $activeYearIds)])
            ->orderBy('sort_order')
            ->get();

        return $this->renderMaster('Core/Master/Grades/Index', [
            'grades' => GradeResource::collection($grades)->resolve(),
            'majors' => MajorResource::collection(Major::query()->orderBy('code')->get())->resolve(),
        ]);
    }
}
