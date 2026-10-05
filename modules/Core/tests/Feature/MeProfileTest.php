<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * "Saya" › Profil: read only, the signed-in account's own record and
 * nothing else.
 */
it('shows a student only their own record', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'me-siswa']);
    $account = UserFactory::new()->forTenant($tenant->id)->withUsername('71001')->create();

    app(TenantContext::class)->run($tenant->id, function () use ($account): void {
        $account->assignTenantRole('siswa');

        Student::factory()->create(['name' => 'Aditya Nugraha', 'nis' => '71001'])
            ->forceFill(['user_id' => $account->id])->save();
        Student::factory()->create(['name' => 'Siswa Lain', 'nis' => '71002']);
    });

    actingAs($account->fresh());

    get(school($tenant->slug, '/saya/profil'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Core/Me/Profile')
        ->where('person.kind', 'student')
        ->where('person.name', 'Aditya Nugraha')
        ->where('person.nis', '71001')
        ->where('account.login', '71001')
        ->where('account.roles', ['Siswa']));
});

it('shows a teacher their duty and an unlinked account no person', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'me-guru']);
    $guru = UserFactory::new()->forTenant($tenant->id)->create(['email' => 'guru@me-guru.test']);
    $staf = UserFactory::new()->forTenant($tenant->id)->create(['email' => 'staf@me-guru.test']);

    app(TenantContext::class)->run($tenant->id, function () use ($guru, $staf): void {
        $guru->assignTenantRole('guru');
        $staf->assignTenantRole('staf-tu');

        Teacher::factory()->create(['name' => 'Bu Sari', 'duty' => 'Guru Matematika'])
            ->forceFill(['user_id' => $guru->id])->save();
    });

    actingAs($guru->fresh());

    get(school($tenant->slug, '/saya/profil'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('person.kind', 'teacher')
        ->where('person.duty', 'Guru Matematika')
        ->where('account.roles', ['Guru']));

    actingAs($staf->fresh());

    get(school($tenant->slug, '/saya/profil'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('person', null));
});

it('keeps the profile page from a role without core.me.view', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'me-admin']);
    $admin = UserFactory::new()->forTenant($tenant->id)->create(['email' => 'admin@me-admin.test']);

    app(TenantContext::class)->run($tenant->id, fn () => $admin->assignTenantRole('admin-sekolah'));

    actingAs($admin->fresh());

    get(school($tenant->slug, '/saya/profil'))->assertForbidden();
});
