<?php

namespace Modules\Core\Tests\Feature;

use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\Database\Factories\TenantFactory;

it('reproduces: log in through the form, then land on beranda', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'sdn-repro']);
    $admin = UserFactory::new()->forTenant($tenant->id)->create([
        'email' => 'admin@sdn-repro.test',
    ]);
    app(TenantContext::class)->run($tenant->id, fn () => $admin->assignTenantRole('admin-sekolah'));

    $this->post(school('sdn-repro', '/login'), [
        'school' => schoolId('sdn-repro'),
        'email' => 'admin@sdn-repro.test',
        'password' => 'password',
    ])->assertRedirect();

    $this->withoutExceptionHandling();

    $this->get(school('sdn-repro', '/beranda'))->assertOk();
});
