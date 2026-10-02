<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Ppdb\App\Domain\Enums\ApplicantSource;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\Database\Factories\PpdbAccountFactory;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * The applicant's own page and registration form: what it shows, sending
 * the form once, correcting it when the committee asks, and never touching
 * anything but this account's own registration in the one school it joined.
 */

const PORTAL_URL = 'http://localhost/calon-siswa';

beforeEach(function () {
    // 10 February 2027, 10:00 in Jakarta: inside the default wave (1 Jan – 31 Mar).
    $this->travelTo('2027-02-10 03:00:00');
});

it('tells an account that has not joined a school to join one', function () {
    ppdbAccount();

    get(PORTAL_URL)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Account/Home')
        ->where('portal.state', 'no_school')
    );
});

it('shows a joined account that the registration is open, with the dates', function () {
    [$tenant] = ppdbOpenSchool('portal-buka');
    ppdbAccount($tenant);

    get(PORTAL_URL)->assertInertia(fn (Assert $page) => $page
        ->where('portal.state', 'joined')
        ->where('portal.school', $tenant->name)
        ->where('portal.period', 'PPDB 2027/2028')
        ->where('portal.registration.open', true)
        ->where('portal.registration.waveName', 'Gelombang 1')
        ->where('portal.registration.closesOn', '31 Maret 2027')
    );
});

it('says when the next wave opens while none is open', function () {
    [$tenant, $period] = ppdbOpenSchool('portal-belum');
    ppdbWave($tenant, $period, ['name' => 'Gelombang 2', 'opens_on' => '2027-04-01', 'closes_on' => '2027-06-30']);
    ppdbAccount($tenant);
    $this->travelTo('2027-03-31 17:30:00'); // already 1 April in Jakarta, so wave 1 is closed, wave 2 open

    get(PORTAL_URL)->assertInertia(fn (Assert $page) => $page
        ->where('portal.registration.open', true)
        ->where('portal.registration.waveName', 'Gelombang 2')
    );

    $this->travelTo('2026-12-01 03:00:00');

    get(PORTAL_URL)->assertInertia(fn (Assert $page) => $page
        ->where('portal.registration.open', false)
        ->where('portal.registration.waveName', null)
        ->where('portal.registration.nextOpensOn', '1 Januari 2027')
    );
});

it('shows that the school is not taking applications when its period has closed', function () {
    [$tenant, $period] = ppdbOpenSchool('portal-tutup');
    ppdbAccount($tenant);
    ppdbSchool($tenant, fn () => $period->update(['status' => PeriodStatus::Closed]));

    get(PORTAL_URL)->assertInertia(fn (Assert $page) => $page
        ->where('portal.state', 'unavailable')
        ->where('portal.school', $tenant->name)
    );
});

it('offers the form with the school\'s paths, to a joined account while a wave is open', function () {
    [$tenant, $period] = ppdbOpenSchool('portal-form');
    ppdbPath($tenant, $period, ['name' => 'Prestasi', 'sort_order' => 1]);
    ppdbAccount($tenant, ['name' => 'Siti Aminah']);

    get(PORTAL_URL.'/formulir')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Account/Form')
        ->where('period', 'PPDB 2027/2028')
        ->where('paths', fn ($paths) => collect($paths)->pluck('label')->all() === ['Zonasi', 'Prestasi'])
        ->where('applicant', null)
        ->where('defaultName', 'Siti Aminah')
    );
});

it('sends an account that has not joined, or finds no open wave, away from the form', function () {
    ppdbAccount();

    get(PORTAL_URL.'/formulir')->assertRedirect(route('ppdb.account.join'));

    [$tenant] = ppdbOpenSchool('portal-form-tutup');
    ppdbAccount($tenant);
    $this->travelTo('2027-06-01 03:00:00');

    get(PORTAL_URL.'/formulir')->assertRedirect(route('ppdb.account.home'))->assertSessionHas('status');
});

it('registers the applicant once, in the open wave, as their own online registration', function () {
    [$tenant, , $wave, $path] = ppdbOpenSchool('portal-kirim');
    $account = ppdbAccount($tenant);

    post(PORTAL_URL.'/formulir', ownApplicationForm($path))
        ->assertRedirect(route('ppdb.account.home'))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'PPDB-27-0001'));

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    expect($applicant->account_id)->toBe($account->id)
        ->and($applicant->wave_id)->toBe($wave->id)
        ->and($applicant->path_id)->toBe($path->id)
        ->and($applicant->source)->toBe(ApplicantSource::Online)
        ->and($applicant->status)->toBe(ApplicantStatus::Submitted)
        ->and($applicant->decision)->toBe(Decision::Pending)
        ->and($applicant->registered_on)->toBe('2027-02-10')
        ->and($applicant->recorded_by)->toBeNull()
        ->and($applicant->tenant_id)->toBe($tenant->id);

    // A second form from the same account is refused and adds nothing.
    post(PORTAL_URL.'/formulir', ownApplicationForm($path, ['name' => 'Anak Lain', 'nisn' => '0079999999']))
        ->assertSessionHasErrors('application');
    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(1);
});

it('ignores whatever the form tries to decide for itself', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('portal-curang');
    [$other] = ppdbOpenSchool('portal-curang-lain');
    $later = ppdbWave($tenant, $period, ['name' => 'Gelombang 2', 'opens_on' => '2027-04-01', 'closes_on' => '2027-06-30']);
    $account = ppdbAccount($tenant);

    post(PORTAL_URL.'/formulir', ownApplicationForm($path, [
        'wave_id' => $later->id,
        'account_id' => 999,
        'tenant_id' => $other->id,
        'school' => $other->id,
        'status' => 'verified',
        'decision' => 'accepted',
        'score' => '99.00',
        'number' => 'PPDB-27-9999',
        'source' => 'staff',
        'student_id' => 5,
    ]))->assertSessionHasNoErrors();

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    expect($applicant->wave_id)->toBe($wave->id)
        ->and($applicant->account_id)->toBe($account->id)
        ->and($applicant->tenant_id)->toBe($tenant->id)
        ->and($applicant->status)->toBe(ApplicantStatus::Submitted)
        ->and($applicant->decision)->toBe(Decision::Pending)
        ->and($applicant->score)->toBeNull()
        ->and($applicant->number)->toBe('PPDB-27-0001')
        ->and($applicant->source)->toBe(ApplicantSource::Online)
        ->and($applicant->student_id)->toBeNull();

    expect(ppdbSchool($other, fn () => Applicant::query()->count()))->toBe(0);
});

it('refuses a registration with missing data, a path of another period or a taken NISN', function () {
    [$tenant, $period, , $path] = ppdbOpenSchool('portal-salah');
    $foreign = ppdbPath($tenant, ppdbPeriod($tenant, ['name' => 'PPDB Lain', 'entry_year' => 2026, 'status' => PeriodStatus::Closed]), ['name' => 'Milik Lain']);
    ppdbApplicant($tenant, $period, ppdbSchool($tenant, fn () => $period->waves()->first()), $path, ['nisn' => '0070000001']);
    ppdbAccount($tenant);

    post(PORTAL_URL.'/formulir', ownApplicationForm($path, ['name' => '']))->assertSessionHasErrors('name');
    post(PORTAL_URL.'/formulir', ownApplicationForm($path, ['birth_date' => '2030-01-01']))->assertSessionHasErrors('birth_date');
    post(PORTAL_URL.'/formulir', ownApplicationForm($foreign))->assertSessionHasErrors('path_id');
    post(PORTAL_URL.'/formulir', ownApplicationForm($path, ['nisn' => '0070000001']))->assertSessionHasErrors('nisn');

    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(1);
});

it('refuses a registration while no wave is open, and from an account that has not joined', function () {
    [$tenant, , , $path] = ppdbOpenSchool('portal-kirim-tutup');
    ppdbAccount($tenant);
    $this->travelTo('2027-06-01 03:00:00');

    post(PORTAL_URL.'/formulir', ownApplicationForm($path))->assertSessionHasErrors('period');
    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0);

    ppdbAccount();
    $this->travelTo('2027-02-10 03:00:00');

    post(PORTAL_URL.'/formulir', ownApplicationForm($path))->assertRedirect(route('ppdb.account.join'));
    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0);
});

it('shows the registration and keeps the decision back until the results are announced', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('portal-hasil');
    $account = ppdbAccount($tenant);
    ppdbApplicant($tenant, $period, $wave, $path, [
        'account_id' => $account->id,
        'name' => 'Nadia Putri',
        'number' => 'PPDB-27-0001',
        'status' => ApplicantStatus::Verified,
        'decision' => Decision::Accepted,
        'score' => '88.40',
    ]);

    get(PORTAL_URL)->assertInertia(fn (Assert $page) => $page
        ->where('portal.state', 'applied')
        ->where('portal.application.number', 'PPDB-27-0001')
        ->where('portal.application.status', 'verified')
        ->where('portal.application.statusLabel', 'Terverifikasi')
        ->where('portal.application.pathName', 'Zonasi')
        ->where('portal.application.resultsPublished', false)
        ->where('portal.application.decision', null)
        ->where('portal.application.decisionLabel', null)
        // Nothing of the score or the decision in the page at all.
        ->missing('portal.application.score')
    );

    ppdbSchool($tenant, fn () => $period->update(['results_published_at' => '2027-07-01 08:00:00']));

    get(PORTAL_URL)->assertInertia(fn (Assert $page) => $page
        ->where('portal.application.resultsPublished', true)
        ->where('portal.application.decision', 'accepted')
        ->where('portal.application.decisionLabel', 'Diterima')
        ->missing('portal.application.score')
    );
});

it('lets the applicant correct the registration only when the committee asks for it', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('portal-perbaiki');
    $account = ppdbAccount($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path, ['account_id' => $account->id, 'name' => 'Nama Salah', 'nisn' => '0070000002']);

    // Waiting for verification: the form is closed and a correction is refused.
    get(PORTAL_URL.'/formulir')->assertRedirect(route('ppdb.account.home'));
    put(PORTAL_URL.'/formulir', ownApplicationForm($path, ['name' => 'Nama Benar', 'nisn' => '0070000002']))->assertSessionHasErrors('application');
    expect(ppdbSchool($tenant, fn () => $applicant->fresh()->name))->toBe('Nama Salah');

    // The committee asks for a correction.
    ppdbSchool($tenant, fn () => $applicant->forceFill(['status' => ApplicantStatus::Revision, 'verification_note' => 'Nama tidak sesuai akta.'])->save());

    get(PORTAL_URL)->assertInertia(fn (Assert $page) => $page
        ->where('portal.application.status', 'revision')
        ->where('portal.application.note', 'Nama tidak sesuai akta.')
        ->where('portal.application.canEdit', true)
    );
    get(PORTAL_URL.'/formulir')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('applicant.name', 'Nama Salah')
        ->where('applicant.note', 'Nama tidak sesuai akta.')
    );

    put(PORTAL_URL.'/formulir', ownApplicationForm($path, ['name' => 'Nama Benar', 'nisn' => '0070000002']))
        ->assertRedirect(route('ppdb.account.home'));

    $saved = ppdbSchool($tenant, fn () => $applicant->fresh());
    expect($saved->name)->toBe('Nama Benar')
        ->and($saved->status)->toBe(ApplicantStatus::Submitted)
        ->and($saved->verification_note)->toBeNull()
        ->and($saved->wave_id)->toBe($wave->id)
        ->and($saved->number)->toBe($applicant->number)
        ->and($saved->account_id)->toBe($account->id);
});

it('keeps one applicant\'s registration out of reach of another account', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('portal-akun-a');
    $first = ppdbAccount($tenant, ['email' => 'pertama@contoh.test']);
    $second = ppdbAccount($tenant, ['email' => 'kedua@contoh.test']);
    $firstApplicant = ppdbApplicant($tenant, $period, $wave, $path, [
        'account_id' => $first->id, 'name' => 'Milik Pertama', 'status' => ApplicantStatus::Revision, 'verification_note' => 'Perbaiki.',
    ]);

    // The second account sees nothing of the first's registration …
    get(PORTAL_URL)->assertInertia(fn (Assert $page) => $page->where('portal.state', 'joined'));
    // … and a correction from it finds nothing to correct.
    put(PORTAL_URL.'/formulir', ownApplicationForm($path, ['name' => 'Dibajak']))->assertNotFound();

    expect(ppdbSchool($tenant, fn () => $firstApplicant->fresh()->name))->toBe('Milik Pertama')
        ->and($second->fresh()->tenant_id)->toBe($tenant->id);
});

it('keeps an applicant in the school it joined, whatever school the session remembers', function () {
    [$tenant, , , $path] = ppdbOpenSchool('portal-sekolah-a');
    [$other] = ppdbOpenSchool('portal-sekolah-b');
    $account = ppdbAccount($tenant);

    $this->withSession(['tenant_id' => $other->id])
        ->get(PORTAL_URL)
        ->assertInertia(fn (Assert $page) => $page->where('portal.school', $tenant->name));

    $this->withSession(['tenant_id' => $other->id])
        ->post(PORTAL_URL.'/formulir?school='.$other->id, ownApplicationForm($path))
        ->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(1)
        ->and(ppdbSchool($other, fn () => Applicant::query()->count()))->toBe(0)
        ->and(ppdbSchool($tenant, fn () => Applicant::query()->sole()->account_id))->toBe($account->id);
});

it('frees the account when the committee cancels the registration', function () {
    [$tenant, , , $path] = ppdbOpenSchool('portal-batal');
    $account = ppdbAccount($tenant);

    post(PORTAL_URL.'/formulir', ownApplicationForm($path))->assertSessionHasNoErrors();
    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    // Locked to the school while the registration stands.
    delete(PORTAL_URL.'/gabung')->assertSessionHasErrors('school');

    ppdbMember($tenant, 'admin-sekolah');
    delete(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"))->assertRedirect('/ppdb/pendaftar');

    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0)
        ->and($account->fresh()->tenant_id)->toBeNull();
});

it('does not free another school\'s account when a registration is cancelled', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('portal-batal-a');
    [$other] = ppdbOpenSchool('portal-batal-b');
    // An account that has since joined the OTHER school (it was freed before).
    $account = ppdbAccount($other);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path, ['account_id' => $account->id]);

    ppdbMember($tenant, 'admin-sekolah');
    delete(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"))->assertRedirect('/ppdb/pendaftar');

    expect($account->fresh()->tenant_id)->toBe($other->id);
});

it('holds an applicant who is not verified out of the form and the join page', function () {
    [$tenant, , , $path] = ppdbOpenSchool('portal-belum-verifikasi');
    $account = PpdbAccountFactory::new()->unverified()->joined($tenant->id)->create();
    \Pest\Laravel\actingAs($account, 'ppdb');

    get(PORTAL_URL.'/formulir')->assertRedirect(route('ppdb.account.verify.notice'));
    post(PORTAL_URL.'/formulir', ownApplicationForm($path))->assertRedirect(route('ppdb.account.verify.notice'));

    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0);
});
