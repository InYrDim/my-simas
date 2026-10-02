<?php

namespace Modules\Identity\App\Domain\Actions;

use Illuminate\Support\Facades\Password;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Send a password reset link to a tenant user from the school-admin
 * UI (Fase 2 Stage 9) — the same tenant-scoped machinery as the
 * public "forgot password" flow (Stage 4): the tenant-scoped broker
 * mints the token and User::sendPasswordResetNotification() queues
 * the mail with a tenant-host URL.
 *
 * The eligibility gates live in sendPasswordResetNotification: a
 * deactivated user or one without a password gets NO email — the
 * admin re-invites a never-activated user instead (Stage 10).
 */
final class SendResetLink
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * Returns true when the user is a reset candidate (active, with a
     * password and an email) — i.e. an email was actually queued. The controller
     * re-checks the policy before calling, so false only surfaces for
     * invited-but-not-yet-activated accounts.
     */
    public function handle(User $target): bool
    {
        if (! $target->isActive() || $target->password === null || $target->email === null) {
            return false;
        }

        $this->context->run($target->tenant_id, function () use ($target): void {
            // Ensures the broker's tenant-stamped repository writes the
            // row for the TARGET's tenant, even from CLI/queue callers.
            Password::broker()->sendResetLink(['email' => $target->email]);
        });

        return true;
    }
}
