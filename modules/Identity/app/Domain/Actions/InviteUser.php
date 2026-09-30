<?php

namespace Modules\Identity\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\App\Domain\Exceptions\InvitationNotAllowedException;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\App\Infrastructure\Auth\TenantTokenMinter;
use Modules\Identity\App\Infrastructure\Mail\SetPasswordMail;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantUrl;

/**
 * Email invitation from the school-admin UI (Fase 2 Stage 10) — the
 * ONLY new path is the entry point: the user and the set-password
 * acceptance are the Stage 8 machinery (one token table, one
 * acceptance page).
 *
 * Two shapes:
 *  - NEW email → user with a NULL password, verified at set-password.
 *  - EXISTING user with a NULL password → UPDATED (fresh name, extra
 *    role), and a NEW token replaces the old link (deleteExisting is
 *    tenant-scoped) with the mail re-sent. This is the deliberate
 *    replacement for a "resend invitation" button — a hungus link
 *    (60-minute TTL) is answered by inviting again.
 *
 * An ACTIVE user (has a password) may NOT be re-invited: their password
 * is theirs — an admin must never trigger a password-mail from the
 * invite path (send-reset exists for that).
 *
 * MUST run inside the TARGET tenant's context (the HTTP controller has
 * it from ResolveTenant; CLI/queue callers wrap in TenantContext::run):
 * user lookup/creation (BelongsToTenant), role resolution (Spatie
 * team), the token mint (tenant-scoped repository), and the mail's
 * tenant-host URL. Token + mail queueing are atomic (DB transaction) —
 * same as ProvisionFirstAdmin.
 */
final class InviteUser
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantUrl $tenantUrl,
        private readonly TenantTokenMinter $minter,
    ) {}

    /**
     * @param  array{name: string, email: string}  $data
     * @return User The invited (created or updated) user, persisted.
     */
    public function handle(array $data, ?string $roleName): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name'], 'password' => null],
        );

        if ($user->password !== null) {
            throw new InvitationNotAllowedException(
                'Akun dengan email ini sudah aktif — gunakan kirim tautan reset, bukan undangan.',
            );
        }

        // Fresh profile data — a re-invite also corrects the name.
        $user->forceFill(['name' => $data['name']])->save();

        if ($roleName !== null) {
            $user->assignTenantRole($roleName);
        }

        DB::transaction(function () use ($user): void {
            $token = $this->minter->mint($user);

            $url = $this->tenantUrl->root($this->context->currentOrFail()->id)
                .'/set-password?token='.$token.'&email='.urlencode($user->email);

            Mail::to($user->email)->queue(new SetPasswordMail($url, $user->name));
        });

        return $user;
    }
}
