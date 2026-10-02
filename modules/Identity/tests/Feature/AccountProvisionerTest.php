<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Modules\Identity\App\Contracts\AccountProvisioner;
use Modules\Identity\App\Contracts\DTOs\NewAccount;
use Modules\Identity\App\Contracts\Exceptions\AccountActionRefusedException;
use Modules\Identity\App\Contracts\Exceptions\UsernameTakenException;
use Modules\Identity\App\Contracts\ResolvesUsers;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

/*
 * AccountProvisioner — how the module owning a student or a teacher gives
 * that person an account without touching the User model.
 */

/**
 * @template T
 *
 * @param  callable(AccountProvisioner): T  $callback
 * @return T
 */
function provisioning(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->run($tenant->id, fn () => $callback(app(AccountProvisioner::class)));
}

function studentAccount(string $username = '24001'): NewAccount
{
    return new NewAccount(name: 'Aditya Nugraha', username: $username, password: '04032010', role: 'siswa');
}

it('creates an account that signs in by username, holds the role and must change its password', function () {
    $tenant = TenantFactory::new()->create();

    $id = provisioning($tenant, fn (AccountProvisioner $accounts): int => $accounts->create(studentAccount()));

    app(TenantContext::class)->run($tenant->id, function () use ($id): void {
        $user = User::query()->findOrFail($id);

        expect($user)
            ->username->toBe('24001')
            ->email->toBeNull()
            ->must_change_password->toBeTrue()
            ->and(Hash::check('04032010', (string) $user->password))->toBeTrue()
            ->and($user->hasTenantRole('siswa'))->toBeTrue();
    });
});

it('refuses a username or an email another account of the school holds', function (NewAccount $second) {
    $tenant = TenantFactory::new()->create();

    provisioning($tenant, fn (AccountProvisioner $accounts): int => $accounts->create(
        new NewAccount(name: 'Bu Rina', username: '1987001', password: 'x', role: 'guru', email: 'rina@sekolah.test'),
    ));

    expect(fn () => provisioning($tenant, fn (AccountProvisioner $accounts): int => $accounts->create($second)))
        ->toThrow(UsernameTakenException::class);

    expect(User::withoutTenancy()->count())->toBe(1);
})->with([
    'same username' => [new NewAccount(name: 'Lain', username: '1987001', password: 'x', role: 'guru')],
    'same email' => [new NewAccount(name: 'Lain', username: '1987002', password: 'x', role: 'guru', email: 'Rina@Sekolah.test')],
]);

it('allows the same username in another school', function () {
    $a = TenantFactory::new()->create();
    $b = TenantFactory::new()->create();

    provisioning($a, fn (AccountProvisioner $accounts): int => $accounts->create(studentAccount()));
    provisioning($b, fn (AccountProvisioner $accounts): int => $accounts->create(studentAccount()));

    expect(User::withoutTenancy()->where('username', '24001')->count())->toBe(2);
});

it('refuses a role the school does not have', function () {
    $tenant = TenantFactory::new()->create();

    provisioning($tenant, fn (AccountProvisioner $accounts): int => $accounts->create(
        new NewAccount(name: 'X', username: '1', password: 'x', role: 'kepala-yayasan'),
    ));
})->throws(AccountActionRefusedException::class);

it('resets the password and asks for a change again', function () {
    $tenant = TenantFactory::new()->create();
    $user = User::factory()->forTenant($tenant->id)->withUsername('24001')->create(['password' => 'SandiSendiri1!']);

    provisioning($tenant, fn (AccountProvisioner $accounts) => $accounts->resetPassword($user->id, '04032010'));

    $user->refresh();

    expect(Hash::check('04032010', (string) $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();
});

it('follows a change of name and number, and refuses a number that is taken', function () {
    $tenant = TenantFactory::new()->create();
    $user = User::factory()->forTenant($tenant->id)->withUsername('24001')->create();
    User::factory()->forTenant($tenant->id)->withUsername('24002')->create();

    provisioning($tenant, fn (AccountProvisioner $accounts) => $accounts->updateIdentity($user->id, 'Nama Baru', '24009'));

    expect($user->refresh())->name->toBe('Nama Baru')->username->toBe('24009');

    expect(fn () => provisioning($tenant, fn (AccountProvisioner $accounts) => $accounts->updateIdentity($user->id, 'Nama Baru', '24002')))
        ->toThrow(UsernameTakenException::class);

    // Keeping its own username is not a clash.
    provisioning($tenant, fn (AccountProvisioner $accounts) => $accounts->updateIdentity($user->id, 'Nama Lagi', '24009'));

    expect($user->refresh()->name)->toBe('Nama Lagi');
});

it('deactivates and reactivates an account', function () {
    $tenant = TenantFactory::new()->create();
    $user = User::factory()->forTenant($tenant->id)->withUsername('24001')->create();

    provisioning($tenant, fn (AccountProvisioner $accounts) => $accounts->deactivate($user->id));
    expect($user->refresh()->isActive())->toBeFalse();

    provisioning($tenant, fn (AccountProvisioner $accounts) => $accounts->reactivate($user->id));
    expect($user->refresh()->isActive())->toBeTrue();
});

it('never deactivates the last active admin of the school', function () {
    $tenant = TenantFactory::new()->create();
    $admin = User::factory()->forTenant($tenant->id)->create();
    app(TenantContext::class)->run($tenant->id, fn () => $admin->assignTenantRole('admin-sekolah'));

    expect(fn () => provisioning($tenant, fn (AccountProvisioner $accounts) => $accounts->deactivate($admin->id)))
        ->toThrow(AccountActionRefusedException::class);

    expect($admin->refresh()->isActive())->toBeTrue();
});

it('does not reach an account of another school', function () {
    $a = TenantFactory::new()->create();
    $b = TenantFactory::new()->create();
    $user = User::factory()->forTenant($a->id)->withUsername('24001')->create(['password' => 'SandiSendiri1!']);

    expect(fn () => provisioning($b, fn (AccountProvisioner $accounts) => $accounts->resetPassword($user->id, 'x')))
        ->toThrow(AccountActionRefusedException::class);

    expect(fn () => provisioning($b, fn (AccountProvisioner $accounts) => $accounts->deactivate($user->id)))
        ->toThrow(AccountActionRefusedException::class);

    expect($user->refresh()->isActive())->toBeTrue()
        ->and(Hash::check('SandiSendiri1!', (string) $user->password))->toBeTrue();
});

it('fails closed without a school', function () {
    app(AccountProvisioner::class)->create(studentAccount());
})->throws(TenantNotSetException::class);

it('describes the accounts of the current school by id', function () {
    $a = TenantFactory::new()->create();
    $b = TenantFactory::new()->create();
    $mine = User::factory()->forTenant($a->id)->withUsername('24001')->mustChangePassword()->create();
    $theirs = User::factory()->forTenant($b->id)->withUsername('24002')->create();

    $records = app(TenantContext::class)->run(
        $a->id,
        fn (): array => app(ResolvesUsers::class)->findMany([$mine->id, $theirs->id, 999_999]),
    );

    expect(array_keys($records))->toBe([$mine->id])
        ->and($records[$mine->id])
        ->username->toBe('24001')
        ->email->toBeNull()
        ->active->toBeTrue()
        ->mustChangePassword->toBeTrue();
});
