<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\App\Http\Controllers\BerandaController;
use Modules\Core\App\Http\Controllers\MasterDataController;

// Module routes are registered via loadRoutesFrom() and do NOT inherit
// the root web group automatically — always declare the group here.
Route::middleware('web')->group(function (): void {
    // The school's landing record. Named "home" so the auth redirect
    // (redirectUsersTo) lands here after login. auth only — core is
    // always-active, so there is no module flag to check.
    Route::middleware('auth')->group(function (): void {
        Route::get('beranda', BerandaController::class)->name('home');

        // Master data = base records that must exist (mockup phase: static
        // sample data, nothing persisted).
        Route::prefix('master')->name('core.master.')->controller(MasterDataController::class)->group(function (): void {
            Route::get('sekolah', 'school')->name('school');
            Route::get('tahun-ajaran', 'academicYears')->name('years');
            Route::get('semester', 'semesters')->name('semesters');
            Route::get('tingkat-jurusan', 'grades')->name('grades');
            Route::get('kelas', 'classes')->name('classes');
            Route::get('kelas/{id}', 'classShow')->whereNumber('id')->name('classes.show');
            Route::get('mata-pelajaran', 'subjects')->name('subjects');
            Route::get('guru', 'teachers')->name('teachers');
            Route::get('guru/{id}', 'teacherShow')->whereNumber('id')->name('teachers.show');
            Route::get('siswa', 'students')->name('students');
            Route::get('siswa/{id}', 'studentShow')->whereNumber('id')->name('students.show');
            Route::get('ruangan', 'rooms')->name('rooms');
            Route::get('ekstrakurikuler', 'extracurriculars')->name('extracurriculars');
            Route::get('ekstrakurikuler/{id}', 'extracurricularShow')->whereNumber('id')->name('extracurriculars.show');
        });

        // Academic management: actions and schedules that work on the base
        // records (mockup phase).
        Route::prefix('akademik')->name('core.academic.')->controller(MasterDataController::class)->group(function (): void {
            Route::get('penempatan', 'placement')->name('placement');
            Route::get('pengampu', 'assignments')->name('assignments');
            Route::get('wali-kelas', 'homerooms')->name('homerooms');
            Route::get('jam-pelajaran', 'periods')->name('periods');
            Route::get('kalender', 'calendar')->name('calendar');
        });

        // Bulk import of base records (mockup phase).
        Route::get('kelola/impor', [MasterDataController::class, 'importData'])->name('core.manage.import');

        // Integrasi with outside services (mockup phase).
        Route::get('integrasi/whatsapp', [MasterDataController::class, 'whatsapp'])->name('core.integration.whatsapp');

        // Statistik and Laporan: school-wide figures and downloadable
        // reports (mockup phase).
        Route::prefix('statistik-laporan')->name('core.insight.')->controller(MasterDataController::class)->group(function (): void {
            Route::get('statistik', 'statistics')->name('statistics');
            Route::get('laporan', 'reports')->name('reports');
        });
    });
});
