<?php

namespace Modules\Attendance\App\Http\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * The signed-in account as the plain id attendance stores and asks Core
 * about; the User model itself stays in Identity.
 */
trait KnowsSignedInUser
{
    protected function signedInUserId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
