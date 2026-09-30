<?php

namespace Modules\Identity\App\Infrastructure\Auth;

use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Support\Facades\App;
use Modules\Identity\App\Domain\Models\User;

/**
 * Mints a tenant-scoped password token via the password broker's
 * repository (the tenant-scoped subclass — the AMBIENT context decides
 * the tenant). Shared seam for the two token publishers:
 *
 * - ProvisionFirstAdmin (Stage 8, first school admin on approval)
 * - InviteUser (Stage 10, admin UI invitations)
 *
 * The broker exposes no public create-only API, so this reaches its
 * repository through the same reflection seam the reset flow uses —
 * guarded by FirstAdminProvisioningTest's seam test.
 *
 * Callers MUST run inside the target tenant's context (deleteExisting
 * is tenant-scoped, so a re-invite replaces only THIS tenant's token
 * row — the same email in another tenant is untouched).
 */
final class TenantTokenMinter
{
    /**
     * Mint a fresh plain token for the user. Any existing token row for
     * (tenant_id, email) is replaced — the previous link dies instantly.
     */
    public function mint(User $user): string
    {
        $broker = App::make('auth.password.broker');

        $repository = new \ReflectionProperty($broker, 'tokens');
        $repository->setAccessible(true);

        /** @var TokenRepositoryInterface $tokens */
        $tokens = $repository->getValue($broker);

        return $tokens->create($user);
    }
}
