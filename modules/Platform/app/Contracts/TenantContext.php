<?php

namespace Modules\Platform\App\Contracts;

/**
 * Holds the current tenant for this request/worker/process. Resolved by
 * the ResolveTenant middleware (HTTP) or restored on queue jobs; CLI
 * uses `tenant:run`. Central requests have NO context — consumers must
 * opt in via currentOrFail()/id() and handle the absent case.
 */
interface TenantContext
{
    /**
     * The current tenant, or null when running centrally (no context).
     */
    public function current(): ?TenantData;

    /**
     * The current tenant, or throw TenantNotSetException.
     *
     * @throws Exceptions\TenantNotSetException
     */
    public function currentOrFail(): TenantData;

    /**
     * The current tenant id, or null when running centrally.
     */
    public function id(): ?string;

    /**
     * The current tenant timezone, falling back to the configured
     * default when running centrally.
     */
    public function timezone(): string;

    /**
     * Activate a tenant for the remainder of the request (or until
     * forget()/run() restores). Also bridges to the permission layer.
     */
    public function set(string $tenantId): void;

    /**
     * Clear tenant context (e.g. terminator, worker restore).
     */
    public function forget(): void;

    /**
     * Run a callback with a tenant set, restoring the previous context
     * afterwards (safe to nest). Returns the callback's return value.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function run(string $tenantId, callable $callback): mixed;

    /**
     * Run a callback explicitly without tenant context (e.g. central
     * maintenance, cross-tenant queries), restoring afterwards.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runWithoutTenant(callable $callback): mixed;
}
