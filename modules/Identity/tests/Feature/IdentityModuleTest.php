<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\App\Contracts\ResolvesUsers;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

test('user model is owned by the identity module and persisted', function () {
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create();
    $tenantId = $tenant->id;

    $user = User::factory()->forTenant($tenantId)->create([
        'email' => 'identity@example.com',
    ]);

    $row = DB::table('users')->where('email', 'identity@example.com')->first();

    expect($row)->not->toBeNull()
        ->and($row->tenant_id)->toBe($tenantId)
        ->and(Hash::check('password', $row->password))->toBeTrue()
        ->and($user->name)->toBe($row->name);
});

test('the identity module resolves users through its public contract', function () {
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create();
    $tenantId = $tenant->id;

    User::factory()->forTenant($tenantId)->create([
        'email' => 'resolver@example.com',
    ]);

    // User queries are tenant-scoped; resolution runs in context.
    $record = app(TenantContext::class)->run(
        $tenantId,
        fn () => app(ResolvesUsers::class)->findByEmail('resolver@example.com'),
    );

    expect($record)->not->toBeNull()
        ->and($record->email)->toBe('resolver@example.com')
        ->and($record->tenantId)->toBe($tenantId)
        ->and($record->id)->toBeInt();

    $missing = app(TenantContext::class)->run(
        $tenantId,
        fn () => app(ResolvesUsers::class)->findByEmail('missing@example.com'),
    );

    expect($missing)->toBeNull();
});
