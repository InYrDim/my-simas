<?php

namespace Modules\Identity\App\Domain\Actions;

use Modules\Identity\App\Domain\Models\User;

/**
 * Reactivate a previously deactivated user: clear the audit stamp and
 * access resumes immediately (roles were never removed).
 */
final class ReactivateUser
{
    public function handle(User $target): void
    {
        $target->forceFill(['deactivated_at' => null])->save();
    }
}
