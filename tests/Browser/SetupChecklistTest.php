<?php

use Modules\Core\App\Domain\Actions\SaveSchoolProfile;

require_once __DIR__.'/Support/school.php';

/*
 * The setup checklist on the Beranda of a new school, in a real browser:
 * the card, the step it points to, and that it follows the school's data.
 * What each step needs and who gets the card are covered by the Core
 * feature tests (SetupChecklistTest, BerandaTest).
 */
it('shows a new school what to do next and takes the administrator there', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    $page->assertSee('Persiapan sekolah')
        ->assertSee('0 dari 7 selesai')
        ->assertSee('Simpan profil sekolah')
        ->assertSee('Berikutnya')
        ->assertSee('Menunggu: profil sekolah, tahun ajaran aktif, jurusan.')
        ->assertNoJavaScriptErrors();

    $page->click('Kerjakan')
        ->assertPathIs('/master/sekolah')
        ->assertNoJavaScriptErrors();
});

it('moves on to the next step once the school has saved its profile', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    inTenant($tenant, fn () => app(SaveSchoolProfile::class)->handle(['level' => 'sma']));

    $page->navigate('/beranda')
        ->assertSee('1 dari 7 selesai')
        ->assertSee('Buat dan aktifkan tahun ajaran')
        ->assertNoJavaScriptErrors();
});

it('does not show the checklist to a teacher', function () {
    [$page] = schoolMemberSignsIn('guru');

    $page->assertDontSee('Persiapan sekolah')
        ->assertNoJavaScriptErrors();
});
