<?php

namespace Modules\Core\App\Http\Concerns;

use Illuminate\Support\Facades\Gate;
use Modules\Identity\App\Contracts\ResolvesUsers;

/**
 * The login account behind a student's or a teacher's `user_id`, as the
 * detail pages show it, read through Identity's contract.
 */
trait DescribesLinkedAccount
{
    /**
     * @return array{account: array{username: string|null, email: string|null, active: bool, mustChangePassword: bool}|null, can: array{create: bool, reset: bool}}
     */
    protected function linkedAccount(?int $userId): array
    {
        $record = $userId === null ? null : (app(ResolvesUsers::class)->findMany([$userId])[$userId] ?? null);

        return [
            'account' => $record === null ? null : [
                'username' => $record->username,
                'email' => $record->email,
                'active' => $record->active,
                'mustChangePassword' => $record->mustChangePassword,
            ],
            'can' => [
                'create' => Gate::allows('core.master.manage') && Gate::allows('identity.users.create'),
                'reset' => Gate::allows('core.master.manage') && Gate::allows('identity.users.sendReset'),
            ],
        ];
    }
}
