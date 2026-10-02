<?php

use Illuminate\Support\Facades\DB;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;

require_once __DIR__.'/Support/school.php';

/*
 * Akun siswa dan guru (Fase 8) in a real browser: the dialogs an admin
 * goes through on the class, student and teacher pages. Who is skipped,
 * permissions and tenant isolation are covered by the Core feature tests;
 * the student's first login runs on the real server (tests/E2E).
 */

it('makes accounts for a class and shows them on the class and student pages', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    [$class, $andi] = inTenant($tenant, function (): array {
        $year = AcademicYear::factory()->active()->create();
        $class = ClassGroup::factory()->create(['academic_year_id' => $year->id, 'name' => 'X 1']);

        $andi = Student::factory()->create(['name' => 'Andi Wijaya', 'nis' => '71001', 'birth_date' => '2010-03-04', 'class_id' => $class->id]);
        Student::factory()->create(['name' => 'Budi Hartono', 'nis' => '71002', 'birth_date' => null, 'class_id' => $class->id]);

        return [$class, $andi];
    });

    $page->navigate("/master/kelas/{$class->id}")
        ->assertSee('Belum punya akun')
        ->press('Buatkan akun siswa')
        ->assertSee('2 siswa belum punya akun')
        ->click('internal:role=button[name="Buatkan akun"s]')
        ->assertSee('1 akun dibuat. Dilewati: Budi Hartono (tanpa tanggal lahir).')
        ->assertSee('Punya akun')
        ->assertNoJavaScriptErrors();

    $account = DB::table('users')->where('id', $andi->refresh()->user_id)->first();

    expect($account)->not->toBeNull()
        ->and($account->username)->toBe('71001')
        ->and((bool) $account->must_change_password)->toBeTrue();

    $page->navigate("/master/siswa/{$andi->id}")
        ->assertSee('Nama pengguna')
        ->assertSee('Masih kata sandi awal')
        ->press('Reset kata sandi')
        ->click('internal:role=button[name="Reset kata sandi"s] >> nth=-1')
        ->assertSee('dikembalikan ke tanggal lahir')
        ->assertNoJavaScriptErrors();

    // The user list sits behind the identity module flag. Enabled inside
    // the school's context: the flag cache is partitioned by the ambient
    // tenant, and the earlier requests already cached "off" there.
    inTenant($tenant, fn () => app(ModuleFlagManager::class)->enable($tenant->id, 'identity'));

    $page->navigate('/users?role=siswa')
        ->assertSee('Andi Wijaya')
        ->assertSee('71001')
        ->assertNoJavaScriptErrors();
});

it('makes a teacher account and shows its password once', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    $teacher = inTenant($tenant, fn (): Teacher => Teacher::factory()->create([
        'name' => 'Rina Marlina', 'nip' => '198705012010012001', 'email' => 'rina@sekolah-uji.test',
    ]));

    $page->navigate("/master/guru/{$teacher->id}")
        ->assertSee('Belum punya akun')
        ->press('Buat akun')
        ->assertSee('Kata sandi sementara tampil sekali')
        ->click('internal:role=button[name="Buat akun"s] >> nth=-1')
        ->assertSee('Kata sandi sementara')
        ->assertSee('198705012010012001')
        ->assertNoJavaScriptErrors();

    $account = DB::table('users')->where('id', $teacher->refresh()->user_id)->first();

    expect($account)->not->toBeNull()
        ->and($account->username)->toBe('198705012010012001')
        ->and($account->email)->toBe('rina@sekolah-uji.test');

    // Loading the page again no longer shows the password.
    $page->navigate("/master/guru/{$teacher->id}")
        ->assertSee('Masih kata sandi awal')
        ->assertDontSee('Catat sekarang')
        ->assertNoJavaScriptErrors();
});
