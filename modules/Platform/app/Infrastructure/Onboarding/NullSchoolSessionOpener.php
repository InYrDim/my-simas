<?php

namespace Modules\Platform\App\Infrastructure\Onboarding;

use Modules\Platform\App\Contracts\SchoolSessionOpener;

/**
 * Platform's own SchoolSessionOpener: refuses everything. Identity
 * replaces this binding with the implementation that knows school users;
 * the fallback exists so Platform never opens a session by itself.
 */
final class NullSchoolSessionOpener implements SchoolSessionOpener
{
    public function attempt(string $tenantId, string $email, #[\SensitiveParameter] string $password, bool $remember = false): bool
    {
        return false;
    }
}
