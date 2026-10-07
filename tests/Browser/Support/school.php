<?php

use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

require_once __DIR__.'/hosts.php';

/*
 * Helpers for browser tests on the school side: a school with a signed-in
 * member, and a way to create that school's data.
 */

/**
 * Run a closure inside a school's tenant context (factories and queries of
 * tenant-scoped models need one).
 *
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function inTenant(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->run($tenant->id, $callback);
}

/**
 * Create a school with one member holding `$role`, sign them in through the
 * real login form (school code, email, password) and return their page and
 * the school. They land on the school's Beranda. `$modules` are switched on
 * for the school before the sign-in.
 *
 * @param  list<string>  $modules
 * @return array{0: mixed, 1: Tenant}
 */
function schoolMemberSignsIn(string $role = 'admin-sekolah', array $modules = []): array
{
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-uji']);
    $user = UserFactory::new()->forTenant($tenant->id)->create(['email' => "{$role}@sekolah-uji.test"]);

    inTenant($tenant, function () use ($tenant, $user, $role, $modules): void {
        foreach ($modules as $module) {
            app(ModuleFlagManager::class)->enable($tenant->id, $module);
        }

        $user->assignTenantRole($role);
    });

    onCentralHost();

    $page = visit('/login');

    $page->fill('school', $tenant->id)
        ->fill('login', "{$role}@sekolah-uji.test")
        ->fill('password', 'password')
        ->press('Masuk')
        ->assertPathIs('/beranda');

    return [$page, $tenant];
}
