<?php

namespace Modules\Identity\App\Infrastructure\Onboarding;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\App\Infrastructure\Auth\TenantTokenMinter;
use Modules\Identity\App\Infrastructure\Mail\SetPasswordMail;
use Modules\Platform\App\Contracts\Events\TenantApproved;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantUrl;

/**
 * Provisions the first school admin when Platform approves an
 * application (listens to TenantApproved, fired synchronously INSIDE
 * the approval transaction — a failure here rolls the whole approval
 * back, so no tenant ever exists without its admin).
 *
 * Runs from a central/CLI context without ambient tenant state, so
 * everything happens inside TenantContext::run($event->tenantId):
 * user creation (BelongsToTenant fills tenant_id), role assignment
 * (Spatie resolves the tenant's role), and the token mint (the tenant-
 * scoped token repository requires the context).
 *
 * When the application came from an applicant account, the event
 * carries that account's password hash: the admin takes it over
 * (email already verified) and NO set-password mail is sent. Without
 * a hash the admin starts with a null password and gets the link.
 *
 * The link points at the TENANT host (TenantUrl) — the account does
 * not exist on central. Idempotent: an email already provisioned for
 * THIS tenant is left alone (approval re-runs never duplicate).
 * email_verified_at is stamped at set-password acceptance, not here.
 */
final class ProvisionFirstAdmin
{
    /**
     * Role machine name — see modules/Identity/config/roles.php.
     */
    private const FIRST_ADMIN_ROLE = 'admin-sekolah';

    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantUrl $tenantUrl,
        private readonly TenantTokenMinter $minter,
    ) {}

    public function handle(TenantApproved $event): void
    {
        $this->context->run($event->tenantId, function () use ($event): void {
            $user = User::query()->firstOrCreate(
                [
                    'email' => $event->applicantEmail,
                ],
                [
                    'name' => $event->applicantName,
                    'password' => null,
                ],
            );

            // First admin role (create-if-missing within this tenant).
            // assignTenantRole is idempotent, so a re-run is harmless.
            $user->assignTenantRole(self::FIRST_ADMIN_ROLE);

            if ($user->password !== null) {
                // Already activated (re-approval, or a previous run
                // completed activation) — do not reset their password.
                return;
            }

            if ($event->passwordHash !== null) {
                // The applicant registered with a verified email and a
                // password of their own: the admin account takes both
                // over and needs no set-password link.
                $user->forceFill([
                    'password' => $event->passwordHash,
                    'email_verified_at' => now(),
                ])->save();

                return;
            }

            // Tenant-scoped token: mint inside the tenant context so
            // the row lands on (tenant_id, email). DB transaction keeps
            // token + mail queueing atomic within the approval.
            DB::transaction(function () use ($user, $event): void {
                $token = $this->minter->mint($user);

                $url = $this->tenantUrl->url($event->tenantId, 'set-password', [
                    'token' => $token,
                    'email' => $user->email,
                ]);

                Mail::to($user->email)->queue(new SetPasswordMail(
                    $url,
                    $event->applicantName,
                ));
            });
        });
    }
}
