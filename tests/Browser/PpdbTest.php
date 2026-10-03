<?php

use Illuminate\Support\Facades\Mail;
use Modules\Core\App\Domain\Models\Student;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Infrastructure\Mail\AccountVerificationMail;
use Modules\Ppdb\Database\Factories\PpdbAccountFactory;

require_once __DIR__.'/Support/school.php';
require_once __DIR__.'/Support/onboarding.php';

/*
 * PPDB (Fase 11) in a real browser: an applicant makes an account, joins a
 * school with its code, sends the form, and the committee verifies, scores,
 * decides, announces and records the re-registration — the applicant sees
 * the result and the school has a new student. The rules behind each step
 * (quota, one registration per account, results held back, tenant
 * isolation, ...) are covered by the Ppdb feature tests; the same journey
 * on the real server and two real browser sessions by
 * tests/E2E/ppdb-account.spec.ts.
 */

/**
 * A school with PPDB on, a running period with a wave open today and a
 * Zonasi path of two seats, and a school admin.
 *
 * @return array{0: Tenant, 1: string} the school and the admin's email
 */
function ppdbSchoolWithAdmin(): array
{
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-ppdb', 'name' => 'SMA Contoh PPDB']);
    $email = 'admin@sekolah-ppdb.test';
    $admin = UserFactory::new()->forTenant($tenant->id)->create(['email' => $email]);

    inTenant($tenant, function () use ($tenant, $admin): void {
        // Before the first page: the module flag is cached per school.
        app(ModuleFlagManager::class)->enable($tenant->id, 'ppdb');
        $admin->assignTenantRole('admin-sekolah');

        $period = AdmissionPeriod::factory()->active()->create(['name' => 'PPDB Uji', 'entry_year' => now()->year + 1]);

        AdmissionWave::factory()->create([
            'period_id' => $period->id,
            'name' => 'Gelombang Uji',
            'opens_on' => now($tenant->timezone)->subDay()->toDateString(),
            'closes_on' => now($tenant->timezone)->addDays(30)->toDateString(),
        ]);
        AdmissionPath::factory()->create(['period_id' => $period->id, 'name' => 'Zonasi', 'quota' => 2]);
    });

    return [$tenant, $email];
}

it('takes an applicant from a school link to the announced result and a place as a student', function () {
    Mail::fake();
    [$tenant, $adminEmail] = ppdbSchoolWithAdmin();
    onCentralHost();

    // --- The applicant makes an account and verifies the email
    $applicant = visit('/calon-siswa/daftar');

    $applicant->fill('name', 'Siti Aminah')
        ->fill('email', 'siti@contoh.test')
        ->fill('password', 'rahasia-sekali')
        ->fill('password_confirmation', 'rahasia-sekali')
        ->click('internal:role=button[name="Buat akun"i]')
        ->assertPathIs('/calon-siswa/verifikasi')
        ->assertSee('Verifikasi email Anda');

    $account = PpdbAccount::query()->sole();
    expect($account->hasVerifiedEmail())->toBeFalse()->and($account->tenant_id)->toBeNull();

    $applicant->navigate(mailedPath(AccountVerificationMail::class, 'verifyUrl'));
    expect($account->fresh()->hasVerifiedEmail())->toBeTrue();
    refreshSignedInUsers();

    // --- They join the school with the code from the school's link
    $applicant->navigate('/calon-siswa/gabung?school='.$tenant->id)
        ->assertSee('Gabung ke sekolah')
        ->press('Gabung')
        ->assertPathIs('/calon-siswa')
        ->assertSee('SMA Contoh PPDB')
        ->assertSee('Isi formulir pendaftaran');

    expect($account->fresh()->tenant_id)->toBe($tenant->id);

    // --- They send the registration form
    $applicant->click('Isi formulir pendaftaran')
        ->assertPathIs('/calon-siswa/formulir')
        ->fill('#applicant-name', 'Nadia Putri Anggraini')
        ->click('internal:role=combobox[name="Jalur"i]')
        ->click('internal:role=option[name="Zonasi"i]')
        ->click('internal:role=combobox[name="Jenis kelamin"i]')
        ->click('internal:role=option[name="Perempuan"i]')
        ->fill('#applicant-nisn', '0071234567')
        ->fill('#applicant-birth-date', '2012-05-04')
        ->fill('#applicant-origin', 'SMPN 3 Bandung')
        ->fill('#applicant-guardian', 'Budi Santoso')
        ->fill('#applicant-phone', '081234567890')
        ->press('Kirim pendaftaran')
        ->assertPathIs('/calon-siswa')
        ->assertSee('Pendaftaran terkirim')
        ->assertSee('Menunggu verifikasi')
        ->assertSee('Hasil seleksi belum diumumkan')
        ->assertNoJavaScriptErrors();

    $registration = inTenant($tenant, fn () => Applicant::query()->sole());
    $number = 'PPDB-'.(now()->year + 1) % 100 .'-0001';

    expect($registration->account_id)->toBe($account->id)
        ->and($registration->number)->toBe($number)
        ->and($registration->status)->toBe(ApplicantStatus::Submitted);

    // --- The committee verifies, scores, decides and announces
    refreshSignedInUsers();
    $staff = visit('/login');

    $staff->fill('school', $tenant->id)
        ->fill('login', $adminEmail)
        ->fill('password', 'password')
        ->press('Masuk')
        ->assertPathIs('/beranda');

    $staff->navigate('/ppdb/pengaturan')
        ->assertSee('Kode dan tautan sekolah')
        ->assertSee('Jalur dan kuota')
        ->assertSee('Gelombang Uji')
        ->assertNoJavaScriptErrors();

    $staff->navigate('/ppdb/pendaftar')
        ->assertSee('Nadia Putri Anggraini')
        ->assertSee($number)
        ->click('Nadia Putri Anggraini')
        ->click('internal:role=combobox[name="Status verifikasi"i]')
        ->click('internal:role=option[name="Terverifikasi"i]')
        ->press('Simpan verifikasi')
        ->assertSee('Status verifikasi disimpan.')
        ->assertNoJavaScriptErrors();

    $staff->navigate('/ppdb/seleksi')
        ->assertSee('Nadia Putri Anggraini')
        ->fill('input[aria-label="Nilai Nadia Putri Anggraini"]', '88.4')
        ->click('internal:role=button[name="Terima"i]')
        ->press('Simpan seleksi')
        ->assertSee('Seleksi jalur Zonasi disimpan.')
        ->press('Umumkan hasil')
        ->click('internal:role=alertdialog >> internal:role=button[name="Umumkan hasil"i]')
        ->assertSee('Hasil seleksi diumumkan.')
        ->assertNoJavaScriptErrors();

    $decided = inTenant($tenant, fn () => Applicant::query()->sole());

    expect($decided->status)->toBe(ApplicantStatus::Verified)
        ->and($decided->decision)->toBe(Decision::Accepted)
        ->and((float) $decided->score)->toBe(88.4)
        ->and(inTenant($tenant, fn () => AdmissionPeriod::query()->sole()->results_published_at))->not->toBeNull();

    // --- The applicant sees the announced result
    refreshSignedInUsers();
    $applicant->navigate('/calon-siswa')
        ->assertSee('Diterima')
        ->assertDontSee('Hasil seleksi belum diumumkan')
        ->assertNoJavaScriptErrors();

    // --- The committee records the re-registration: a student is made
    refreshSignedInUsers();
    $staff->navigate('/ppdb/pendaftar')
        ->click('Nadia Putri Anggraini')
        ->fill('#enroll-nis', '20270001')
        ->press('Catat daftar ulang')
        ->assertSee('tercatat daftar ulang')
        ->assertNoJavaScriptErrors();

    $student = inTenant($tenant, fn () => Student::query()->where('nis', '20270001')->sole());

    expect($student->name)->toBe('Nadia Putri Anggraini')
        ->and($student->class_id)->toBeNull()
        ->and(inTenant($tenant, fn () => Applicant::query()->sole()->student_id))->toBe($student->id);

    $staff->navigate('/master/siswa')
        ->assertSee('Nadia Putri Anggraini')
        ->assertSee('20270001')
        ->assertNoJavaScriptErrors();

    refreshSignedInUsers();
    $applicant->navigate('/calon-siswa')
        ->assertSee('Anda sudah melakukan daftar ulang.')
        ->assertNoJavaScriptErrors();
});

it('builds the form the applicant fills in: archived field gone, new question added, shown in the preview', function () {
    [$tenant, $adminEmail] = ppdbSchoolWithAdmin();
    PpdbAccountFactory::new()->joined($tenant->id)->create(['email' => 'siti@contoh.test']);
    onCentralHost();

    // --- The admin archives NISN, makes the origin school optional and adds a question
    $staff = visit('/login');

    $staff->fill('school', $tenant->id)
        ->fill('login', $adminEmail)
        ->fill('password', 'password')
        ->press('Masuk')
        ->assertPathIs('/beranda');

    $staff->navigate('/ppdb/formulir')
        ->assertSee('Kolom formulir')
        ->assertSee('Pratinjau')
        ->click('internal:role=button[name="Arsipkan NISN"i]')
        ->click('internal:role=button[name="Ubah Asal sekolah"i]')
        ->click('internal:role=switch[name="Wajib diisi"i]')
        ->press('Tambah kolom')
        ->click('internal:role=menuitem[name="Pilihan tunggal"i]')
        ->fill('internal:label="Label pertanyaan"s', 'Program')
        ->assertSee('Program')
        ->press('Simpan formulir')
        ->assertSee('Formulir pendaftaran disimpan.')
        ->assertNoJavaScriptErrors();

    // --- The applicant's form follows: no NISN, an optional origin school and the new question
    $applicant = visit('/calon-siswa/masuk');

    $applicant->fill('email', 'siti@contoh.test')
        ->fill('password', 'password')
        ->press('Masuk')
        ->assertPathIs('/calon-siswa');

    $applicant->navigate('/calon-siswa/formulir')
        ->assertDontSee('NISN')
        ->assertSee('Asal sekolah')
        ->assertSee('Program')
        ->fill('#applicant-name', 'Nadia Putri Anggraini')
        ->click('internal:role=combobox[name="Jalur"i]')
        ->click('internal:role=option[name="Zonasi"i]')
        ->click('internal:role=combobox[name="Jenis kelamin"i]')
        ->click('internal:role=option[name="Perempuan"i]')
        ->click('internal:role=combobox[name="Program"i]')
        ->click('internal:role=option[name="Opsi 2"i]')
        ->fill('#applicant-birth-date', '2012-05-04')
        ->fill('#applicant-guardian', 'Budi Santoso')
        ->fill('#applicant-phone', '081234567890')
        ->press('Kirim pendaftaran')
        ->assertPathIs('/calon-siswa')
        ->assertSee('Pendaftaran terkirim')
        ->assertNoJavaScriptErrors();

    $registration = inTenant($tenant, fn () => Applicant::query()->sole());
    $answer = inTenant($tenant, fn () => ApplicantAnswer::query()->sole());

    expect($registration->nisn)->toBeNull()
        ->and($registration->origin_school)->toBeNull()
        ->and($registration->name)->toBe('Nadia Putri Anggraini')
        ->and($answer->value)->toBe('Opsi 2');

    // --- The committee sees the answer on the applicant's page
    $staff->navigate('/ppdb/pendaftar')
        ->click('Nadia Putri Anggraini')
        ->assertSee('Program')
        ->assertSee('Opsi 2')
        ->assertNoJavaScriptErrors();
});

it('refuses a code that is not a school with PPDB, in the words every refusal uses', function () {
    Mail::fake();
    ppdbSchoolWithAdmin();
    onCentralHost();

    $applicant = visit('/calon-siswa/daftar');

    $applicant->fill('name', 'Siti Aminah')
        ->fill('email', 'siti@contoh.test')
        ->fill('password', 'rahasia-sekali')
        ->fill('password_confirmation', 'rahasia-sekali')
        ->click('internal:role=button[name="Buat akun"i]')
        ->assertPathIs('/calon-siswa/verifikasi');

    $applicant->navigate(mailedPath(AccountVerificationMail::class, 'verifyUrl'));
    refreshSignedInUsers();

    $applicant->navigate('/calon-siswa/gabung')
        ->fill('code', 'bukan-kode-sekolah')
        ->press('Gabung')
        ->assertSee('Kode sekolah tidak dikenali atau PPDB sekolah itu belum dibuka.')
        ->assertNoJavaScriptErrors();

    expect(PpdbAccount::query()->sole()->tenant_id)->toBeNull();
});

it('keeps the settings page a summary and changes it through dialogs', function () {
    [$tenant, $adminEmail] = ppdbSchoolWithAdmin();
    onCentralHost();

    $staff = visit('/login');

    $staff->fill('school', $tenant->id)
        ->fill('login', $adminEmail)
        ->fill('password', 'password')
        ->press('Masuk')
        ->assertPathIs('/beranda');

    // The page reads as text: no form is open until one is asked for.
    $staff->navigate('/ppdb/pengaturan')
        ->assertSee('PPDB Uji')
        ->assertSee('Gelombang Uji')
        ->assertSee('Zonasi')
        ->assertSee('Total 2 kursi.')
        ->assertDontSee('Nama periode')
        ->assertDontSee('Nama gelombang')
        ->assertNoJavaScriptErrors();

    // A wave is added in a dialog and lands in the table.
    $opens = now($tenant->timezone)->addDays(60)->toDateString();
    $closes = now($tenant->timezone)->addDays(90)->toDateString();

    $staff->press('Tambah gelombang')
        ->assertSee('Gelombang baru')
        ->fill('#wave-name', 'Gelombang Dua')
        ->fill('#wave-opens', $opens)
        ->fill('#wave-closes', $closes)
        ->press('Simpan gelombang')
        ->assertSee('Gelombang Dua')
        ->assertDontSee('Nama gelombang')
        ->assertNoJavaScriptErrors();

    // The seats are changed in a dialog and the total follows.
    $staff->press('Atur jalur dan kuota')
        ->fill('input[aria-label="Kuota jalur 1"]', '5')
        ->press('Simpan jalur dan kuota')
        ->assertSee('Total 5 kursi.')
        ->assertNoJavaScriptErrors();

    // The period is renamed in a dialog.
    $staff->press('Ubah periode')
        ->fill('#period-name-put', 'PPDB Revisi')
        ->press('Simpan periode')
        ->assertSee('PPDB Revisi')
        ->assertNoJavaScriptErrors();

    expect(inTenant($tenant, fn () => AdmissionWave::query()->where('name', 'Gelombang Dua')->exists()))->toBeTrue()
        ->and(inTenant($tenant, fn () => AdmissionPath::query()->sole()->quota))->toBe(5)
        ->and(inTenant($tenant, fn () => AdmissionPeriod::query()->sole()->name))->toBe('PPDB Revisi');
});
