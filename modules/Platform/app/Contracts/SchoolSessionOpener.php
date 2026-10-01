<?php

namespace Modules\Platform\App\Contracts;

/**
 * Opens a school session for someone who proves they are a user of that
 * school. Platform DEFINES this (its applicant login needs it once an
 * application is approved) but cannot implement it: school users belong
 * to Identity, a layer above. Identity binds the real implementation;
 * Platform's own default always refuses, so without Identity nothing is
 * ever opened.
 */
interface SchoolSessionOpener
{
    /**
     * Check the credentials against the school's users and, when they
     * match an active account, sign that user in on the school guard and
     * remember the school in the session.
     *
     * Returns false for every failure alike — unknown or suspended
     * school, unknown email, wrong password, deactivated account — so
     * the caller can answer generically.
     */
    public function attempt(string $tenantId, string $email, #[\SensitiveParameter] string $password, bool $remember = false): bool;
}
