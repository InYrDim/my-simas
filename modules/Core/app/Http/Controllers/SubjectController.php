<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Modules\Core\App\Domain\Actions\DeleteSubject;
use Modules\Core\App\Domain\Actions\SaveSubject;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Http\Requests\SubjectRequest;
use Modules\Core\App\Http\Resources\SubjectResource;

final class SubjectController
{
    use RendersMasterPage;

    public function index(): Response
    {
        $names = Grade::query()->orderBy('sort_order')->pluck('name');

        $range = match (true) {
            $names->isEmpty() => '—',
            $names->count() === 1 => "Kelas {$names->first()}",
            default => "Kelas {$names->first()}–{$names->last()}",
        };

        return $this->renderMaster('Core/Master/Subjects/Index', [
            'subjects' => SubjectResource::rows(Subject::query()->orderBy('name')->get(), $range),
        ]);
    }

    public function store(SubjectRequest $request, SaveSubject $save): RedirectResponse
    {
        $subject = $save->handle(null, $request->subjectData());

        return back()->with('status', "Mata pelajaran {$subject->name} ditambahkan.");
    }

    public function update(SubjectRequest $request, Subject $subject, SaveSubject $save): RedirectResponse
    {
        $save->handle($subject, $request->subjectData());

        return back()->with('status', "Mata pelajaran {$subject->name} diperbarui.");
    }

    public function destroy(Subject $subject, DeleteSubject $delete): RedirectResponse
    {
        $delete->handle($subject);

        return back()->with('status', "Mata pelajaran {$subject->name} dihapus.");
    }
}
