<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;

/**
 * Stage 9 (Fase 2): UserPolicy — the authorization seam for school-
 * admin user management.
 *
 * - permission = which action (identity.users.* via tenant roles)
 * - policy     = which data (target must be same-tenant)
 * - deactivated actors hold no rights (before-hook deny)
 * - Guru/Staf (empty permission sets) are refused everywhere
 *
 * Unit-style `can()` checks resolve permissions through Spatie's team
 * pointer, which follows the ambient tenant context — so the checks
 * run inside TenantContext::run(), exactly like the HTTP path where
 * ResolveTenant sets the context before the policy executes.
 */
function policyTenant(string $slug): Tenant
{
    return TenantFactory::new()->create(['slug' => $slug]);
}

function policyUser(
    Tenant $tenant,
    string $email,
    ?string $role = null,
): User {
    $user = User::factory()->forTenant($tenant->id)->create(['email' => $email]);

    if ($role !== null) {
        app(TenantContext::class)->run($tenant->id, function () use ($user, $role): void {
            $user->assignTenantRole($role);
        });
    }

    return $user;
}

it('grants a school admin every user-management ability', function () {
    $tenant = policyTenant('policy-a');
    $admin = policyUser($tenant, 'admin@policy-a.test', 'admin-sekolah');
    $target = policyUser($tenant, 'guru@policy-a.test', 'guru');

    actingAs($admin);

    app(TenantContext::class)->run($tenant->id, function () use ($admin, $target): void {
        expect($admin->can('viewAny', User::class))->toBeTrue()
            ->and($admin->can('create', User::class))->toBeTrue()
            ->and($admin->can('update', $target))->toBeTrue()
            ->and($admin->can('deactivate', $target))->toBeTrue()
            ->and($admin->can('reactivate', $target))->toBeTrue()
            ->and($admin->can('sendReset', $target))->toBeTrue();
    });
});

it('refuses guru and staf-tu (no user-management permissions)', function () {
    $tenant = policyTenant('policy-b');
    $guru = policyUser($tenant, 'guru@policy-b.test', 'guru');
    $staf = policyUser($tenant, 'staf@policy-b.test', 'staf-tu');
    $target = policyUser($tenant, 'lain@policy-b.test');

    actingAs($guru);

    app(TenantContext::class)->run($tenant->id, function () use ($guru, $target): void {
        expect($guru->can('viewAny', User::class))->toBeFalse()
            ->and($guru->can('create', User::class))->toBeFalse()
            ->and($guru->can('update', $target))->toBeFalse()
            ->and($guru->can('deactivate', $target))->toBeFalse()
            ->and($guru->can('sendReset', $target))->toBeFalse();
    });

    actingAs($staf);

    app(TenantContext::class)->run($tenant->id, function () use ($staf): void {
        expect($staf->can('viewAny', User::class))->toBeFalse()
            ->and($staf->can('create', User::class))->toBeFalse();
    });
});

it('refuses everything for a deactivated actor', function () {
    $tenant = policyTenant('policy-c');
    $admin = policyUser($tenant, 'admin@policy-c.test', 'admin-sekolah');
    $target = policyUser($tenant, 'guru@policy-c.test');

    $admin->forceFill(['deactivated_at' => now()])->save();

    actingAs($admin);

    app(TenantContext::class)->run($tenant->id, function () use ($admin, $target): void {
        // The before-hook denies BEFORE permission evaluation — even
        // the permission-carrying role cannot act once deactivated.
        expect($admin->can('viewAny', User::class))->toBeFalse()
            ->and($admin->can('update', $target))->toBeFalse()
            ->and($admin->can('deactivate', $target))->toBeFalse();
    });
});

it('refuses cross-tenant targets even with full permissions', function () {
    $a = policyTenant('policy-d-a');
    $b = policyTenant('policy-d-b');

    $adminA = policyUser($a, 'admin@policy-d-a.test', 'admin-sekolah');
    $userB = policyUser($b, 'korban@policy-d-b.test');

    actingAs($adminA);

    app(TenantContext::class)->run($a->id, function () use ($adminA, $userB): void {
        // Policy re-asserts target tenancy — the last line of defence.
        expect($adminA->can('update', $userB))->toBeFalse()
            ->and($adminA->can('deactivate', $userB))->toBeFalse()
            ->and($adminA->can('reactivate', $userB))->toBeFalse()
            ->and($adminA->can('sendReset', $userB))->toBeFalse();
    });
});

it('guests hold no abilities at all', function () {
    $tenant = policyTenant('policy-e');
    policyUser($tenant, 'siapa@policy-e.test');

    // No actingAs — guest request: the policy methods type-hint the
    // actor, so a null user never even reaches them.
    expect(auth()->user())->toBeNull()
        ->and(Gate::allows('viewAny', User::class))->toBeFalse();
});
