<?php

namespace Modules\Ppdb\Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Ppdb\App\Domain\Actions\Account\JoinSchool;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\Database\Factories\PpdbAccountFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/Support/helpers.php';

/*
 * Joining a school from an applicant's account, with the school's code
 * (or the link the school hands out), and leaving it again.
 */

const JOIN_URL = 'http://localhost/calon-siswa/gabung';

it('shows the join form with the code from the school\'s link', function () {
    [$tenant] = ppdbOpenSchool('gabung-tautan');
    ppdbAccount();

    get(JOIN_URL.'?school='.strtoupper($tenant->id))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Account/Join')
        ->where('code', $tenant->id)
    );

    get(JOIN_URL)->assertInertia(fn (Assert $page) => $page->where('code', ''));
});

it('sends a guest from the school\'s link to the applicant login and remembers the link', function () {
    [$tenant] = ppdbOpenSchool('gabung-tamu');

    get(JOIN_URL.'?school='.$tenant->id)
        ->assertRedirect(route('ppdb.account.login'))
        ->assertSessionHas('url.intended', fn (string $url) => str_contains($url, '/calon-siswa/gabung?school='.$tenant->id));

    assertGuest('ppdb');
});

it('holds an unverified account at the verification notice', function () {
    actingAs(PpdbAccountFactory::new()->unverified()->create(), 'ppdb');

    get(JOIN_URL)->assertRedirect(route('ppdb.account.verify.notice'));
});

it('joins the school with its code, however it is typed', function () {
    [$tenant] = ppdbOpenSchool('gabung-berhasil');
    $account = ppdbAccount();

    post(JOIN_URL, ['code' => '  '.strtoupper($tenant->id).'  '])
        ->assertRedirect(route('ppdb.account.home'))
        ->assertSessionHas('status');

    expect($account->fresh()->tenant_id)->toBe($tenant->id);
});

it('joins the school by its school code', function () {
    [$tenant] = ppdbOpenSchool('gabung-kode');
    $account = ppdbAccount();

    post(JOIN_URL, ['code' => ' Gabung-Kode '])->assertRedirect(route('ppdb.account.home'));

    expect($account->fresh()->tenant_id)->toBe($tenant->id);
});

it('refuses every school that cannot be joined with the very same message', function () {
    [$open] = ppdbOpenSchool('gabung-buka');

    $suspended = TenantFactory::new()->create(['slug' => 'gabung-ditangguhkan']);
    app(ModuleFlagManager::class)->enable($suspended->id, 'ppdb');
    ppdbPeriod($suspended, ['status' => PeriodStatus::Active]);
    $suspended->forceFill(['status' => 'suspended'])->save();

    // Has PPDB switched on, but no period is running.
    $quiet = TenantFactory::new()->create(['slug' => 'gabung-sepi']);
    app(ModuleFlagManager::class)->enable($quiet->id, 'ppdb');
    ppdbPeriod($quiet, ['status' => PeriodStatus::Closed]);

    // Has a running period in its tables but the module is not on for it.
    $without = TenantFactory::new()->create(['slug' => 'gabung-tanpa-modul']);
    ppdbPeriod($without, ['status' => PeriodStatus::Active]);

    $account = ppdbAccount();

    foreach ([
        'nonsense' => 'bukan-kode',
        'empty-looking' => str_repeat('0', 26),
        'unknown school' => '01arz3ndektsv4rrffq69g5fav',
        'suspended' => $suspended->id,
        'no running period' => $quiet->id,
        'module off' => $without->id,
    ] as $label => $code) {
        post(JOIN_URL, ['code' => $code])->assertSessionHasErrors(['code' => JoinSchool::NOT_AVAILABLE]);
        expect($account->fresh()->tenant_id)->toBeNull($label);
    }

    // The one that can be joined still can.
    post(JOIN_URL, ['code' => $open->id])->assertSessionHasNoErrors();
    expect($account->fresh()->tenant_id)->toBe($open->id);
});

it('refuses a code that is missing', function () {
    ppdbAccount();

    post(JOIN_URL, ['code' => ''])->assertSessionHasErrors('code');
});

it('counts refused attempts and then makes the applicant wait', function () {
    [$tenant] = ppdbOpenSchool('gabung-batas');
    $account = ppdbAccount();
    RateLimiter::clear('ppdb-join:'.$account->id.':127.0.0.1');

    foreach (range(1, 10) as $attempt) {
        post(JOIN_URL, ['code' => 'salah'])->assertSessionHasErrors(['code' => JoinSchool::NOT_AVAILABLE]);
    }

    // Now even the right code waits.
    post(JOIN_URL, ['code' => $tenant->id])->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->toStartWith('Terlalu banyak percobaan')
        ->and($account->fresh()->tenant_id)->toBeNull();
});

it('keeps an account to one school at a time', function () {
    [$first] = ppdbOpenSchool('gabung-satu');
    [$second] = ppdbOpenSchool('gabung-dua');
    $account = ppdbAccount($first);

    // The same school again changes nothing and is no error.
    post(JOIN_URL, ['code' => $first->id])->assertSessionHasNoErrors();

    post(JOIN_URL, ['code' => $second->id])->assertSessionHasErrors('code');
    expect($account->fresh()->tenant_id)->toBe($first->id);

    get(JOIN_URL.'?school='.$second->id)
        ->assertRedirect(route('ppdb.account.home'))
        ->assertSessionHas('status');
});

it('lets an account leave a school until it has sent the form, then join another', function () {
    [$first] = ppdbOpenSchool('keluar-satu');
    [$second] = ppdbOpenSchool('keluar-dua');
    $account = ppdbAccount($first);

    delete(JOIN_URL)->assertRedirect(route('ppdb.account.home'))->assertSessionHas('status');
    expect($account->fresh()->tenant_id)->toBeNull();

    post(JOIN_URL, ['code' => $second->id])->assertSessionHasNoErrors();
    expect($account->fresh()->tenant_id)->toBe($second->id);
});

it('does not let an account that has sent the form leave', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('keluar-terkunci');
    $account = ppdbAccount($tenant);
    ppdbApplicant($tenant, $period, $wave, $path, ['account_id' => $account->id]);

    delete(JOIN_URL)->assertSessionHasErrors('school');

    expect($account->fresh()->tenant_id)->toBe($tenant->id);
});
