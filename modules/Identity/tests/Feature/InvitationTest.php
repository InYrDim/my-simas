<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\App\Domain\Actions\InviteUser;
use Modules\Identity\App\Domain\Exceptions\InvitationNotAllowedException;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\App\Infrastructure\Auth\TenantTokenMinter;
use Modules\Identity\App\Infrastructure\Mail\SetPasswordMail;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Stage 10 (Fase 2): email invitations from the school-admin UI.
 *
 * The acceptance page, token table, and mailable are the Stage 8
 * machinery — only the entry point is new (invite action from the
 * admin UI). Re-inviting an invited user UPDATES it (fresh name/role,
 * fresh token replacing the old link) instead of duplicating — the
 * deliberate replacement for a resend button.
 */
function invTenant(string $slug): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $slug]);

    app(ModuleFlagManager::class)->enable($tenant->id, 'identity');

    return $tenant;
}

function invAdmin(Tenant $tenant, string $email = 'admin@invite.test'): User
{
    $admin = User::factory()->forTenant($tenant->id)->create([
        'email' => $email,
        'password' => 'SandiRahasia1!',
    ]);

    app(TenantContext::class)->run($tenant->id, function () use ($admin): void {
        $admin->assignTenantRole('admin-sekolah');
    });

    return $admin;
}

function invitedRow(string $tenantId, string $email): ?object
{
    return DB::table('users')
        ->where('tenant_id', $tenantId)
        ->where('email', $email)
        ->first();
}

function invTokenRow(string $tenantId, string $email): ?object
{
    return DB::table('password_reset_tokens')
        ->where('tenant_id', $tenantId)
        ->where('email', $email)
        ->first();
}

function mintPlainToken(string $tenantId, string $email): string
{
    // The invite mail's own token is hashed at rest — tests mint a
    // fresh plain token through the same seam (the new row replaces
    // the old link, exactly like a re-invite would).
    return app(TenantContext::class)->run(
        $tenantId,
        fn (): string => app(TenantTokenMinter::class)->mint(
            User::query()->where('email', $email)->firstOrFail(),
        ),
    );
}

beforeEach(function () {
    User::flushTenantPermissionCache();
});

it('invites a new user: password-null row, token, tenant-host mail', function () {
    Mail::fake();

    $tenant = invTenant('inv-a');
    $admin = invAdmin($tenant);

    actingAs($admin)
        ->post(school('inv-a', '/users/invite'), [
            'name' => 'Bu Sari',
            'email' => 'sari@inv-a.test',
            'role' => 'guru',
        ])
        ->assertRedirect(school('inv-a', '/users'))
        ->assertSessionHas('status');

    $row = invitedRow($tenant->id, 'sari@inv-a.test');

    expect($row)->not->toBeNull()
        ->and($row->password)->toBeNull()
        ->and($row->email_verified_at)->toBeNull()
        ->and(invTokenRow($tenant->id, 'sari@inv-a.test'))->not->toBeNull();

    Mail::assertQueued(SetPasswordMail::class, fn (SetPasswordMail $mail): bool => str_contains($mail->setPasswordUrl, 'school='.$tenant->id)
        && str_contains($mail->setPasswordUrl, '/set-password?token='));
});

it('completes the full loop: invite, set password, login', function () {
    Mail::fake();

    $tenant = invTenant('inv-b');
    $admin = invAdmin($tenant);

    actingAs($admin)
        ->post(school('inv-b', '/users/invite'), [
            'name' => 'Pak Budi',
            'email' => 'budi@inv-b.test',
            'role' => 'staf-tu',
        ])
        ->assertRedirect();

    Mail::assertQueuedCount(1);

    $plainToken = mintPlainToken($tenant->id, 'budi@inv-b.test');

    // The admin session from the invite POST must end — the
    // acceptance route is guest-only and would bounce it.
    auth()->guard('web')->logout();

    post(school('inv-b', '/set-password'), [
        'token' => $plainToken,
        'email' => 'budi@inv-b.test',
        'password' => 'SandiBaruKuat123!',
        'password_confirmation' => 'SandiBaruKuat123!',
    ])
        ->assertRedirect(route('home'));

    $row = invitedRow($tenant->id, 'budi@inv-b.test');

    expect($row->password)->not->toBeNull()
        ->and($row->email_verified_at)->not->toBeNull()
        ->and(auth()->check())->toBeTrue();

    // The activated account can log in on its own host.
    auth()->guard('web')->logout();

    post(school('inv-b', '/login'), [
        'school' => schoolId('inv-b'),
        'email' => 'budi@inv-b.test',
        'password' => 'SandiBaruKuat123!',
    ])->assertRedirect();

    expect(auth()->user()?->email)->toBe('budi@inv-b.test');
});

it('re-inviting an invited user updates instead of duplicating and replaces the token', function () {
    Mail::fake();

    $tenant = invTenant('inv-c');
    $admin = invAdmin($tenant);

    actingAs($admin)
        ->post(school('inv-c', '/users/invite'), [
            'name' => 'Nama Awal',
            'email' => 'ulang@inv-c.test',
        ])
        ->assertRedirect();

    $firstTokenHash = invTokenRow($tenant->id, 'ulang@inv-c.test')->token;

    // Invite again: corrected name, adds a role — same row, no dup.
    actingAs($admin)
        ->post(school('inv-c', '/users/invite'), [
            'name' => 'Nama Dikoreksi',
            'email' => 'ulang@inv-c.test',
            'role' => 'guru',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(DB::table('users')
        ->where('tenant_id', $tenant->id)
        ->where('email', 'ulang@inv-c.test')
        ->count())->toBe(1);

    $row = invitedRow($tenant->id, 'ulang@inv-c.test');

    expect($row->name)->toBe('Nama Dikoreksi')
        ->and($row->password)->toBeNull();

    // The token row was REPLACED (new hash — the old link is dead).
    expect(invTokenRow($tenant->id, 'ulang@inv-c.test')->token)
        ->not->toBe($firstTokenHash);

    Mail::assertQueuedCount(2);

    app(TenantContext::class)->run($tenant->id, function () use ($row): void {
        expect(User::query()->findOrFail($row->id)->hasTenantRole('guru'))->toBeTrue();
    });
});

it('refuses inviting an already-active account', function () {
    Mail::fake();

    $tenant = invTenant('inv-d');
    $admin = invAdmin($tenant);

    // The target email already belongs to an ACTIVE account.
    User::factory()->forTenant($tenant->id)->create([
        'email' => 'aktif@inv-d.test',
        'password' => 'SandiRahasia1!',
    ]);

    actingAs($admin)
        ->post(school('inv-d', '/users/invite'), [
            'name' => 'Sudah Aktif',
            'email' => 'aktif@inv-d.test',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    Mail::assertNothingQueued();

    // Same rule at the domain level.
    app(TenantContext::class)->run($tenant->id, function (): void {
        app(InviteUser::class)->handle(
            ['name' => 'Sudah Aktif', 'email' => 'aktif@inv-d.test'],
            null,
        );
    });
})->throws(InvitationNotAllowedException::class);

it('an expired invitation token is refused', function () {
    Mail::fake();

    $tenant = invTenant('inv-e');
    invAdmin($tenant);

    actingAs(invAdmin($tenant, 'admin2@inv-e.test'))
        ->post(school('inv-e', '/users/invite'), [
            'name' => 'Kadaluarsa',
            'email' => 'hangus@inv-e.test',
        ])
        ->assertRedirect();

    $plainToken = mintPlainToken($tenant->id, 'hangus@inv-e.test');

    // End the admin session — the acceptance route is guest-only.
    auth()->guard('web')->logout();

    // Age the token row beyond the 60-minute TTL (same row the link
    // would read — the TTL applies to whatever token is in the mail).
    DB::table('password_reset_tokens')
        ->where('tenant_id', $tenant->id)
        ->where('email', 'hangus@inv-e.test')
        ->update(['created_at' => now()->subHours(2)]);

    post(school('inv-e', '/set-password'), [
        'token' => $plainToken,
        'email' => 'hangus@inv-e.test',
        'password' => 'SandiBaruKuat123!',
        'password_confirmation' => 'SandiBaruKuat123!',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    expect(invitedRow($tenant->id, 'hangus@inv-e.test')->password)->toBeNull();
});

it('a consumed token is one-shot: second acceptance fails', function () {
    Mail::fake();

    $tenant = invTenant('inv-f');
    invAdmin($tenant);

    actingAs(invAdmin($tenant, 'admin2@inv-f.test'))
        ->post(school('inv-f', '/users/invite'), [
            'name' => 'Sekali Pakai',
            'email' => 'sekali@inv-f.test',
        ])
        ->assertRedirect();

    $plainToken = mintPlainToken($tenant->id, 'sekali@inv-f.test');

    // End the admin session — the acceptance route is guest-only.
    auth()->guard('web')->logout();

    post(school('inv-f', '/set-password'), [
        'token' => $plainToken,
        'email' => 'sekali@inv-f.test',
        'password' => 'SandiBaruKuat123!',
        'password_confirmation' => 'SandiBaruKuat123!',
    ])->assertRedirect(route('home'));

    auth()->guard('web')->logout();

    // Second attempt with the SAME token: the row is gone (one-shot).
    post(school('inv-f', '/set-password'), [
        'token' => $plainToken,
        'email' => 'sekali@inv-f.test',
        'password' => 'SandiLainKuat456!',
        'password_confirmation' => 'SandiLainKuat456!',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');
});

it('a deactivated user cannot accept an invitation', function () {
    Mail::fake();

    $tenant = invTenant('inv-g');
    invAdmin($tenant);

    actingAs(invAdmin($tenant, 'admin2@inv-g.test'))
        ->post(school('inv-g', '/users/invite'), [
            'name' => 'Dinonaktifkan',
            'email' => 'nonaktif@inv-g.test',
        ])
        ->assertRedirect();

    DB::table('users')
        ->where('tenant_id', $tenant->id)
        ->where('email', 'nonaktif@inv-g.test')
        ->update(['deactivated_at' => now()]);

    $plainToken = mintPlainToken($tenant->id, 'nonaktif@inv-g.test');

    // End the admin session — the acceptance route is guest-only.
    auth()->guard('web')->logout();

    post(school('inv-g', '/set-password'), [
        'token' => $plainToken,
        'email' => 'nonaktif@inv-g.test',
        'password' => 'SandiBaruKuat123!',
        'password_confirmation' => 'SandiBaruKuat123!',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    $row = invitedRow($tenant->id, 'nonaktif@inv-g.test');

    expect($row->password)->toBeNull()
        ->and($row->email_verified_at)->toBeNull();
});

it('an invited user without a password cannot log in', function () {
    $tenant = invTenant('inv-h');
    $admin = invAdmin($tenant);

    actingAs($admin)
        ->post(school('inv-h', '/users/invite'), [
            'name' => 'Belum Aktif',
            'email' => 'belum@inv-h.test',
        ])
        ->assertRedirect();

    auth()->guard('web')->logout();

    // A NULL password verifies against nothing — login refuses with
    // the generic error (explicit pin from the plan).
    post(school('inv-h', '/login'), [
        'school' => schoolId('inv-h'),
        'email' => 'belum@inv-h.test',
        'password' => 'SandiRahasia1!',
    ])->assertSessionHasErrors('email');

    expect(auth()->user())->toBeNull();
});

it('guru cannot reach the invitation routes', function () {
    $tenant = invTenant('inv-i');
    $guru = User::factory()->forTenant($tenant->id)->create([
        'email' => 'guru@inv-i.test',
        'password' => 'SandiRahasia1!',
    ]);

    app(TenantContext::class)->run($tenant->id, function () use ($guru): void {
        $guru->assignTenantRole('guru');
    });

    Mail::fake();

    actingAs($guru);

    get(school('inv-i', '/users/invite'))->assertForbidden();

    post(school('inv-i', '/users/invite'), [
        'name' => 'X', 'email' => 'x@inv-i.test',
    ])->assertForbidden();

    Mail::assertNothingQueued();
});

it('invitations are rate limited per tenant, email, and ip', function () {
    Mail::fake();

    $tenant = invTenant('inv-j');
    $admin = invAdmin($tenant);

    actingAs($admin);

    // The bucket key is (tenant, email, ip): five invites for the same
    // address pass, the sixth is throttled.
    foreach (range(1, 6) as $attempt) {
        $response = post(school('inv-j', '/users/invite'), [
            'name' => 'Uji '.$attempt,
            'email' => 'banyak@inv-j.test',
        ]);

        if ($attempt < 6) {
            $response->assertRedirect(school('inv-j', '/users'))
                ->assertSessionHasNoErrors();
        } else {
            $response->assertSessionHasErrors('email');
        }
    }
});
