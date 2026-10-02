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
 * Pengaturan Absensi: switches for the gate and the lesson attendance, and
 * the school's cut-off for arriving on time. Which
 * WhatsApp notices go out is set on Integrasi › WhatsApp, not here.
 */
final class SettingsController
{
    public function show(): Response
    {
        $settings = AttendanceSetting::current();

        return Inertia::render('Attendance/Settings', [
            'lateAfter' => $settings->lateAfter(),
            'gateEnabled' => $settings->gate_enabled,
            'lessonEnabled' => $settings->lesson_enabled,
            'can' => ['manageNotices' => Gate::allows('core.integration.manage')],
        ]);
    }

    public function update(Request $request, SaveAttendanceSettings $save): RedirectResponse
    {
        $validated = $request->validate(
            [
                'late_after' => ['required', 'date_format:H:i'],
                'gate_enabled' => ['sometimes', 'boolean'],
                'lesson_enabled' => ['sometimes', 'boolean'],
            ],
            ['required' => ':attribute wajib diisi.', 'date_format' => ':attribute harus berformat jj:mm.', 'boolean' => ':attribute harus berupa ya atau tidak.'],
            ['late_after' => 'Batas jam masuk', 'gate_enabled' => 'Absensi gerbang', 'lesson_enabled' => 'Absensi jam pelajaran'],
        );

        $current = AttendanceSetting::current();

        $save->handle(
            $validated['late_after'],
            $request->boolean('gate_enabled', $current->gate_enabled),
            $request->boolean('lesson_enabled', $current->lesson_enabled),
        );

        return back()->with('status', 'Pengaturan absensi disimpan.');
    }
}
