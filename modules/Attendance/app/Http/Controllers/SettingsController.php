<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Actions\SaveAttendanceSettings;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;

/**
 * Pengaturan Absensi: the school's cut-off for arriving on time. Which
 * WhatsApp notices go out is set on Integrasi › WhatsApp, not here.
 */
final class SettingsController
{
    public function show(): Response
    {
        return Inertia::render('Attendance/Settings', [
            'lateAfter' => AttendanceSetting::current()->lateAfter(),
            'can' => ['manageNotices' => Gate::allows('core.integration.manage')],
        ]);
    }

    public function update(Request $request, SaveAttendanceSettings $save): RedirectResponse
    {
        $validated = $request->validate(
            ['late_after' => ['required', 'date_format:H:i']],
            ['required' => ':attribute wajib diisi.', 'date_format' => ':attribute harus berformat jj:mm.'],
            ['late_after' => 'Batas jam masuk'],
        );

        $save->handle($validated['late_after']);

        return back()->with('status', 'Pengaturan absensi disimpan.');
    }
}
