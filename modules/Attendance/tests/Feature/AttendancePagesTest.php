<?php

namespace Modules\Attendance\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Absensi mockup pages: every route renders for a signed-in user of a
 * tenant that has the module enabled, and is blocked without the flag.
 */
function attendanceTenant(bool $enabled = true): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $enabled ? 'absensi-on' : 'absensi-off']);

    if ($enabled) {
        app(ModuleFlagManager::class)->enable($tenant->id, 'attendance');
    }

    $user = UserFactory::new()->forTenant($tenant->id)->create(['email' => "admin@{$tenant->slug}.test"]);

    app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole('admin-sekolah'));

    actingAs($user);

    return $tenant;
}

dataset('attendancePages', [
    'overview' => ['/absensi', 'Attendance/Overview'],
    'input' => ['/absensi/input', 'Attendance/Input'],
    'monthly' => ['/absensi/rekap', 'Attendance/Monthly'],
]);

it('renders each absensi page for a school user', function (string $path, string $component) {
    $tenant = attendanceTenant();

    get(school($tenant->slug, $path))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component($component)
    );
})->with('attendancePages');

it('lists the Absensi group in the sidebar when the module is enabled', function () {
    $tenant = attendanceTenant();

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect($nav)->pluck('label')->contains('Absensi'))
    );
});

it('hides and blocks Absensi for a tenant without the module', function () {
    $tenant = attendanceTenant(enabled: false);

    get(school($tenant->slug, '/absensi'))->assertForbidden();

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => ! collect($nav)->pluck('label')->contains('Absensi'))
    );
});
