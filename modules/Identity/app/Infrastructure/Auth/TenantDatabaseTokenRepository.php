<?php

namespace Modules\Identity\App\Infrastructure\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * DatabaseTokenRepository scoped to the CURRENT tenant.
 *
 * The default repository keys token rows by email alone, which crosses
 * tenants (the Fase 2 plan's single most important auth fix). This
 * subclass adds `tenant_id` from the ambient TenantContext to every
 * payload and scopes every lookup/insert/delete by it — a token minted
 * in tenant A is never found on tenant B, even for the same email.
 *
 * Callers MUST run inside the target tenant's context (HTTP requests on
 * a school subdomain always do; CLI flows wrap themselves). Without a
 * context the repository fails closed with TenantNotSetException — it
 * must never fall back to an email-only row.
 */
final class TenantDatabaseTokenRepository extends DatabaseTokenRepository
{
    public function __construct(
        ConnectionInterface $connection,
        HasherContract $hasher,
        string $table,
        #[\SensitiveParameter] string $hashKey,
        int $expires,
        int $throttle,
        private readonly TenantContext $context,
    ) {
        parent::__construct($connection, $hasher, $table, $hashKey, $expires, $throttle);
    }

    /**
     * The tenant id required on every token row.
     */
    private function requiredTenantId(): string
    {
        return $this->context->id()
            ?? throw new TenantNotSetException(
                'Password tokens are tenant-scoped; run inside TenantContext::run().',
            );
    }

    /**
     * Tenant-scoped query against the token table.
     */
    private function tenantTable(): Builder
    {
        return $this->getTable()
            ->where('tenant_id', $this->requiredTenantId());
    }

    /**
     * Insert the tenant id alongside the default payload.
     *
     * @param  string  $email
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getPayload($email, #[\SensitiveParameter] $token)
    {
        return [
            ...parent::getPayload($email, $token),
            'tenant_id' => $this->requiredTenantId(),
        ];
    }

    /**
     * Delete only THIS tenant's rows for the email (deleteExisting).
     */
    protected function deleteExisting(CanResetPasswordContract $user)
    {
        return $this->tenantTable()
            ->where('email', $user->getEmailForPasswordReset())
            ->delete();
    }

    /**
     * Token existence/validity is judged against this tenant's rows only.
     */
    public function exists(CanResetPasswordContract $user, #[\SensitiveParameter] $token)
    {
        $record = (array) $this->tenantTable()
            ->where('email', $user->getEmailForPasswordReset())
            ->first();

        return $record
            && ! $this->tokenExpired($record['created_at'])
            && $this->hasher->check($token, $record['token']);
    }

    /**
     * Throttle check (recentlyCreatedToken) is tenant-scoped too.
     */
    public function recentlyCreatedToken(CanResetPasswordContract $user)
    {
        $record = (array) $this->tenantTable()
            ->where('email', $user->getEmailForPasswordReset())
            ->first();

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    /**
     * Deletes ALL expired rows across tenants: created_at alone
     * identifies them, and garbage collection has no security meaning.
     */
    public function deleteExpired()
    {
        $expiredAt = Carbon::now()->subSeconds($this->expires);

        $this->getTable()->where('created_at', '<', $expiredAt)->delete();
    }
}
