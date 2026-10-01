<?php

namespace Modules\Identity\App\Contracts;

/**
 * Counts of a tenant's accounts, for modules that must report on a
 * school without importing the User model.
 *
 * The three states partition `total`:
 *   - `active`              — can sign in right now
 *   - `awaitingActivation`  — invited, password still null, waiting on a human
 *   - `deactivated`         — refused at login; rows and roles kept for audit
 *
 * Deactivated accounts are rows the tenant still owns, so they stay in
 * `total` and are never presented as missing people.
 */
readonly class UserSummary
{
    public function __construct(
        /** Every account row in the tenant, in any state. */
        public int $total,
        /** Not deactivated and holding a password — can sign in. */
        public int $active,
        /** Not deactivated, password still null — invited, not yet activated. */
        public int $awaitingActivation,
        /** Deactivated at; rows and roles kept so reactivation restores access. */
        public int $deactivated,
        /** Holding no role at all in this tenant. */
        public int $withoutRole,
    ) {}
}
