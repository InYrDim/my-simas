<?php

namespace Modules\Identity\Tests\Feature;

use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\from;
use function Pest\Laravel\post;

/*
 * The login form takes one identity: an email, or the username of an
 * account that has none (a student's NIS, a teacher's NIP).
 */

/**
 * @param  array<string, mixed>  $attributes
 */
function usernameAccount(string $slug, string $username, array $attributes = []): User
{
    $tenant = TenantFactory::new()->create(['slug' => $slug]);

    return User::factory()->forTenant($tenant->id)->withUsername($username)->create([
        'password' => '17082010',
        ...$attributes,
    ]);
}

it('logs an account in by its username', function () {
    $user = usernameAccount('sekolah-a', '24001');

    post(school('sekolah-a', '/login'), [
        'school' => schoolId('sekolah-a'),
        'login' => '24001',
        'password' => '17082010',
    ])->assertRedirect();

    expect(auth()->user()?->is($user))->toBeTrue();
});

it('still logs an account in by its email', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);
    $user = User::factory()->forTenant($tenant->id)->create(['email' => 'budi@example.com', 'username' => 'budi', 'password' => 'password123']);

    post(school('sekolah-a', '/login'), [
        'school' => schoolId('sekolah-a'),
        'login' => 'budi@example.com',
        'password' => 'password123',
    ])->assertRedirect();

    expect(auth()->user()?->is($user))->toBeTrue();
});

it('refuses a wrong password with the generic error', function () {
    usernameAccount('sekolah-a', '24001');

    from(school('sekolah-a', '/login'))
        ->post(school('sekolah-a', '/login'), [
            'school' => schoolId('sekolah-a'),
            'login' => '24001',
            'password' => 'salah-sekali',
        ])->assertSessionHasErrors(['login' => __('auth.failed')]);

    expect(auth()->user())->toBeNull();
});

it('refuses the username of another school', function () {
    usernameAccount('sekolah-a', '24001');
    usernameAccount('sekolah-b', '24999');

    from(school('sekolah-b', '/login'))
        ->post(school('sekolah-b', '/login'), [
            'school' => schoolId('sekolah-b'),
            'login' => '24001',
            'password' => '17082010',
        ])->assertSessionHasErrors(['login' => __('auth.failed')]);

    expect(auth()->user())->toBeNull();
});

it('refuses a deactivated username account with the same generic error', function () {
    usernameAccount('sekolah-a', '24001', ['deactivated_at' => now()]);

    from(school('sekolah-a', '/login'))
        ->post(school('sekolah-a', '/login'), [
            'school' => schoolId('sekolah-a'),
            'login' => '24001',
            'password' => '17082010',
        ])->assertSessionHasErrors(['login' => __('auth.failed')]);

    expect(auth()->user())->toBeNull();
});

it('throttles one username without locking out another', function () {
    $first = usernameAccount('sekolah-a', '24001');
    User::factory()->forTenant($first->tenant_id)->withUsername('24002')->create(['password' => '01012011']);

    for ($i = 0; $i < 5; $i++) {
        post(school('sekolah-a', '/login'), [
            'school' => schoolId('sekolah-a'),
            'login' => '24001',
            'password' => 'salah-sekali',
        ])->assertSessionHasErrors('login');
    }

    // The sixth attempt is throttled even with the right password...
    post(school('sekolah-a', '/login'), [
        'school' => schoolId('sekolah-a'),
        'login' => '24001',
        'password' => '17082010',
    ])->assertSessionHasErrors('login');

    expect(auth()->user())->toBeNull();

    // ...while the classmate signs in as usual.
    post(school('sekolah-a', '/login'), [
        'school' => schoolId('sekolah-a'),
        'login' => '24002',
        'password' => '01012011',
    ])->assertRedirect();

    expect(auth()->user()?->username)->toBe('24002');
});
