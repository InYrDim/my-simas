<?php

use Illuminate\Support\Facades\Auth;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Provider console authentication (central hosts only, 'provider'
 * guard). Complements Identity's TenantAuthTest which proves the
 * tenant side of the guard separation.
 */
it('renders the provider login page on the central host', function () {
    get('http://localhost/platform/login')
        ->assertOk()
        ->assertSee('Console Provider', false);
});

it('refuses the provider console on a tenant host', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('http://sekolah-a.localhost/platform/login')->assertNotFound();
});

it('logs a provider user in on the central host', function () {
    /** @var ProviderUser $provider */
    $provider = ProviderUserFactory::new()->create([
        'email' => 'ops@simas.test',
        'password' => 'password123',
    ]);

    post('http://localhost/platform/login', [
        'email' => 'ops@simas.test',
        'password' => 'password123',
    ])->assertRedirect();

    expect(Auth::guard('provider')->check())->toBeTrue()
        ->and(Auth::guard('provider')->user()?->is($provider))->toBeTrue()
        ->and(Auth::guard('web')->check())->toBeFalse();
});

it('rejects wrong provider credentials', function () {
    ProviderUserFactory::new()->create([
        'email' => 'ops@simas.test',
        'password' => 'password123',
    ]);

    from('http://localhost/platform/login')
        ->post('http://localhost/platform/login', [
            'email' => 'ops@simas.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

    expect(Auth::guard('provider')->check())->toBeFalse();
});

it('does not accept tenant credentials on the provider login', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    // provider_users is a separate table: this email does not exist there.
    post('http://localhost/platform/login', [
        'email' => 'admin@sekolah-a.test',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    expect(Auth::guard('provider')->check())->toBeFalse();
});

it('throttles provider login attempts', function () {
    ProviderUserFactory::new()->create([
        'email' => 'ops@simas.test',
        'password' => 'password123',
    ]);

    for ($i = 0; $i < 5; $i++) {
        post('http://localhost/platform/login', [
            'email' => 'ops@simas.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    post('http://localhost/platform/login', [
        'email' => 'ops@simas.test',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    // The 6th error is the throttle message, not auth.failed.
    session()->regenerate();
});

it('logs the provider user out', function () {
    /** @var ProviderUser $provider */
    $provider = ProviderUserFactory::new()->create();

    actingAs($provider, 'provider');

    expect(Auth::guard('provider')->check())->toBeTrue();

    post('http://localhost/platform/logout')->assertRedirect();

    expect(Auth::guard('provider')->check())->toBeFalse();
});
