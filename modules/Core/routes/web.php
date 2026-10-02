<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\App\Http\Controllers\AcademicYearController;
use Modules\Core\App\Http\Controllers\BerandaController;
use Modules\Core\App\Http\Controllers\CalendarEventController;
use Modules\Core\App\Http\Controllers\ClassGroupController;
use Modules\Core\App\Http\Controllers\ExtracurricularController;
use Modules\Core\App\Http\Controllers\ExtracurricularMemberController;
use Modules\Core\App\Http\Controllers\GradeController;
use Modules\Core\App\Http\Controllers\HomeroomController;
use Modules\Core\App\Http\Controllers\ImportController;
use Modules\Core\App\Http\Controllers\MajorController;
use Modules\Core\App\Http\Controllers\MasterDataController;
use Modules\Core\App\Http\Controllers\PeriodSlotController;
use Modules\Core\App\Http\Controllers\PlacementController;
use Modules\Core\App\Http\Controllers\ReportController;
use Modules\Core\App\Http\Controllers\RoomController;
use Modules\Core\App\Http\Controllers\SchoolProfileController;
use Modules\Core\App\Http\Controllers\SemesterController;
use Modules\Core\App\Http\Controllers\StatisticsController;
use Modules\Core\App\Http\Controllers\StudentController;
use Modules\Core\App\Http\Controllers\SubjectController;
use Modules\Core\App\Http\Controllers\TeacherController;
use Modules\Core\App\Http\Controllers\TeachingAssignmentController;

// Module routes are registered via loadRoutesFrom() and do NOT inherit
// the root web group automatically — always declare the group here.
Route::middleware('web')->group(function (): void {
    // The school's landing record. Named "home" so the auth redirect
    // (redirectUsersTo) lands here after login. auth only — core is
    // always-active, so there is no module flag to check.
    Route::middleware('auth')->group(function (): void {
        Route::get('beranda', BerandaController::class)->name('home');

        // Master data = base records that must exist. Reads need
        // core.master.view, writes core.master.manage. Pages not yet backed
        // by the database (see docs/ai/plan/fase-3) still read mock data.
        Route::prefix('master')->name('core.master.')->middleware('can:core.master.view')->group(function (): void {
            Route::get('sekolah', [SchoolProfileController::class, 'show'])->name('school');
            Route::get('tahun-ajaran', [AcademicYearController::class, 'index'])->name('years');
            Route::get('semester', [SemesterController::class, 'index'])->name('semesters');
            Route::get('tingkat-jurusan', [GradeController::class, 'index'])->name('grades');
            Route::get('kelas', [ClassGroupController::class, 'index'])->name('classes');
            Route::get('kelas/{classGroup}', [ClassGroupController::class, 'show'])->whereNumber('classGroup')->name('classes.show');
            Route::get('mata-pelajaran', [SubjectController::class, 'index'])->name('subjects');
            Route::get('ruangan', [RoomController::class, 'index'])->name('rooms');

            Route::get('guru', [TeacherController::class, 'index'])->name('teachers');
            Route::get('guru/{teacher}', [TeacherController::class, 'show'])->whereNumber('teacher')->name('teachers.show');
            Route::get('siswa', [StudentController::class, 'index'])->name('students');
            Route::get('siswa/{student}', [StudentController::class, 'show'])->whereNumber('student')->name('students.show');
            Route::get('ekstrakurikuler', [ExtracurricularController::class, 'index'])->name('extracurriculars');
            Route::get('ekstrakurikuler/{extracurricular}', [ExtracurricularController::class, 'show'])->whereNumber('extracurricular')->name('extracurriculars.show');

            Route::middleware('can:core.master.manage')->group(function (): void {
                Route::put('sekolah', [SchoolProfileController::class, 'update'])->name('school.update');

                Route::post('tahun-ajaran', [AcademicYearController::class, 'store'])->name('years.store');
                Route::put('tahun-ajaran/{year}', [AcademicYearController::class, 'update'])->whereNumber('year')->name('years.update');
                Route::post('tahun-ajaran/{year}/aktifkan', [AcademicYearController::class, 'activate'])->whereNumber('year')->name('years.activate');
                Route::delete('tahun-ajaran/{year}', [AcademicYearController::class, 'destroy'])->whereNumber('year')->name('years.destroy');

                Route::put('semester/{semester}', [SemesterController::class, 'update'])->whereNumber('semester')->name('semesters.update');

                Route::post('jurusan', [MajorController::class, 'store'])->name('majors.store');
                Route::put('jurusan/{major}', [MajorController::class, 'update'])->whereNumber('major')->name('majors.update');
                Route::delete('jurusan/{major}', [MajorController::class, 'destroy'])->whereNumber('major')->name('majors.destroy');

                Route::post('kelas', [ClassGroupController::class, 'store'])->name('classes.store');
                Route::put('kelas/{classGroup}', [ClassGroupController::class, 'update'])->whereNumber('classGroup')->name('classes.update');
                Route::delete('kelas/{classGroup}', [ClassGroupController::class, 'destroy'])->whereNumber('classGroup')->name('classes.destroy');

                Route::post('mata-pelajaran', [SubjectController::class, 'store'])->name('subjects.store');
                Route::put('mata-pelajaran/{subject}', [SubjectController::class, 'update'])->whereNumber('subject')->name('subjects.update');
                Route::delete('mata-pelajaran/{subject}', [SubjectController::class, 'destroy'])->whereNumber('subject')->name('subjects.destroy');

                Route::post('guru', [TeacherController::class, 'store'])->name('teachers.store');
                Route::put('guru/{teacher}', [TeacherController::class, 'update'])->whereNumber('teacher')->name('teachers.update');
                Route::delete('guru/{teacher}', [TeacherController::class, 'destroy'])->whereNumber('teacher')->name('teachers.destroy');

                Route::post('siswa', [StudentController::class, 'store'])->name('students.store');
                Route::put('siswa/{student}', [StudentController::class, 'update'])->whereNumber('student')->name('students.update');
                Route::delete('siswa/{student}', [StudentController::class, 'destroy'])->whereNumber('student')->name('students.destroy');

                Route::post('ekstrakurikuler', [ExtracurricularController::class, 'store'])->name('extracurriculars.store');
                Route::put('ekstrakurikuler/{extracurricular}', [ExtracurricularController::class, 'update'])->whereNumber('extracurricular')->name('extracurriculars.update');
                Route::delete('ekstrakurikuler/{extracurricular}', [ExtracurricularController::class, 'destroy'])->whereNumber('extracurricular')->name('extracurriculars.destroy');
                Route::post('ekstrakurikuler/{extracurricular}/anggota', [ExtracurricularMemberController::class, 'store'])->whereNumber('extracurricular')->name('extracurriculars.members.store');
                Route::delete('ekstrakurikuler/{extracurricular}/anggota/{student}', [ExtracurricularMemberController::class, 'destroy'])->whereNumber(['extracurricular', 'student'])->name('extracurriculars.members.destroy');

                Route::post('ruangan', [RoomController::class, 'store'])->name('rooms.store');
                Route::put('ruangan/{room}', [RoomController::class, 'update'])->whereNumber('room')->name('rooms.update');
                Route::delete('ruangan/{room}', [RoomController::class, 'destroy'])->whereNumber('room')->name('rooms.destroy');
            });
        });

        // Academic management: actions and schedules that work on the base
        // records. Reads need core.academic.view, writes
        // core.academic.manage.
        Route::prefix('akademik')->name('core.academic.')->middleware('can:core.academic.view')->group(function (): void {
            Route::get('penempatan', [PlacementController::class, 'index'])->name('placement');
            Route::get('pengampu', [TeachingAssignmentController::class, 'index'])->name('assignments');
            Route::get('wali-kelas', [HomeroomController::class, 'index'])->name('homerooms');
            Route::get('jam-pelajaran', [PeriodSlotController::class, 'index'])->name('periods');
            Route::get('kalender', [CalendarEventController::class, 'index'])->name('calendar');

            Route::middleware('can:core.academic.manage')->group(function (): void {
                Route::post('penempatan', [PlacementController::class, 'store'])->name('placement.store');

                Route::post('kalender', [CalendarEventController::class, 'store'])->name('calendar.store');
                Route::put('kalender/{calendarEvent}', [CalendarEventController::class, 'update'])->whereNumber('calendarEvent')->name('calendar.update');
                Route::delete('kalender/{calendarEvent}', [CalendarEventController::class, 'destroy'])->whereNumber('calendarEvent')->name('calendar.destroy');

                Route::post('jam-pelajaran', [PeriodSlotController::class, 'store'])->name('periods.store');
                Route::post('jam-pelajaran/salin', [PeriodSlotController::class, 'copy'])->name('periods.copy');
                Route::put('jam-pelajaran/{periodSlot}', [PeriodSlotController::class, 'update'])->whereNumber('periodSlot')->name('periods.update');
                Route::delete('jam-pelajaran/{periodSlot}', [PeriodSlotController::class, 'destroy'])->whereNumber('periodSlot')->name('periods.destroy');

                Route::put('wali-kelas', [HomeroomController::class, 'update'])->name('homerooms.update');
                Route::put('pengampu/{classGroup}', [TeachingAssignmentController::class, 'update'])->whereNumber('classGroup')->name('assignments.update');
            });
        });

        // Bulk import of students and teachers from CSV. It writes master
        // data, so the page and its endpoints need core.master.manage.
        Route::prefix('kelola')->name('core.manage.')->middleware('can:core.master.manage')->group(function (): void {
            Route::get('impor', [ImportController::class, 'index'])->name('import');
            Route::get('impor/templat/{target}', [ImportController::class, 'template'])->name('import.template');
            Route::post('impor/pratinjau', [ImportController::class, 'preview'])->name('import.preview');
            Route::post('impor', [ImportController::class, 'store'])->name('import.store');
        });

        // Integrasi with outside services (mockup phase).
        Route::get('integrasi/whatsapp', [MasterDataController::class, 'whatsapp'])->name('core.integration.whatsapp');

        // Statistik and Laporan: school-wide figures and downloadable
        // reports. The reports hold student records, so the pages need
        // core.master.view; a report may ask for a permission of its own.
        Route::prefix('statistik-laporan')->name('core.insight.')->middleware('can:core.master.view')->group(function (): void {
            Route::get('statistik', StatisticsController::class)->name('statistics');
            Route::get('laporan', [ReportController::class, 'index'])->name('reports');
            Route::get('laporan/{report}/unduh', [ReportController::class, 'download'])->name('reports.download');
            Route::get('laporan/{report}/cetak', [ReportController::class, 'printView'])->name('reports.print');
        });
    });
});
