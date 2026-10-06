<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\App\Http\Controllers\ClassAttendanceController;
use Modules\Attendance\App\Http\Controllers\DailyInputController;
use Modules\Attendance\App\Http\Controllers\HistoryController;
use Modules\Attendance\App\Http\Controllers\LessonAttendanceController;
use Modules\Attendance\App\Http\Controllers\LessonCheckController;
use Modules\Attendance\App\Http\Controllers\MonthlyRecapController;
use Modules\Attendance\App\Http\Controllers\MyAttendanceController;
use Modules\Attendance\App\Http\Controllers\MyClassController;
use Modules\Attendance\App\Http\Controllers\MyClassesController;
use Modules\Attendance\App\Http\Controllers\MyClassStudentsController;
use Modules\Attendance\App\Http\Controllers\MyScheduleController;
use Modules\Attendance\App\Http\Controllers\OverviewController;
use Modules\Attendance\App\Http\Controllers\ScanController;
use Modules\Attendance\App\Http\Controllers\SettingsController;
use Modules\Attendance\App\Http\Controllers\StudentQrController;

// Module routes are registered via loadRoutesFrom() and do NOT inherit
// the root web group automatically — always declare the group here.
Route::middleware('web')->group(function (): void {
    Route::middleware(['auth', 'module:attendance'])
        ->prefix('absensi')
        ->name('attendance.')
        ->group(function (): void {
            Route::middleware('can:attendance.view')->group(function (): void {
                Route::get('/', OverviewController::class)->name('overview');
                Route::get('rekap', MonthlyRecapController::class)->name('monthly');
            });

            // Daily attendance is the office's; a teacher records per
            // lesson (Kelas Saya below).
            Route::middleware('can:attendance.daily.record')->group(function (): void {
                Route::get('input', [DailyInputController::class, 'index'])->name('input');
                Route::put('input', [DailyInputController::class, 'update'])->name('input.update');
                Route::delete('input/pulang', [DailyInputController::class, 'cancelCheckOut'])->name('input.cancel-check-out');
            });

            // The school-wide lesson page (any class) stays with the
            // school-wide recorders; a teacher uses Kelas Saya.
            Route::middleware('can:attendance.lesson.school')->group(function (): void {
                Route::get('jam-pelajaran', [LessonAttendanceController::class, 'index'])->name('lessons');
                Route::put('jam-pelajaran', [LessonAttendanceController::class, 'update'])->name('lessons.update');
            });

            // Kelas Saya: the teacher's own workspace. What they teach,
            // what today brings, the lesson open only in its own hour,
            // and the history where a record is corrected later.
            Route::middleware('can:attendance.class.record')->group(function (): void {
                Route::get('kelas-saya', MyClassesController::class)->name('my-classes');
                Route::get('kelas-saya/{classId}', MyClassStudentsController::class)
                    ->whereNumber('classId')
                    ->name('my-classes.students');
                Route::get('jadwal-saya', MyScheduleController::class)->name('schedule');
                Route::put('jadwal-saya/centang', [LessonCheckController::class, 'update'])->name('schedule.check');
            });

            Route::middleware('can:attendance.class.lesson.use')->group(function (): void {
                Route::get('absen-kelas', [ClassAttendanceController::class, 'index'])->name('class-roll');
                Route::put('absen-kelas', [ClassAttendanceController::class, 'update'])->name('class-roll.update');
                Route::get('riwayat', [HistoryController::class, 'index'])->name('history');
                Route::put('riwayat', [HistoryController::class, 'update'])->name('history.update');
            });

            // The scanner serves the gate and the lessons: either one, while
            // the school has it on, opens the page; the mode of a scan decides
            // which one it needs (ScanController, ScanRequest).
            Route::get('pindai', [ScanController::class, 'index'])->name('scan');
            Route::post('pindai', [ScanController::class, 'store'])->name('scan.store');
            Route::get('pindai/siswa', [ScanController::class, 'students'])->name('scan.students');

            Route::middleware('can:attendance.settings.manage')->group(function (): void {
                Route::get('pengaturan', [SettingsController::class, 'show'])->name('settings');
                Route::put('pengaturan', [SettingsController::class, 'update'])->name('settings.update');
            });

            // A student's own history; the controller finds the student
            // from the signed-in account, never from the URL.
            Route::get('saya', MyAttendanceController::class)
                ->middleware('can:attendance.mine.view')
                ->name('mine');

            // A student's own class: info, timetable, subjects with their
            // teachers. The controller finds the class from the account.
            Route::middleware('can:attendance.class.view-own')->prefix('kelasku')->group(function (): void {
                Route::get('/', [MyClassController::class, 'info'])->name('class-mine');
                Route::get('jadwal', [MyClassController::class, 'timetable'])->name('class-mine.timetable');
                Route::get('mapel', [MyClassController::class, 'subjects'])->name('class-mine.subjects');
            });

            // A student's own QR; the controller also asks for an account
            // linked to an active student.
            Route::middleware('can:attendance.qr.use')->group(function (): void {
                Route::get('qr-saya', [StudentQrController::class, 'show'])->name('my-qr');
                Route::post('qr-saya/token', [StudentQrController::class, 'token'])->name('my-qr.token');
            });
        });
});
