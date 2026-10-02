<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/*
 * An account whose password somebody else chose has to set its own before
 * it reaches anything else; every signed-in user may change theirs.
 */

function givenPasswordAccount(string $slug = 'sekolah-a'): User
{
    $tenant = TenantFactory::new()->create(['slug' => $slug]);

    $user = User::factory()->forTenant($tenant->id)->withUsername('24001')->mustChangePassword()->create([
        'password' => '17082010',
    ]);

    app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole('siswa'));

    return $user;
}

it('sends a flagged account to the change-password page from anywhere', function () {
    actingAs(givenPasswordAccount());

    get(school('sekolah-a', '/beranda'))->assertRedirect(route('password.change'));
    get(school('sekolah-a', '/users'))->assertRedirect(route('password.change'));
    getJson(school('sekolah-a', '/beranda'))->assertForbidden();
});

it('shows the flagged account the page and tells it the change is required', function () {
    actingAs(givenPasswordAccount());

    get(school('sekolah-a', '/ganti-kata-sandi'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Identity/Auth/ChangePassword')
        ->where('forced', true));
});

it('still lets a flagged account sign out', function () {
    actingAs(givenPasswordAccount());

    post(school('sekolah-a', '/logout'))->assertRedirect(route('login'));

    expect(auth()->user())->toBeNull();
});

it('sets the new password, clears the flag and opens the school', function () {
    $user = givenPasswordAccount();
    actingAs($user);

    put(school('sekolah-a', '/ganti-kata-sandi'), [
        'current_password' => '17082010',
        'password' => 'SandiBaruSaya1!',
        'password_confirmation' => 'SandiBaruSaya1!',
    ])->assertRedirect(route('home'));

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('SandiBaruSaya1!', (string) $user->password))->toBeTrue();

    get(school('sekolah-a', '/beranda'))->assertOk();
});

it('refuses a wrong current password, a mismatch and the same password again', function (array $input, string $field) {
    $user = givenPasswordAccount();
    actingAs($user);

    from(school('sekolah-a', '/ganti-kata-sandi'))
        ->put(school('sekolah-a', '/ganti-kata-sandi'), $input)
        ->assertSessionHasErrors($field);

    $user->refresh();

    expect($user->must_change_password)->toBeTrue()
        ->and(Hash::check('17082010', (string) $user->password))->toBeTrue();
})->with([
    'wrong current password' => [['current_password' => 'bukan-ini', 'password' => 'SandiBaruSaya1!', 'password_confirmation' => 'SandiBaruSaya1!'], 'current_password'],
    'confirmation differs' => [['current_password' => '17082010', 'password' => 'SandiBaruSaya1!', 'password_confirmation' => 'SandiLainSaya1!'], 'password'],
    'same as the given one' => [['current_password' => '17082010', 'password' => '17082010', 'password_confirmation' => '17082010'], 'password'],
]);

it('lets an ordinary account change its password without being forced', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);
    $user = User::factory()->forTenant($tenant->id)->create(['password' => 'SandiLamaSaya1!']);
    app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole('guru'));
    // As the guard loads it: a factory instance lacks the column defaults.
    actingAs($user->fresh());

    get(school('sekolah-a', '/beranda'))->assertOk();

    get(school('sekolah-a', '/ganti-kata-sandi'))->assertInertia(fn (Assert $page) => $page->where('forced', false));

    put(school('sekolah-a', '/ganti-kata-sandi'), [
        'current_password' => 'SandiLamaSaya1!',
        'password' => 'SandiBaruSaya1!',
        'password_confirmation' => 'SandiBaruSaya1!',
    ])->assertRedirect(route('home'));

    expect(Hash::check('SandiBaruSaya1!', (string) $user->refresh()->password))->toBeTrue();
});

it('asks a guest to sign in first', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get(school('sekolah-a', '/ganti-kata-sandi'))->assertRedirect(route('login'));
});
