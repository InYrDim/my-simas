<?php

require_once __DIR__.'/Support/school.php';

/*
 * The Beranda's blocks in a real browser: the page shell first, then the
 * deferred blocks of the active modules. What each role gets, and from
 * which permission, is covered by the Core, Attendance and Ppdb feature
 * tests (DashboardTest, AttendanceDashboardTest, AdmissionDashboardTest).
 */
it('fills the Beranda of a school administrator with its attendance blocks', function () {
    [$page] = schoolMemberSignsIn(modules: ['attendance']);

    $page
        ->assertSee('Kehadiran hari ini')
        ->assertSee('Kelas belum mengirim absensi')
        ->assertSee('Rekap hari ini')
        ->assertSee('Aksi cepat')
        ->assertSee('Undang staf')
        ->assertNoJavaScriptErrors();
});

it('shows a teacher the day on a phone without the office blocks', function () {
    [$page] = schoolMemberSignsIn('guru', ['attendance']);

    $page->resize(390, 844)
        ->navigate('/beranda')
        ->assertSee('Jadwal mengajar hari ini')
        ->assertSee('Tidak ada jam mengajar hari ini.')
        ->assertDontSee('Kelas belum mengirim absensi')
        ->assertNoJavaScriptErrors();
});
