<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Core\App\Domain\Actions\AddExtracurricularMember;
use Modules\Core\App\Domain\Models\Extracurricular;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Http\Requests\ExtracurricularMemberRequest;

final class ExtracurricularMemberController
{
    public function store(
        ExtracurricularMemberRequest $request,
        Extracurricular $extracurricular,
        AddExtracurricularMember $add,
    ): RedirectResponse {
        $student = $add->handle($extracurricular, $request->validated('nis'));

        return back()->with('status', "{$student->name} ditambahkan ke {$extracurricular->name}.");
    }

    public function destroy(Extracurricular $extracurricular, Student $student): RedirectResponse
    {
        $extracurricular->memberships()->where('student_id', $student->id)->delete();

        return back()->with('status', "{$student->name} dikeluarkan dari {$extracurricular->name}.");
    }
}
