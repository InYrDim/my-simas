<?php

namespace Modules\Identity\App\Infrastructure\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\SchoolSessionOpener;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantDirectory;
use Modules\Platform\App\Contracts\TenantSession;

/**
 * Identity's implementation of Platform's SchoolSessionOpener: the same
 * rules as the school login (credentials checked inside the school's own
 * context, deactivated accounts refused), minus the school code — the
 * caller already knows which school the person belongs to.
 *
 * Used by Platform's applicant login once an application is approved, so
 * a new school admin lands in their school with the email and password
 * they registered with.
 */
final class DefaultSchoolSessionOpener implements SchoolSessionOpener
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantDirectory $tenants,
        private readonly TenantSession $session,
    ) {}

    public function attempt(string $tenantId, string $email, #[\SensitiveParameter] string $password, bool $remember = false): bool
    {
        $tenant = $this->tenants->findMany([$tenantId])[$tenantId] ?? null;

        // A suspended school answers 403 on every request; do not hand
        // out a session that can only hit that wall.
        if ($tenant === null || $tenant->status->value !== 'active') {
            return false;
        }

        return $this->context->run($tenantId, function () use ($tenantId, $email, $password, $remember): bool {
            $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

            if ($user === null
                || $user->tenant_id !== $tenantId
                || ! $user->isActive()
                || $user->password === null
                || ! Hash::check($password, $user->password)) {
                return false;
            }

            Auth::guard('web')->login($user, $remember);
            $this->session->remember($tenantId);

            return true;
        });
    }
}
