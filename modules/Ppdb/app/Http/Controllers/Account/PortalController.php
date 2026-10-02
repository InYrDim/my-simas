<?php

namespace Modules\Ppdb\App\Http\Controllers\Account;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Domain\Queries\PortalState;

/**
 * The applicant's own page (`/calon-siswa`): where they stand and what to
 * do next.
 */
final class PortalController
{
    public function __invoke(PortalState $state): Response
    {
        /** @var PpdbAccount $account */
        $account = Auth::guard('ppdb')->user();

        return Inertia::render('Ppdb/Account/Home', [
            'account' => ['name' => $account->name, 'email' => $account->email],
            'portal' => $state->for($account),
        ]);
    }
}
