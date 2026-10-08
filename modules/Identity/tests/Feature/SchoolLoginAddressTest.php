<?php

namespace Modules\Identity\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * A school's own login address `/{code}/login` carries the school code,
 * so the form needs no code field and a student only types NIS + password.
 */

it('adopts the school from the login address and hides the code field', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('/'.schoolId('sekolah-a').'/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Identity/Auth/Login')
            ->where('schoolLinked', true));
});

it('logs in without a submitted school code after visiting the school address', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);
    $user = User::factory()->forTenant($tenant->id)->withUsername('24001')->create(['password' => '17082010']);

    get('/'.$tenant->id.'/login')->assertOk();

    post('/login', ['login' => '24001', 'password' => '17082010'])->assertRedirect();

    expect(auth()->user()?->is($user))->toBeTrue();
});

it('adopts the school from its slug in the login address', function () {
    TenantFactory::new()->create(['slug' => '123456']);

    get('/123456/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('schoolLinked', true));
});

it('keeps the code field on an unknown school address', function () {
    get('/'.str_repeat('0', 26).'/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('schoolLinked', false));
});

it('keeps the code field on the plain login page', function () {
    get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('schoolLinked', false));
});
