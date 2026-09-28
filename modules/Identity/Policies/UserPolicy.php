<?php

namespace Modules\Identity\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Identity\Models\User;

class UserPolicy
{
    use HandlesAuthorization;
}
