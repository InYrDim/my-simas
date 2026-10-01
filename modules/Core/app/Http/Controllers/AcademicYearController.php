<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\ActivateAcademicYear;
use Modules\Core\App\Domain\Actions\CreateAcademicYear;
use Modules\Core\App\Domain\Actions\DeleteAcademicYear;
use Modules\Core\App\Domain\Actions\SuggestAcademicYear;
use Modules\Core\App\Domain\Actions\UpdateAcademicYear;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\AcademicYearRequest;
use Modules\Core\App\Http\Resources\AcademicYearResource;
use Modules\Platform\App\Contracts\TenantContext;

final class AcademicYearController
{
    use RendersMasterPage;

    public function index(SuggestAcademicYear $suggest, TenantContext $context): Response
    {
        $years = AcademicYear::query()->with('semesters')->orderByDesc('start_date')->get();

        return $this->renderMaster('Core/Master/AcademicYears/Index', [
            'years' => AcademicYearResource::collection($years)->resolve(),
            'suggestion' => $suggest->handle(now($context->currentOrFail()->timezone)),
        ]);
    }

    public function store(AcademicYearRequest $request, CreateAcademicYear $create): RedirectResponse
    {
        $year = $create->handle($request->validated());

        return back()->with('status', "Tahun ajaran {$year->name} ditambahkan.");
    }

    public function update(AcademicYearRequest $request, AcademicYear $year, UpdateAcademicYear $update): RedirectResponse
    {
        $update->handle($year, $request->validated());

        return back()->with('status', "Tahun ajaran {$year->name} diperbarui.");
    }

    public function activate(AcademicYear $year, ActivateAcademicYear $activate): RedirectResponse
    {
        $activate->handle($year);

        return back()->with('status', "Tahun ajaran {$year->name} kini aktif.");
    }

    public function destroy(AcademicYear $year, DeleteAcademicYear $delete): RedirectResponse
    {
        $delete->handle($year);

        return back()->with('status', "Tahun ajaran {$year->name} dihapus.");
    }
}
