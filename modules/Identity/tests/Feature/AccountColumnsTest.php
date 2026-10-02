<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\Database\Factories\TenantFactory;

/*
 * Accounts that sign in by username: the columns added for students
 * (NIS) and teachers (NIP).
 */

it('stores an account with a username and no email', function () {
    $tenant = TenantFactory::new()->create();

    $user = User::factory()->forTenant($tenant->id)->withUsername('24001')->mustChangePassword()->create();

    expect($user->refresh())
        ->username->toBe('24001')
        ->email->toBeNull()
        ->must_change_password->toBeTrue();
});

it('leaves an ordinary account without a username and free of the password flag', function () {
    $tenant = TenantFactory::new()->create();

    $user = User::factory()->forTenant($tenant->id)->create();

    expect($user->refresh())
        ->username->toBeNull()
        ->must_change_password->toBeFalse();
});

it('allows several accounts without an email in one school', function () {
    $tenant = TenantFactory::new()->create();

    User::factory()->forTenant($tenant->id)->withUsername('24001')->create();
    User::factory()->forTenant($tenant->id)->withUsername('24002')->create();

    $count = app(TenantContext::class)->run($tenant->id, fn (): int => User::query()->whereNull('email')->count());

    expect($count)->toBe(2);
});

it('allows the same username in two schools', function () {
    $a = TenantFactory::new()->create();
    $b = TenantFactory::new()->create();

    User::factory()->forTenant($a->id)->withUsername('24001')->create();
    $second = User::factory()->forTenant($b->id)->withUsername('24001')->create();

    expect($second->exists)->toBeTrue();
});

it('refuses the same username twice in one school', function () {
    $tenant = TenantFactory::new()->create();

    User::factory()->forTenant($tenant->id)->withUsername('24001')->create();
    User::factory()->forTenant($tenant->id)->withUsername('24001')->create();
})->throws(UniqueConstraintViolationException::class);

it('sends no reset mail to an account without an email', function () {
    Mail::fake();

    $tenant = TenantFactory::new()->create();
    $user = User::factory()->forTenant($tenant->id)->withUsername('24001')->create();

    app(TenantContext::class)->run($tenant->id, function () use ($user): void {
        $user->sendPasswordResetNotification('token');
    });

    Mail::assertNothingQueued();
});
