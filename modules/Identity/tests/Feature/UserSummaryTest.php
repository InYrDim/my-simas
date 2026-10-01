<?php

namespace Modules\Identity\Tests\Feature;

use Modules\Identity\App\Contracts\ResolvesUsers;
use Modules\Identity\App\Contracts\UserSummary;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

/**
 * ResolvesUsers::currentTenantSummary() — the counts a module outside
 * Identity needs in order to report on a tenant's accounts without
 * importing the User model.
 */
function summaryTenant(string $slug): Tenant
{
    return TenantFactory::new()->create(['slug' => $slug]);
}

function summaryUser(Tenant $tenant, string $email, ?string $role = null): User
{
    $user = User::factory()->forTenant($tenant->id)->create(['email' => $email]);

    if ($role !== null) {
        app(TenantContext::class)->run($tenant->id, function () use ($user, $role): void {
            $user->assignTenantRole($role);
        });
    }

    return $user;
}

/** An invited account: real row, password still null, not yet activated. */
function summaryInvited(Tenant $tenant, string $email): User
{
    return User::factory()
        ->forTenant($tenant->id)
        ->invited()
        ->create(['email' => $email, 'password' => null]);
}

function summaryOf(Tenant $tenant): UserSummary
{
    return app(TenantContext::class)->run(
        $tenant->id,
        fn (): UserSummary => app(ResolvesUsers::class)->currentTenantSummary(),
    );
}

it('counts the accounts of the current tenant only', function () {
    $a = summaryTenant('sum-a');
    $b = summaryTenant('sum-b');

    summaryUser($a, 'admin@sum-a.test', 'admin-sekolah');
    summaryUser($a, 'guru@sum-a.test', 'guru');
    summaryUser($b, 'admin@sum-b.test', 'admin-sekolah');

    $summary = summaryOf($a);

    expect($summary->total)->toBe(2)
        ->and($summary->active)->toBe(2)
        ->and($summary->awaitingActivation)->toBe(0)
        ->and($summary->deactivated)->toBe(0)
        ->and($summary->withoutRole)->toBe(0);
});

it('keeps deactivated accounts in the total and reports them apart', function () {
    $tenant = summaryTenant('sum-deact');
    summaryUser($tenant, 'admin@sum-deact.test', 'admin-sekolah');
    User::factory()
        ->forTenant($tenant->id)
        ->deactivated()
        ->create(['email' => 'guru@sum-deact.test']);

    $summary = summaryOf($tenant);

    expect($summary->total)->toBe(2)
        ->and($summary->active)->toBe(1)
        ->and($summary->deactivated)->toBe(1);
});

it('counts an invited account as awaiting, never as active', function () {
    $tenant = summaryTenant('sum-invite');
    summaryUser($tenant, 'admin@sum-invite.test', 'admin-sekolah');
    summaryInvited($tenant, 'guru@sum-invite.test');

    $summary = summaryOf($tenant);

    expect($summary->total)->toBe(2)
        ->and($summary->active)->toBe(1)
        ->and($summary->awaitingActivation)->toBe(1)
        ->and($summary->deactivated)->toBe(0);
});

it('partitions every account into exactly one state', function () {
    $tenant = summaryTenant('sum-states');
    summaryUser($tenant, 'admin@sum-states.test', 'admin-sekolah');
    summaryInvited($tenant, 'menunggu@sum-states.test');
    User::factory()
        ->forTenant($tenant->id)
        ->deactivated()
        ->create(['email' => 'lama@sum-states.test']);

    $summary = summaryOf($tenant);

    expect($summary->active + $summary->awaitingActivation + $summary->deactivated)
        ->toBe($summary->total);
});

it('counts accounts that hold no role at all', function () {
    $tenant = summaryTenant('sum-roles');
    summaryUser($tenant, 'admin@sum-roles.test', 'admin-sekolah');
    summaryUser($tenant, 'guru@sum-roles.test', 'guru');
    summaryUser($tenant, 'staf@sum-roles.test', 'staf-tu');
    summaryUser($tenant, 'baru@sum-roles.test');

    expect(summaryOf($tenant)->withoutRole)->toBe(1);
});

it('reports zeros for a tenant with no accounts', function () {
    $summary = summaryOf(summaryTenant('sum-empty'));

    expect($summary->total)->toBe(0)
        ->and($summary->active)->toBe(0)
        ->and($summary->awaitingActivation)->toBe(0)
        ->and($summary->deactivated)->toBe(0)
        ->and($summary->withoutRole)->toBe(0);
});

it('fails closed when asked without tenant context', function () {
    app(ResolvesUsers::class)->currentTenantSummary();
})->throws(TenantNotSetException::class);
