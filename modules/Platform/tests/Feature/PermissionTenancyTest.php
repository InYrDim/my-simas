<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Platform\App\Contracts\Concerns\HasTenantRoles;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Test-only tenant user exercising HasTenantRoles. Identity's User
 * model adopts the trait in Stage 9 — here we prove the Platform-side
 * machinery first. users table exists (Identity migration); rows are
 * created directly to avoid importing Identity models (arch rule).
 */
class PlatformTenantUser extends User
{
    use HasTenantRoles;

    protected $table = 'users';

    protected $guarded = [];

    // Spatie resolves the guard from the AUTH session by default; a
    // bare model outside a request resolves to ''. Pin it explicitly.
    protected ?string $guard_name = 'web';
}

uses(InteractsWithAuthentication::class);

beforeEach(function () {
    // Probe routes proving guard separation at the middleware level.
    Route::get('/tenant-guard-probe', fn () => 'tenant-'.(Auth::guard('web')->id() ?? 'none'))
        ->middleware(['web']);
    Route::get('/provider-guard-probe', fn () => 'provider-'.(Auth::guard('provider')->id() ?? 'none'))
        ->middleware(['web']);
});

function tenantForPermissions(string $slug): string
{
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create(['slug' => $slug]);

    return $tenant->id;
}

function tenantUserFor(string $email): PlatformTenantUser
{
    // Central write: users table is not yet tenant-scoped (Stage 9),
    // so create outside the scope machinery.
    $id = DB::table('users')->insertGetId([
        'name' => 'User '.$email,
        'email' => $email,
        'password' => 'password',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    /** @var PlatformTenantUser $user */
    $user = PlatformTenantUser::query()->findOrFail($id);

    return $user;
}

/**
 * Re-fetch the user inside the given tenant context: Spatie caches
 * the roles RELATION on the model instance, so a cross-team switch on
 * the same instance reflects stale teams (framework semantics — the
 * real flow always re-resolves per request/job).
 */
function userInTenant(string $email): PlatformTenantUser
{
    /** @var PlatformTenantUser $user */
    $user = PlatformTenantUser::query()->where('email', $email)->firstOrFail();

    return $user;
}

it('keeps same-named roles apart between tenants', function () {
    $a = tenantForPermissions('sekolah-a');
    $b = tenantForPermissions('sekolah-b');
    $context = app(TenantContext::class);

    $context->run($a, function (): void {
        tenantUserFor('guru@a.test')->assignTenantRole('guru');
    });

    $context->run($b, function (): void {
        tenantUserFor('guru@b.test')->assignTenantRole('guru');
    });

    // Two role rows share the NAME but live in different tenants.
    $roleCount = DB::table('roles')->where('name', 'guru')->count();
    expect($roleCount)->toBe(2);

    // A pivot row is visible in its own tenant, invisible in the other.
    $context->run($a, fn () => expect(userInTenant('guru@a.test')->hasTenantRole('guru'))->toBeTrue());
    $context->run($b, fn () => expect(userInTenant('guru@a.test')->hasTenantRole('guru'))->toBeFalse());

    // Removing in B does not touch A.
    $context->run($b, function (): void {
        $user = userInTenant('guru@b.test');
        $user->removeTenantRole('guru');
        expect($user->hasTenantRole('guru'))->toBeFalse();
    });

    $context->run($a, fn () => expect(userInTenant('guru@a.test')->hasTenantRole('guru'))->toBeTrue());
});

it('does not let a role assigned in tenant A appear in tenant B', function () {
    $a = tenantForPermissions('sekolah-a');
    $b = tenantForPermissions('sekolah-b');
    $context = app(TenantContext::class);

    $context->run($a, function (): void {
        tenantUserFor('guru-a@test.local')->assignTenantRole('guru');
    });

    $context->run($b, function (): void {
        tenantUserFor('guru-b@test.local');
        expect(userInTenant('guru-a@test.local')->hasTenantRole('guru'))->toBeFalse();
    });
});

it('treats null-tenant roles as global rows but keeps assignments per tenant', function () {
    $a = tenantForPermissions('sekolah-a');
    $b = tenantForPermissions('sekolah-b');
    $context = app(TenantContext::class);

    // Global role row (tenant_id NULL) — Spatie teams semantics: the
    // ROW is visible to every team, but assigning it to a user writes
    // a pivot row stamped with the CURRENT team (required tenant_id).
    $context->runWithoutTenant(function (): void {
        DB::table('roles')->insert([
            'tenant_id' => null,
            'name' => 'platform-ops',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $context->run($a, function (): void {
        tenantUserFor('ops@test.local')->assignRole('platform-ops');
    });

    // Assignment belongs to A only: user has it in A, not in B.
    $context->run($a, fn () => expect(userInTenant('ops@test.local')->hasTenantRole('platform-ops'))->toBeTrue());
    $context->run($b, fn () => expect(userInTenant('ops@test.local')->hasTenantRole('platform-ops'))->toBeFalse());

    // The pivot row carries tenant A's id (never null, never B's).
    $pivotTenantIds = DB::table('model_has_roles')
        ->where('role_id', DB::table('roles')->where('name', 'platform-ops')->value('id'))
        ->pluck('tenant_id')
        ->unique()
        ->all();

    expect($pivotTenantIds)->toBe([$a]);
});

it('refuses to resolve roles without tenant context', function () {
    tenantUserFor('central@test.local');

    userInTenant('central@test.local')->assignTenantRole('guru');
})->throws(TenantNotSetException::class);

it('switches teams correctly within one process', function () {
    $a = tenantForPermissions('sekolah-a');
    $b = tenantForPermissions('sekolah-b');
    $context = app(TenantContext::class);

    $context->run($a, function (): void {
        tenantUserFor('multi@test.local')->assignTenantRole('guru');
    });

    // Re-resolved model in B must not inherit A's roles relation.
    $context->run($b, fn () => expect(userInTenant('multi@test.local')->hasTenantRole('guru'))->toBeFalse());

    $context->run($a, fn () => expect(userInTenant('multi@test.local')->hasTenantRole('guru'))->toBeTrue());
});

it('does not let a provider session authenticate tenant routes', function () {
    /** @var ProviderUser $provider */
    $provider = ProviderUserFactory::new()->create();
    tenantForPermissions('sekolah-a');

    actingAs($provider, 'provider');

    get('http://sekolah-a.localhost/tenant-guard-probe')
        ->assertOk()
        ->assertSee('tenant-none', false);
});

it('does not let a web session authenticate provider routes', function () {
    tenantForPermissions('sekolah-a');

    $webUser = tenantUserFor('web@test.local');
    actingAs($webUser, 'web');

    get('http://localhost/provider-guard-probe')
        ->assertOk()
        ->assertSee('provider-none', false);
});
